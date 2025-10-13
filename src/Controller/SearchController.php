<?php

namespace App\Controller;

use App\Repository\TweetRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(
        Request $request,
        TweetRepository $tweetRepository,
        UserRepository $userRepository
    ): Response
    {
        $query = $request->query->get('q', '');

        $tweets = [];
        $users = [];

        if (strlen($query) >= 2) {
            // Tweet axtarışı
            $tweets = $tweetRepository->createQueryBuilder('t')
                ->where('t.content LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->orderBy('t.createdAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            // User axtarışı
            $users = $userRepository->createQueryBuilder('u')
                ->where('u.username LIKE :query OR u.email LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }

        return $this->render('search/results.html.twig', [
            'query' => $query,
            'tweets' => $tweets,
            'users' => $users,
        ]);
    }
}