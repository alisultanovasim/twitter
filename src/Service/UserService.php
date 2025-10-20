<?php

namespace App\Service;

use App\Entity\User;
use App\Event\UserFollowedEvent;
use App\Event\UserUnfollowedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Toggle follow/unfollow (returns new follow status)
     */
    public function toggleFollow(User $currentUser, User $targetUser): array
    {
        // Validation
        if ($currentUser === $targetUser) {
            throw new \InvalidArgumentException('Özünüzü follow edə bilməzsiniz');
        }

        $isFollowing = $currentUser->isFollowing($targetUser);

        if ($isFollowing) {
            // Unfollow
            $currentUser->unfollow($targetUser);
            $this->eventDispatcher->dispatch(new UserUnfollowedEvent($currentUser, $targetUser));
        } else {
            // Follow
            $currentUser->follow($targetUser);
            $this->eventDispatcher->dispatch(new UserFollowedEvent($currentUser, $targetUser));
        }

        $this->em->flush();

        return [
            'following' => !$isFollowing,
            'followers_count' => $targetUser->getFollowersCount(),
            'following_count' => $currentUser->getFollowingCount()
        ];
    }

    /**
     * Get user's tweets
     */
    public function getUserTweets(User $user): array
    {
        return $user->getTweets()->toArray();
    }
}