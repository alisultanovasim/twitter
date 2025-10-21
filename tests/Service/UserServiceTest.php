<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Event\UserFollowedEvent;
use App\Event\UserUnfollowedEvent;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UserServiceTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;
    private ?UserService $userService = null;
    private ?EventDispatcherInterface $eventDispatcher = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->eventDispatcher = $kernel->getContainer()
            ->get(EventDispatcherInterface::class);

        $this->userService = $kernel->getContainer()
            ->get(UserService::class);

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

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setEmail($username . '@test.com');
        $user->setUsername($username);
        $user->setPassword('hashed_password');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testToggleFollow(): void
    {
        $follower = $this->createUser('alice');
        $followed = $this->createUser('bob');

        // Follow
        $result = $this->userService->toggleFollow($follower, $followed);

        $this->assertTrue($result['following']);
        $this->assertEquals(1, $result['followers_count']);
        $this->assertEquals(1, $result['following_count']);
        $this->assertTrue($follower->isFollowing($followed));

        // Unfollow
        $result = $this->userService->toggleFollow($follower, $followed);

        $this->assertFalse($result['following']);
        $this->assertEquals(0, $result['followers_count']);
        $this->assertEquals(0, $result['following_count']);
        $this->assertFalse($follower->isFollowing($followed));
    }

    public function testToggleFollowThrowsExceptionForSelf(): void
    {
        $user = $this->createUser('user');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Özünüzü follow edə bilməzsiniz');

        $this->userService->toggleFollow($user, $user);
    }

    public function testToggleFollowDispatchesEvent(): void
    {
        $follower = $this->createUser('follower');
        $followed = $this->createUser('followed');

        $eventDispatched = false;
        $this->eventDispatcher->addListener(UserFollowedEvent::class, function() use (&$eventDispatched) {
            $eventDispatched = true;
        });

        $this->userService->toggleFollow($follower, $followed);

        $this->assertTrue($eventDispatched, 'UserFollowedEvent should be dispatched');
    }

    public function testToggleUnfollowDispatchesEvent(): void
    {
        $follower = $this->createUser('follower');
        $followed = $this->createUser('followed');

        // First follow
        $this->userService->toggleFollow($follower, $followed);

        $eventDispatched = false;
        $this->eventDispatcher->addListener(UserUnfollowedEvent::class, function() use (&$eventDispatched) {
            $eventDispatched = true;
        });

        // Then unfollow
        $this->userService->toggleFollow($follower, $followed);

        $this->assertTrue($eventDispatched, 'UserUnfollowedEvent should be dispatched');
    }

    public function testGetUserTweets(): void
    {
        $user = $this->createUser('user');

        // User-ə əl ilə tweet əlavə et (TweetService olmadan test)
        $tweet1 = new \App\Entity\Tweet();
        $tweet1->setUser($user);
        $tweet1->setContent('Tweet 1');
        $this->em->persist($tweet1);

        $tweet2 = new \App\Entity\Tweet();
        $tweet2->setUser($user);
        $tweet2->setContent('Tweet 2');
        $this->em->persist($tweet2);

        $this->em->flush();
        $this->em->clear(); // ← Clear to force fresh fetch

        // Refresh user from database
        $user = $this->em->getRepository(\App\Entity\User::class)->find($user->getId());

        $tweets = $this->userService->getUserTweets($user);

        $this->assertCount(2, $tweets);
    }
}