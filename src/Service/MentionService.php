<?php

namespace App\Service;

use App\Entity\Tweet;
use App\Entity\User;
use App\Repository\UserRepository;

class MentionService
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationService $notificationService
    ) {}

    /**
     * Extract mentions from content
     * Returns array of usernames (e.g., ["john", "mary"])
     */
    public function extractMentions(string $content): array
    {
        // Regex: @ ilə başlayan, sonra hərflər/rəqəmlər/underscore
        preg_match_all('/@(\w+)/', $content, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Process mentions for a tweet (send notifications)
     */
    public function processTweetMentions(Tweet $tweet): void
    {
        $usernames = $this->extractMentions($tweet->getContent());

        foreach ($usernames as $username) {
            $user = $this->userRepository->findOneBy(['username' => $username]);

            if ($user && $user !== $tweet->getUser()) {

                $this->notificationService->createMentionNotification(
                    $tweet->getUser(),
                    $user,
                    $tweet
                );
            }
        }
    }

    /**
     * Convert mentions to clickable links in content
     */
    public function linkifyMentions(string $content): string
    {
        return preg_replace_callback(
            '/@(\w+)/',
            fn($matches) => sprintf(
                '<a href="/@%s">@%s</a>',
                $matches[1],
                $matches[1]
            ),
            $content
        );
    }
}