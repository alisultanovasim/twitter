<?php

namespace App\EventSubscriber;

use App\Event\TweetLikedEvent;
use App\Event\TweetUnlikedEvent;
use App\Event\UserFollowedEvent;
use App\Event\UserUnfollowedEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            TweetLikedEvent::class => 'onTweetLiked',
            TweetUnlikedEvent::class => 'onTweetUnliked',
            UserFollowedEvent::class => 'onUserFollowed',
            // UserUnfollowedEvent-ə subscriber yoxdur (notification silmək lazım deyil)
        ];
    }

    public function onTweetLiked(TweetLikedEvent $event): void
    {
        $this->notificationService->createLikeNotification(
            $event->getLiker(),
            $event->getTweet()
        );
    }

    public function onTweetUnliked(TweetUnlikedEvent $event): void
    {
        $this->notificationService->removeLikeNotification(
            $event->getUnliker(),
            $event->getTweet()
        );
    }

    public function onUserFollowed(UserFollowedEvent $event): void
    {
        $this->notificationService->createFollowNotification(
            $event->getFollower(),
            $event->getFollowed()
        );
    }
}