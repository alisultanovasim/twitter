<?php

namespace App\Event;

use App\Entity\Tweet;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class TweetLikedEvent extends Event
{
    public function __construct(
        private User $liker,
        private Tweet $tweet
    ) {}

    public function getLiker(): User
    {
        return $this->liker;
    }

    public function getTweet(): Tweet
    {
        return $this->tweet;
    }
}