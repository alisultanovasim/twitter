<?php

namespace App\Tests\Service;

use App\Entity\Tweet;
use App\Entity\User;
use App\Entity\Hashtag;
use App\Service\HashtagService;
use App\Repository\HashtagRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class HashtagServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private HashtagRepository $hashtagRepository;
    private HashtagService $service;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->service = new HashtagService($this->em, $this->hashtagRepository, $this->cache);
    }

    public function testExtractHashtags(): void
    {
        $content = 'Learning #Symfony and #PHP is awesome! #symfony';
        $result = $this->service->extractHashtags($content);

        $this->assertCount(2, $result); // symfony, php (duplicate removed)
        $this->assertContains('symfony', $result);
        $this->assertContains('php', $result);
    }

    public function testExtractHashtagsEmpty(): void
    {
        $content = 'No hashtags here';
        $result = $this->service->extractHashtags($content);

        $this->assertEmpty($result);
    }

    public function testExtractHashtagsWithUnicode(): void
    {
        $content = '#Azərbaycan dilində hashtag';
        $result = $this->service->extractHashtags($content);

        $this->assertContains('azərbaycan', $result);
    }

    public function testProcessTweetHashtags(): void
    {
        $user = new User();
        $tweet = new Tweet();
        $tweet->setUser($user);
        $tweet->setContent('Testing #symfony #php');

        $hashtag1 = new Hashtag();
        $hashtag1->setTag('symfony');

        $hashtag2 = new Hashtag();
        $hashtag2->setTag('php');

        $this->hashtagRepository
            ->expects($this->exactly(2))
            ->method('findOrCreateByTag')
            ->willReturnOnConsecutiveCalls($hashtag1, $hashtag2);

        $this->em->expects($this->once())->method('flush');

        $this->service->processTweetHashtags($tweet);

        $this->assertCount(2, $tweet->getHashtags());
    }

    public function testRemoveTweetHashtags(): void
    {
        $user = new User();
        $tweet = new Tweet();
        $tweet->setUser($user);

        $hashtag = new Hashtag();
        $hashtag->setTag('test');
        $hashtag->incrementUsageCount();

        $tweet->addHashtag($hashtag);

        $this->em->expects($this->once())->method('flush');

        $this->service->removeTweetHashtags($tweet);

        $this->assertCount(0, $tweet->getHashtags());
        $this->assertEquals(0, $hashtag->getUsageCount());
    }

    public function testGetTrending(): void
    {
        $hashtags = [new Hashtag(), new Hashtag()];

        $this->hashtagRepository
            ->expects($this->once())
            ->method('findTrending')
            ->with(10)
            ->willReturn($hashtags);

        $result = $this->service->getTrending(10);

        $this->assertCount(2, $result);
    }
}