<?php

namespace App\Event;

use App\Entity\Tweet;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class TweetUnlikedEvent extends Event
{
    public function __construct(
        private User $unliker,
        private Tweet $tweet
    ) {}

    public function getUnliker(): User
    {
        return $this->unliker;
    }

    public function getTweet(): Tweet
    {
        return $this->tweet;
    }
}