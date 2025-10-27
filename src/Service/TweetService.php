<?php

namespace App\Service;

use App\Entity\Tweet;
use App\Entity\User;
use App\Event\TweetLikedEvent;
use App\Event\TweetUnlikedEvent;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TweetService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TweetRepository $tweetRepository,
        private EventDispatcherInterface $eventDispatcher,
        private HashtagService $hashtagService,
        private MentionService $mentionService
    ) {}

    /**
     * Create new tweet
     */
    public function createTweet(User $user, string $content, ?string $imagePath = null, ?Tweet $parent = null): Tweet
    {
        $tweet = new Tweet();
        $tweet->setUser($user);
        $tweet->setContent($content);

        if ($imagePath) {
            $tweet->setImagePath($imagePath);
        }

        if ($parent) {
            $tweet->setParent($parent);
        }

        $this->em->persist($tweet);
        $this->em->flush();

        $this->hashtagService->processTweetHashtags($tweet);

        $this->mentionService->processTweetMentions($tweet);

        return $tweet;
    }

    /**
     * Delete tweet (with ownership check)
     */
    public function deleteTweet(Tweet $tweet, User $user): void
    {
        if ($tweet->getUser() !== $user) {
            throw new \RuntimeException('Bu tweet sizin deyil!');
        }
        $this->hashtagService->removeTweetHashtags($tweet);

        $this->em->remove($tweet);
        $this->em->flush();
    }

    /**
     * Toggle like (returns new like status)
     */
    public function toggleLike(User $user, Tweet $tweet): bool
    {
        $isLiked = $tweet->isLikedBy($user);

        if ($isLiked) {
            $tweet->removeLike($user);
            $this->eventDispatcher->dispatch(new TweetUnlikedEvent($user, $tweet));
        } else {
            $tweet->addLike($user);
            $this->eventDispatcher->dispatch(new TweetLikedEvent($user, $tweet));
        }

        $this->em->flush();

        return !$isLiked; // Return new status
    }

    /**
     * Toggle retweet (returns new retweet status)
     */
    public function toggleRetweet(User $user, Tweet $originalTweet): array
    {
        // Validation
        if ($originalTweet->getUser() === $user) {
            throw new \InvalidArgumentException('Öz tweet-inizi retweet edə bilməzsiniz');
        }

        if ($originalTweet->isRetweet()) {
            throw new \InvalidArgumentException('Retweet-i retweet edə bilməzsiniz');
        }

        // Check existing retweet
        $existingRetweet = $this->tweetRepository->findOneBy([
            'user' => $user,
            'originalTweet' => $originalTweet
        ]);

        if ($existingRetweet) {
            // Undo retweet
            $this->em->remove($existingRetweet);
            $this->em->flush();

            return [
                'retweeted' => false,
                'count' => $originalTweet->getRetweetsCount()
            ];
        }

        // Create new retweet
        $retweet = new Tweet();
        $retweet->setUser($user);
        $retweet->setOriginalTweet($originalTweet);
        $retweet->setContent('[RETWEET]');

        $this->em->persist($retweet);
        $this->em->flush();

        return [
            'retweeted' => true,
            'count' => $originalTweet->getRetweetsCount()
        ];
    }

    /**
     * Get timeline for user (following + own tweets)
     */
    public function getTimeline(User $user, int $page = 1, int $limit = 20)
    {
        return $this->tweetRepository->getFollowingTimelinePaginated($user, $page, $limit);
    }

    /**
     * Get public timeline (for guests)
     */
    public function getPublicTimeline(int $limit = 50): array
    {
        return $this->tweetRepository->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Get replies for tweet
     */
    public function getReplies(Tweet $tweet): array
    {
        return $this->tweetRepository->findBy(
            ['parent' => $tweet],
            ['createdAt' => 'ASC']
        );
    }
}