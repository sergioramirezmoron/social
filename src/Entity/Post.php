<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $text = null;

    #[ORM\Column]
    private ?\DateTime $postdate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $img = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'likes')]
    private Collection $likes;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $deleted_at = null;

    /**
     * Post original que fue retuiteado (si este post es un retweet)
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'retweets')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $originalPost = null;

    /**
     * Posts que son retweets de este post
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'originalPost')]
    private Collection $retweets;

    public function __construct()
    {
        $this->likes = new ArrayCollection();
        $this->retweets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getPostdate(): ?\DateTime
    {
        return $this->postdate;
    }

    public function setPostdate(\DateTime $postdate): static
    {
        $this->postdate = $postdate;

        return $this;
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): static
    {
        $this->img = $img;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(User $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
        }

        return $this;
    }

    public function removeLike(User $like): static
    {
        $this->likes->removeElement($like);

        return $this;
    }

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTime $deleted_at): static
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    public function getOriginalPost(): ?Post
    {
        return $this->originalPost;
    }

    public function setOriginalPost(?Post $originalPost): static
    {
        $this->originalPost = $originalPost;

        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getRetweets(): Collection
    {
        return $this->retweets;
    }

    public function addRetweet(Post $retweet): static
    {
        if (!$this->retweets->contains($retweet)) {
            $this->retweets->add($retweet);
            $retweet->setOriginalPost($this);
        }

        return $this;
    }

    public function removeRetweet(Post $retweet): static
    {
        if ($this->retweets->removeElement($retweet)) {
            if ($retweet->getOriginalPost() === $this) {
                $retweet->setOriginalPost(null);
            }
        }

        return $this;
    }

    /**
     * Verifica si este post es un retweet
     */
    public function isRetweet(): bool
    {
        return $this->originalPost !== null;
    }
}
