<?php

namespace App\Entity;

use App\Repository\HashtagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: HashtagRepository::class)]
#[ORM\Index(name: 'idx_usage_count', columns: ['usage_count'])]
#[ORM\Index(name: 'idx_tag', columns: ['tag'])]
class Hashtag
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $tag;

    #[ORM\Column]
    private int $usageCount = 0;

    /**
     * @var Collection<int, Tweet>
     */
    #[ORM\ManyToMany(targetEntity: Tweet::class, mappedBy: 'hashtags')]
    private Collection $tweets;

    public function __construct()
    {
        $this->tweets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag): static
    {
        // Normalize: lowercase, no #
        $this->tag = strtolower(trim($tag, '#'));

        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function incrementUsageCount(): static
    {
        ++$this->usageCount;

        return $this;
    }

    public function decrementUsageCount(): static
    {
        if ($this->usageCount > 0) {
            --$this->usageCount;
        }

        return $this;
    }

    /**
     * @return Collection<int, Tweet>
     */
    public function getTweets(): Collection
    {
        return $this->tweets;
    }

    public function addTweet(Tweet $tweet): static
    {
        if (!$this->tweets->contains($tweet)) {
            $this->tweets->add($tweet);
            $tweet->addHashtag($this);
        }

        return $this;
    }

    public function removeTweet(Tweet $tweet): static
    {
        if ($this->tweets->removeElement($tweet)) {
            $tweet->removeHashtag($this);
        }

        return $this;
    }
}
