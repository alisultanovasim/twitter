<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserFollowedEvent extends Event
{
    public function __construct(
        private User $follower,
        private User $followed
    ) {}

    public function getFollower(): User
    {
        return $this->follower;
    }

    public function getFollowed(): User
    {
        return $this->followed;
    }
}