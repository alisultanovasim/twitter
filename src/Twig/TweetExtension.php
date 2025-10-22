<?php

namespace App\Twig;

use App\Service\MentionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TweetExtension extends AbstractExtension
{
    public function __construct(
        private MentionService $mentionService
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('linkify_mentions', [$this, 'linkifyMentions'], ['is_safe' => ['html']]),
        ];
    }

    public function linkifyMentions(string $content): string
    {
        return $this->mentionService->linkifyMentions($content);
    }
}