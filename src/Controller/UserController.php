<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TweetRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
}