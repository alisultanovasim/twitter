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

        // Check duplicate
        $existing = $this->notificationRepository->findOneBy([
            'recipient' => $tweet->getUser(),
            'sender' => $liker,
            'type' => 'like',
            'tweet' => $tweet
        ]);

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
        $notification = $this->notificationRepository->findOneBy([
            'recipient' => $tweet->getUser(),
            'sender' => $unliker,
            'type' => 'like',
            'tweet' => $tweet
        ]);

        if ($notification) {
            $this->em->remove($notification);
            $this->em->flush();
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): void
    {
        $notifications = $this->notificationRepository->findBy([
            'recipient' => $user,
            'isRead' => false
        ]);

        foreach ($notifications as $notification) {
            $notification->setRead(true);
        }

        $this->em->flush();
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