<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Tweet;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $notificationRepository
    ) {}

    /**
     * Create follow notification
     */
    public function createFollowNotification(User $follower, User $followed): void
    {
        if ($follower === $followed) {
            return;
        }

        $notification = new Notification();
        $notification->setRecipient($followed);
        $notification->setSender($follower);
        $notification->setType('follow');

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Create like notification
     */
    public function createLikeNotification(User $liker, Tweet $tweet): void
    {
        if ($liker === $tweet->getUser()) {
            return;
        }

        $existing = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.recipient = :recipient')
            ->andWhere('n.sender = :sender')
            ->andWhere('n.type = :type')
            ->andWhere('n.tweet = :tweet')
            ->setParameter('recipient', $tweet->getUser())
            ->setParameter('sender', $liker)
            ->setParameter('type', 'like')
            ->setParameter('tweet', $tweet)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            return;
        }

        $notification = new Notification();
        $notification->setRecipient($tweet->getUser());
        $notification->setSender($liker);
        $notification->setType('like');
        $notification->setTweet($tweet);

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Remove like notification
     */
    public function removeLikeNotification(User $unliker, Tweet $tweet): void
    {
        $notification = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.recipient = :recipient')
            ->andWhere('n.sender = :sender')
            ->andWhere('n.type = :type')
            ->andWhere('n.tweet = :tweet')
            ->setParameter('recipient', $tweet->getUser())
            ->setParameter('sender', $unliker)
            ->setParameter('type', 'like')
            ->setParameter('tweet', $tweet)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($notification) {
            $this->em->remove($notification);
            $this->em->flush();
        }
    }

    /**
     * Create mention notification - YENI
     */
    public function createMentionNotification(User $mentioner, User $mentioned, Tweet $tweet): void
    {
        if ($mentioner === $mentioned) {
            return;
        }

            $existing = $this->notificationRepository->createQueryBuilder('n')
                ->where('n.recipient = :recipient')
                ->andWhere('n.sender = :sender')
                ->andWhere('n.type = :type')
                ->andWhere('n.tweet = :tweet')
                ->setParameter('recipient', $mentioned)
                ->setParameter('sender', $mentioner)
                ->setParameter('type', 'mention')
                ->setParameter('tweet', $tweet)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();


        if ($existing) {
            return;
        }

        $notification = new Notification();
        $notification->setRecipient($mentioned);
        $notification->setSender($mentioner);
        $notification->setType('mention');
        $notification->setTweet($tweet);

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): void
    {
        $this->em->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', ':isRead')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = :currentStatus')
            ->setParameter('isRead', true)
            ->setParameter('currentStatus', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->count([
            'recipient' => $user,
            'isRead' => false
        ]);
    }
}