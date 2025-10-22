<?php

namespace App\Controller;

use App\Service\HashtagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HashtagController extends AbstractController
{
    public function __construct(
        private HashtagService $hashtagService
    ) {}

    /**
     * Trending hashtags page
     */
    #[Route('/hashtags/trending', name: 'hashtag_trending', methods: ['GET'])]
    public function trending(): Response
    {
        $trending = $this->hashtagService->getTrending(20);

        return $this->render('hashtag/trending.html.twig', [
            'hashtags' => $trending,
        ]);
    }

    /**
     * Show tweets by hashtag
     */
    #[Route('/hashtag/{tag}', name: 'hashtag_show', methods: ['GET'])]
    public function show(string $tag): Response
    {
        $tweets = $this->hashtagService->getTweetsByHashtag($tag, 50);

        if (empty($tweets)) {
            throw $this->createNotFoundException("#{$tag} tapılmadı");
        }

        return $this->render('hashtag/show.html.twig', [
            'tag' => $tag,
            'tweets' => $tweets,
        ]);
    }

    /**
     * Hashtag autocomplete API (AJAX)
     */
    #[Route('/api/hashtags/search', name: 'hashtag_search_api', methods: ['GET'])]
    public function searchApi(Request $request): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $hashtags = $this->hashtagService->search($query, 10);

        // Return JSON for autocomplete
        return $this->json(array_map(fn($h) => [
            'tag' => $h->getTag(),
            'count' => $h->getUsageCount(),
        ], $hashtags));
    }
}