<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\Tweet;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NotificationServiceTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;
    private ?NotificationService $notificationService = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->notificationService = $kernel->getContainer()
            ->get(NotificationService::class);

        // Create schema
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS notification');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS tweet');
        $this->em->getConnection()->executeStatement('DROP TABLE IF EXISTS user');

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

    private function createTweet(User $user, string $content): Tweet
    {
        $tweet = new Tweet();
        $tweet->setUser($user);
        $tweet->setContent($content);

        $this->em->persist($tweet);
        $this->em->flush();

        return $tweet;
    }

    public function testCreateFollowNotification(): void
    {
        $follower = $this->createUser('follower');
        $followed = $this->createUser('followed');

        $this->notificationService->createFollowNotification($follower, $followed);

        $notification = $this->em->getRepository(Notification::class)->findOneBy([
            'recipient' => $followed,
            'sender' => $follower,
            'type' => 'follow'
        ]);

        $this->assertNotNull($notification);
        $this->assertEquals('follow', $notification->getType());
        $this->assertEquals($followed, $notification->getRecipient());
        $this->assertEquals($follower, $notification->getSender());
    }

    public function testCreateFollowNotificationDoesNotCreateForSelf(): void
    {
        $user = $this->createUser('user');

        $this->notificationService->createFollowNotification($user, $user);

        $count = $this->em->getRepository(Notification::class)->count([
            'recipient' => $user,
            'type' => 'follow'
        ]);

        $this->assertEquals(0, $count);
    }

    public function testCreateLikeNotification(): void
    {
        $author = $this->createUser('author');
        $liker = $this->createUser('liker');
        $tweet = $this->createTweet($author, 'Test tweet');

        $this->notificationService->createLikeNotification($liker, $tweet);

        $notification = $this->em->getRepository(Notification::class)->findOneBy([
            'recipient' => $author,
            'sender' => $liker,
            'type' => 'like',
            'tweet' => $tweet
        ]);

        $this->assertNotNull($notification);
        $this->assertEquals('like', $notification->getType());
        $this->assertEquals($tweet, $notification->getTweet());
    }

    public function testCreateLikeNotificationDoesNotCreateForOwnTweet(): void
    {
        $user = $this->createUser('user');
        $tweet = $this->createTweet($user, 'Own tweet');

        $this->notificationService->createLikeNotification($user, $tweet);

        $count = $this->em->getRepository(Notification::class)->count([
            'recipient' => $user,
            'type' => 'like'
        ]);

        $this->assertEquals(0, $count);
    }

    public function testCreateLikeNotificationPreventsDuplicate(): void
    {
        $author = $this->createUser('author');
        $liker = $this->createUser('liker');
        $tweet = $this->createTweet($author, 'Test tweet');

        // Create notification twice
        $this->notificationService->createLikeNotification($liker, $tweet);
        $this->notificationService->createLikeNotification($liker, $tweet);

        $count = $this->em->getRepository(Notification::class)->count([
            'recipient' => $author,
            'sender' => $liker,
            'type' => 'like',
            'tweet' => $tweet
        ]);

        $this->assertEquals(1, $count, 'Should not create duplicate notifications');
    }

    public function testRemoveLikeNotification(): void
    {
        $author = $this->createUser('author');
        $liker = $this->createUser('liker');
        $tweet = $this->createTweet($author, 'Test tweet');

        // Create notification
        $this->notificationService->createLikeNotification($liker, $tweet);

        $this->assertEquals(1, $this->em->getRepository(Notification::class)->count(['type' => 'like']));

        // Remove notification
        $this->notificationService->removeLikeNotification($liker, $tweet);

        $this->assertEquals(0, $this->em->getRepository(Notification::class)->count(['type' => 'like']));
    }

    public function testMarkAllAsRead(): void
    {
        $recipient = $this->createUser('recipient');
        $sender = $this->createUser('sender');

        // Create 2 unread notifications
        $this->notificationService->createFollowNotification($sender, $recipient);

        $tweet = $this->createTweet($recipient, 'Tweet');
        $this->notificationService->createLikeNotification($sender, $tweet);

        // Mark all as read
        $this->notificationService->markAllAsRead($recipient);

        $unreadCount = $this->em->getRepository(Notification::class)->count([
            'recipient' => $recipient,
            'isRead' => false
        ]);

        $this->assertEquals(0, $unreadCount);
    }

    public function testGetUnreadCount(): void
    {
        $recipient = $this->createUser('recipient');
        $sender = $this->createUser('sender');

        // Create 2 notifications
        $this->notificationService->createFollowNotification($sender, $recipient);

        $tweet = $this->createTweet($recipient, 'Tweet');
        $this->notificationService->createLikeNotification($sender, $tweet);

        $count = $this->notificationService->getUnreadCount($recipient);

        $this->assertEquals(2, $count);
    }
}