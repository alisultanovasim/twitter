<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TweetRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    #[Route('/@{username}', name: 'user_profile')]
    public function profile(string $username, TweetRepository $tweetRepository,UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            throw $this->createNotFoundException('User tapılmadı');
        }
        // User-in tweet-lərini gətir
        $tweets = $tweetRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'tweets' => $tweets,
            'tweet_count' => count($tweets),
        ]);
    }

    #[Route('/@{username}/follow', name: 'user_follow', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function follow(
        string $username,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        Request $request,
        NotificationService $notificationService
    ): Response
    {
        // CSRF token yoxla
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('user_follow', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $userToFollow = $userRepository->findOneBy(['username' => $username]);

        if (!$userToFollow) {
            throw $this->createNotFoundException('User tapılmadı');
        }

        $currentUser = $this->getUser();

        // Özünü follow edə bilməz
        if ($userToFollow === $currentUser) {
            return $this->json(['error' => 'Özünüzü follow edə bilməzsiniz'], 400);
        }

        if ($currentUser->isFollowing($userToFollow)) {
            $currentUser->unfollow($userToFollow);
            $following = false;
        } else {
            $currentUser->follow($userToFollow);
            $notificationService->createFollowNotification($currentUser, $userToFollow);
            $following = true;
        }

        $em->flush();

        return $this->json([
            'following' => $following,
            'followers_count' => $userToFollow->getFollowersCount(),
            'following_count' => $currentUser->getFollowingCount()
        ]);
    }
}