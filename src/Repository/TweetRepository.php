<?php

namespace App\Repository;

use App\Entity\Tweet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tweet>
 */
class TweetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tweet::class);
    }

    public function getFollowingTimeline(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'u')
            ->leftJoin('t.user', 'u')
            ->where('t.user = :user')
            ->orWhere('u IN (:following)')
            ->andWhere('t.parent IS NULL')
            ->setParameter('user', $user)
            ->setParameter('following', $user->getFollowing()->toArray())
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
