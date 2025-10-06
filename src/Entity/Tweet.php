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


    public function __construct()
    {
        $this->likedBy = new ArrayCollection();
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
}
