<?php

namespace App\Entity;

use App\Repository\TweetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: TweetRepository::class)]
class Tweet
{
    use TimestampableEntity;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Tweet boş ola bilməz')]
    #[Assert\Length(
        max: 280,
        maxMessage: 'Tweet maksimum {{ limit }} simvol ola bilər'
    )]
    private ?string $content = null;

    #[ORM\ManyToOne(inversedBy: 'tweets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'tweet_likes')]
    private Collection $likedBy;

    #[ORM\ManyToOne(targetEntity: Tweet::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Tweet $parent = null;

    /**
     * @var Collection<int, Tweet>
     */
    #[ORM\OneToMany(targetEntity: Tweet::class, mappedBy: 'parent')]
    private Collection $replies;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'tweet')]
    private Collection $notifications;

    /**
     * Original tweet reference (əgər bu retweet-dirsə)
     */
    #[ORM\ManyToOne(targetEntity: Tweet::class, inversedBy: 'retweets')]
    #[ORM\JoinColumn(name: 'original_tweet_id', nullable: true, onDelete: 'CASCADE')]
    private ?Tweet $originalTweet = null;

    /**
     * Bu tweet-in retweet-ləri
     * @var Collection<int, Tweet>
     */
    #[ORM\OneToMany(targetEntity: Tweet::class, mappedBy: 'originalTweet')]
    private Collection $retweets;

    public function __construct()
    {
        $this->likedBy = new ArrayCollection();
        $this->replies = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->retweets = new ArrayCollection();

    }

    public function getParent(): ?Tweet
    {
        return $this->parent;
    }

    public function setParent(?Tweet $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(Tweet $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }
        return $this;
    }

    public function removeReply(Tweet $reply): static
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParent() === $this) {
                $reply->setParent(null);
            }
        }
        return $this;
    }

    public function getRepliesCount(): int
    {
        return $this->replies->count();
    }

    public function isReply(): bool
    {
        return $this->parent !== null;
    }

    public function getLikedBy(): Collection
    {
        return $this->likedBy;
    }

    public function addLike(User $user): static
    {
        if (!$this->likedBy->contains($user)) {
            $this->likedBy->add($user);
        }
        return $this;
    }

    public function removeLike(User $user): static
    {
        $this->likedBy->removeElement($user);
        return $this;
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likedBy->contains($user);
    }

    public function getLikesCount(): int
    {
        return $this->likedBy->count();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setTweet($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getTweet() === $this) {
                $notification->setTweet(null);
            }
        }

        return $this;
    }
    public function getOriginalTweet(): ?Tweet
    {
        return $this->originalTweet;
    }

    public function setOriginalTweet(?Tweet $originalTweet): static
    {
        $this->originalTweet = $originalTweet;
        return $this;
    }

    public function getRetweets(): Collection
    {
        return $this->retweets;
    }

    public function getRetweetsCount(): int
    {
        return $this->retweets->count();
    }

    public function isRetweet(): bool
    {
        return $this->originalTweet !== null;
    }

    /**
     * Check əgər user artıq retweet edibsə
     */
    public function isRetweetedBy(User $user): bool
    {
        foreach ($this->retweets as $retweet) {
            if ($retweet->getUser() === $user) {
                return true;
            }
        }
        return false;
    }
}
