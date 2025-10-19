<?php

namespace App\Controller;

use App\Entity\Tweet;
use App\Form\TweetType;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\NotificationService;

class TweetController extends AbstractController
{
    #[Route('/tweets', name: 'tweet_list', methods: ['GET'])]
    public function list(TweetRepository $tweetRepository): Response
    {
        $thisUser = $this->getUser();
        if (!$thisUser) {
            // Qonaq üçün public timeline
            $tweets = $tweetRepository->findBy([], ['createdAt' => 'DESC'], 50);
        } else {
            // Login user üçün following timeline
            $tweets = $tweetRepository->getFollowingTimeline($thisUser, 50);
        }

        return $this->render('tweet/list.html.twig', ['tweets' => $tweets]);
    }

    #[Route('/tweet/new', name: 'tweet_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]  // Yalnız login user
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $tweet = new Tweet();
        $form = $this->createForm(TweetType::class, $tweet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tweet->setUser($this->getUser());

            $em->persist($tweet);
            $em->flush();

            $this->addFlash('success', 'Tweet yaradıldı!');

            return $this->redirectToRoute('tweet_list');
        }

        return $this->render('tweet/create.html.twig', [
            'form' => $form,
        ]);
    }
    #[Route('/tweet/{id}/delete', name: 'tweet_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Tweet $tweet, EntityManagerInterface $em,Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$tweet->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        // Yalnız öz tweet-ini silə bilər
        if ($tweet->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Bu tweet sənin deyil!');
        }

        $em->remove($tweet);
        $em->flush();

        $this->addFlash('success', 'Tweet silindi!');

        return $this->redirectToRoute('tweet_list');
    }

    #[Route('/tweet/{id}/like', name: 'tweet_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(
        Tweet $tweet,
        EntityManagerInterface $em,
        Request $request,
        NotificationService $notificationService
    ): Response
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('tweet_like', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $user = $this->getUser();

        if ($tweet->isLikedBy($user)) {
            $tweet->removeLike($user);
            $notificationService->removeLikeNotification($user, $tweet);
        } else {
            $tweet->addLike($user);
            $notificationService->createLikeNotification($user, $tweet);
        }

        $em->flush();

        return $this->json([
            'liked' => $tweet->isLikedBy($user),
            'count' => $tweet->getLikesCount()
        ]);
    }
    #[Route('/tweet/{id}', name: 'tweet_show', methods: ['GET'])]
    public function show(Tweet $tweet, TweetRepository $tweetRepository): Response
    {
        // Tweet və bütün cavabları
        $replies = $tweetRepository->findBy(
            ['parent' => $tweet],
            ['createdAt' => 'ASC']
        );

        return $this->render('tweet/show.html.twig', [
            'tweet' => $tweet,
            'replies' => $replies,
        ]);
    }

    #[Route('/tweet/{id}/reply', name: 'tweet_reply', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(Tweet $parentTweet, Request $request, EntityManagerInterface $em): Response
    {
        $reply = new Tweet();
        $reply->setParent($parentTweet);

        $form = $this->createForm(TweetType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reply->setUser($this->getUser());

            $em->persist($reply);
            $em->flush();

            $this->addFlash('success', 'Cavab əlavə olundu!');

            return $this->redirectToRoute('tweet_show', ['id' => $parentTweet->getId()]);
        }

        return $this->render('tweet/reply.html.twig', [
            'form' => $form,
            'parent_tweet' => $parentTweet,
        ]);
    }

    #[Route('/tweet/{id}/retweet', name: 'tweet_retweet', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function retweet(
        Tweet $originalTweet,
        EntityManagerInterface $em,
        Request $request
    ): Response
    {
        // CSRF yoxla
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('tweet_retweet', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $user = $this->getUser();

        // Öz tweet-ini retweet edə bilməz
        if ($originalTweet->getUser() === $user) {
            return $this->json(['error' => 'Öz tweet-inizi retweet edə bilməzsiniz'], 400);
        }

        // Retweet-i retweet edə bilməz (yalnız original)
        if ($originalTweet->isRetweet()) {
            return $this->json(['error' => 'Retweet-i retweet edə bilməzsiniz'], 400);
        }

        // Check: artıq retweet edibmi?
        $existingRetweet = $em->getRepository(Tweet::class)->findOneBy([
            'user' => $user,
            'originalTweet' => $originalTweet
        ]);

        if ($existingRetweet) {
            // Undo retweet
            $em->remove($existingRetweet);
            $em->flush();

            return $this->json([
                'retweeted' => false,
                'count' => $originalTweet->getRetweetsCount()
            ]);
        }

        // Yeni retweet yarat
        $retweet = new Tweet();
        $retweet->setUser($user);
        $retweet->setOriginalTweet($originalTweet);
        $retweet->setContent(''); // Retweet-də content boşdur

        $em->persist($retweet);
        $em->flush();

        return $this->json([
            'retweeted' => true,
            'count' => $originalTweet->getRetweetsCount()
        ]);
    }
}