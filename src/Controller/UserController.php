<?php

namespace App\Controller;

use App\Repository\TweetRepository;
use App\Repository\UserRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    public function __construct(
        private UserService $userService
    ) {}

    #[Route('/@{username}', name: 'user_profile')]
    public function profile(
        string $username,
        TweetRepository $tweetRepository,
        UserRepository $userRepository
    ): Response
    {
        $user = $userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            throw $this->createNotFoundException('User tapılmadı');
        }

        $tweets = $tweetRepository->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

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
        Request $request,
        #[Autowire(service: 'user_follow.limiter')] RateLimiterFactory $followLimiter
    ): Response
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('user_follow', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $limiter = $followLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Çox follow/unfollow edirsiniz. Gözləyin.'], 429);
        }

        $userToFollow = $userRepository->findOneBy(['username' => $username]);

        if (!$userToFollow) {
            throw $this->createNotFoundException('User tapılmadı');
        }

        try {
            $result = $this->userService->toggleFollow($this->getUser(), $userToFollow);
            return $this->json($result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}