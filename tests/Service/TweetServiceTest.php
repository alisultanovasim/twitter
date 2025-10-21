<?php

namespace App\Tests\Service;

use App\Entity\Tweet;
use App\Entity\User;
use App\Event\TweetLikedEvent;
use App\Event\TweetUnlikedEvent;
use App\Service\TweetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TweetServiceTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;
    private ?TweetService $tweetService = null;
    private ?EventDispatcherInterface $eventDispatcher = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->eventDispatcher = $kernel->getContainer()
            ->get(EventDispatcherInterface::class);

        $this->tweetService = $kernel->getContainer()
            ->get(TweetService::class);

        // Disable foreign key checks
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        // Drop tables in any order
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS notification');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS tweet_likes');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS tweet');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS user_following');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS user_followers');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS user');

        // Re-enable foreign key checks
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        // Create schema
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
        $this->em = null;
    }

    private function createUser(string $username = 'testuser'): User
    {
        $user = new User();
        $user->setEmail($username . '@test.com');
        $user->setUsername($username);
        $user->setPassword('hashed_password');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createTweet(User $user, string $content = 'Test tweet'): Tweet
    {
        return $this->tweetService->createTweet($user, $content);
    }

    public function testCreateTweet(): void
    {
        $user = $this->createUser('john');

        $tweet = $this->tweetService->createTweet($user, 'Hello World');

        $this->assertNotNull($tweet->getId());
        $this->assertEquals('Hello World', $tweet->getContent());
        $this->assertEquals($user, $tweet->getUser());
    }

    public function testCreateTweetWithImage(): void
    {
        $user = $this->createUser('jane');

        $tweet = $this->tweetService->createTweet(
            $user,
            'Tweet with image',
            'image123.jpg'
        );

        $this->assertEquals('image123.jpg', $tweet->getImagePath());
    }

    public function testCreateReply(): void
    {
        $user = $this->createUser('alice');
        $parentTweet = $this->createTweet($user, 'Parent tweet');

        $reply = $this->tweetService->createTweet(
            $user,
            'Reply content',
            null,
            $parentTweet
        );

        $this->assertEquals($parentTweet, $reply->getParent());
        $this->assertTrue($reply->isReply());
    }

    public function testDeleteTweet(): void
    {
        $user = $this->createUser('bob');
        $tweet = $this->createTweet($user, 'To be deleted');

        $tweetId = $tweet->getId();

        $this->tweetService->deleteTweet($tweet, $user);

        $deletedTweet = $this->em->getRepository(Tweet::class)->find($tweetId);
        $this->assertNull($deletedTweet);
    }

    public function testDeleteTweetThrowsExceptionForNonOwner(): void
    {
        $owner = $this->createUser('owner');
        $attacker = $this->createUser('attacker');
        $tweet = $this->createTweet($owner, 'Owner tweet');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bu tweet sizin deyil!');

        $this->tweetService->deleteTweet($tweet, $attacker);
    }

    public function testToggleLike(): void
    {
        $author = $this->createUser('author');
        $liker = $this->createUser('liker');
        $tweet = $this->createTweet($author, 'Likeable tweet');

        // First like
        $liked = $this->tweetService->toggleLike($liker, $tweet);

        $this->assertTrue($liked);
        $this->assertTrue($tweet->isLikedBy($liker));
        $this->assertEquals(1, $tweet->getLikesCount());

        // Unlike
        $liked = $this->tweetService->toggleLike($liker, $tweet);

        $this->assertFalse($liked);
        $this->assertFalse($tweet->isLikedBy($liker));
        $this->assertEquals(0, $tweet->getLikesCount());
    }

    public function testToggleLikeDispatchesEvent(): void
    {
        $author = $this->createUser('author');
        $liker = $this->createUser('liker');
        $tweet = $this->createTweet($author, 'Tweet');

        $eventDispatched = false;
        $this->eventDispatcher->addListener(TweetLikedEvent::class, function() use (&$eventDispatched) {
            $eventDispatched = true;
        });

        $this->tweetService->toggleLike($liker, $tweet);

        $this->assertTrue($eventDispatched, 'TweetLikedEvent should be dispatched');
    }

    public function testGetTimeline(): void
    {
        $user = $this->createUser('user');
        $followed = $this->createUser('followed');

        $user->follow($followed);
        $this->em->flush();

        $this->createTweet($user, 'Own tweet');
        $this->createTweet($followed, 'Followed tweet');

        $timeline = $this->tweetService->getTimeline($user, 50);

        $this->assertCount(2, $timeline);
    }

    public function testGetPublicTimeline(): void
    {
        $user1 = $this->createUser('user1');
        $user2 = $this->createUser('user2');

        $this->createTweet($user1, 'Tweet 1');
        $this->createTweet($user2, 'Tweet 2');

        $timeline = $this->tweetService->getPublicTimeline(50);

        $this->assertCount(2, $timeline);
    }

    public function testGetReplies(): void
    {
        $user = $this->createUser('user');
        $parentTweet = $this->createTweet($user, 'Parent');

        $this->tweetService->createTweet($user, 'Reply 1', null, $parentTweet);
        $this->tweetService->createTweet($user, 'Reply 2', null, $parentTweet);

        $replies = $this->tweetService->getReplies($parentTweet);

        $this->assertCount(2, $replies);
        $this->assertEquals('Reply 1', $replies[0]->getContent());
    }

    public function testToggleRetweet(): void
    {
        $author = $this->createUser('author');
        $retweeter = $this->createUser('retweeter');
        $tweet = $this->createTweet($author, 'Original tweet');

        // Retweet
        $result = $this->tweetService->toggleRetweet($retweeter, $tweet);

        $this->assertTrue($result['retweeted']);
        $this->assertEquals(1, $result['count']);

        // Undo retweet
        $result = $this->tweetService->toggleRetweet($retweeter, $tweet);

        $this->assertFalse($result['retweeted']);
        $this->assertEquals(0, $result['count']);
    }

    public function testToggleRetweetThrowsExceptionForOwnTweet(): void
    {
        $user = $this->createUser('user');
        $tweet = $this->createTweet($user, 'Own tweet');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Öz tweet-inizi retweet edə bilməzsiniz');

        $this->tweetService->toggleRetweet($user, $tweet);
    }
}