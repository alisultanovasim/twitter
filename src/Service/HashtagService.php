<?php

namespace App\Service;

use App\Entity\Hashtag;
use App\Entity\Tweet;
use App\Repository\HashtagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HashtagService
{
    public function __construct(
        private EntityManagerInterface $em,
        private HashtagRepository $hashtagRepository,
        private CacheInterface $cache
    ) {}

    /**
     * Get trending hashtags (cached 5 minutes)
     */
    public function getTrending(int $limit = 10): array
    {
        return $this->cache->get('trending_hashtags', function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(300); // 5 dəqiqə cache

            return $this->hashtagRepository->findTrending($limit);
        });
    }

    /**
     * Extract hashtags from tweet content
     * Returns array of hashtag strings (e.g., ["symfony", "php"])
     */

    public function extractHashtags(string $content): array
    {
        // Regex: # ilə başlayan, sonra hərflər/rəqəmlər/underscore
        preg_match_all('/#(\w+)/u', $content, $matches);

        // Normalize: lowercase, unique
        $tags = array_map('strtolower', $matches[1]);
        return array_unique($tags);
    }

    /**
     * Process hashtags for a tweet (create/link entities)
     */
    public function processTweetHashtags(Tweet $tweet): void
    {
        $content = $tweet->getContent();
        if (!$content) {
            return;
        }

        $tagNames = $this->extractHashtags($content);

        foreach ($tagNames as $tagName) {
            // Find or create hashtag entity
            $hashtag = $this->hashtagRepository->findOrCreateByTag($tagName);

            // Link to tweet (if not already linked)
            if (!$tweet->getHashtags()->contains($hashtag)) {
                $tweet->addHashtag($hashtag);
                $hashtag->incrementUsageCount();
            }
        }

        $this->em->flush();
        $this->cache->delete('trending_hashtags');
    }

    /**
     * Remove hashtag associations when tweet is deleted
     */
    public function removeTweetHashtags(Tweet $tweet): void
    {
        foreach ($tweet->getHashtags() as $hashtag) {
            $hashtag->decrementUsageCount();
            $tweet->removeHashtag($hashtag);

            // İstəyə bağlı: əgər usage_count 0-a düşsə, hashtag-ı sil
            if ($hashtag->getUsageCount() === 0) {
                $this->em->remove($hashtag);
            }
        }

        $this->em->flush();
        $this->cache->delete('trending_hashtags');
    }

    /**
     * Search hashtags
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->hashtagRepository->searchByTag($query, $limit);
    }

    /**
     * Get tweets by hashtag
     */
    public function getTweetsByHashtag(string $tag, int $limit = 50): array
    {
        $hashtag = $this->hashtagRepository->findOneBy(['tag' => strtolower($tag)]);

        if (!$hashtag) {
            return [];
        }

        // Doctrine Collection-dan array-a çevir və son tweet-ləri gətir
        return $hashtag->getTweets()
            ->slice(0, $limit);
    }
}