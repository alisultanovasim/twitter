<?php

namespace App\Repository;

use App\Entity\Tweet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Tweet>
 */
class TweetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tweet::class);
    }
    public function getFollowingTimelinePaginated(User $user, int $page = 1, int $limit = 20)
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t', 'u')
            ->leftJoin('t.user', 'u')
            ->where('t.user = :user')
            ->orWhere('u IN (:following)')
            ->andWhere('t.parent IS NULL')
            ->setParameter('user', $user)
            ->setParameter('following', $user->getFollowing()->toArray())
            ->orderBy('t.createdAt', 'DESC');

        return $qb->getQuery();
    }
}
