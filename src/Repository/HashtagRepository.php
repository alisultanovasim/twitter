<?php

namespace App\Repository;

use App\Entity\Hashtag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hashtag>
 */
class HashtagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hashtag::class);
    }

    /**
     * Trending hashtags (ən çox istifadə olunan).
     */
    public function findTrending(int $limit = 10): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search hashtags by tag name.
     */
    public function searchByTag(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.tag LIKE :query')
            ->setParameter('query', strtolower($query).'%')
            ->orderBy('h.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find or create hashtag by tag name.
     */
    public function findOrCreateByTag(string $tag): Hashtag
    {
        $normalized = strtolower(trim($tag, '#'));

        $hashtag = $this->findOneBy(['tag' => $normalized]);

        if (!$hashtag) {
            $hashtag = new Hashtag();
            $hashtag->setTag($normalized);
            $this->getEntityManager()->persist($hashtag);
        }

        return $hashtag;
    }
}
