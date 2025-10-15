<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Tweet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function createFollowNotification(User $follower, User $followed): void
    {
        // Özünə notification göndərmə
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

    public function createLikeNotification(User $liker, Tweet $tweet): void
    {
        // Öz tweet-inə like etsə notification göndərmə
        if ($liker === $tweet->getUser()) {
            return;
        }

        // Eyni notification varsa yenidən yaratma
        $existing = $this->em->getRepository(Notification::class)->findOneBy([
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

    public function removeLikeNotification(User $unliker, Tweet $tweet): void
    {
        $notification = $this->em->getRepository(Notification::class)->findOneBy([
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
}