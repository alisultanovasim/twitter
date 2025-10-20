<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserUnfollowedEvent extends Event
{
    public function __construct(
        private User $unfollower,
        private User $unfollowed
    ) {}

    public function getUnfollower(): User
    {
        return $this->unfollower;
    }

    public function getUnfollowed(): User
    {
        return $this->unfollowed;
    }
}