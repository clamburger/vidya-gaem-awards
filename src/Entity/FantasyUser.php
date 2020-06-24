<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use RandomLib\Factory;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="fantasy_users")
 * @ORM\Entity
 */
class FantasyUser
{
    const NAME_LIMIT = 50;
    const VICTORY_MESSAGE_LIMIT = 140;
    const MAX_AVATAR_SIZE = 1024 * 1024 * 2; // 2 megabytes

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=100)
     */
    private $name = 'Anonymous';

    /**
     * @var string|null
     *
     * @ORM\Column(name="avatar", type="string", nullable=true)
     */
    private $avatarLegacy;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File")
     */
    private $avatar;

    /**
     * @var string|null
     *
     * @ORM\Column(name="image_token", type="string", nullable=true)
     */
    private $imageToken;

    /**
     * @var string|null
     *
     * @ORM\Column(name="victory_message", type="text", nullable=true)
     */
    private $victoryMessage;

    /**
     * @var int|null
     *
     * @ORM\Column(name="score", type="integer", nullable=true)
     */
    private $score;

    /**
     * @var int|null
     *
     * @ORM\Column(name="rank", type="integer", nullable=true)
     */
    private $rank;

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="last_updated", type="datetime_immutable")
     */
    private $lastUpdated;

    /**
     * @var User
     *
     * @ORM\OneToOne(targetEntity="App\Entity\User", inversedBy="fantasyUser")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id", unique=true)
     * })
     */
    private $user;

    /**
     * @var Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\FantasyPrediction", mappedBy="fantasyUser")
     */
    private $predictions;

    public function __construct()
    {
        $this->predictions = new ArrayCollection();
        $this->lastUpdated = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAvatar(): ?File
    {
        return $this->avatar;
    }

    public function setAvatar(?File $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getVictoryMessage(): ?string
    {
        return $this->victoryMessage;
    }

    public function setVictoryMessage(?string $victoryMessage): self
    {
        $this->victoryMessage = $victoryMessage;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection|FantasyPrediction[]
     */
    public function getPredictions(): Collection
    {
        return $this->predictions;
    }

    public function addPrediction(FantasyPrediction $prediction): self
    {
        if (!$this->predictions->contains($prediction)) {
            $this->predictions[] = $prediction;
            $prediction->setFantasyUser($this);
        }

        return $this;
    }

    public function removePrediction(FantasyPrediction $prediction): self
    {
        if ($this->predictions->contains($prediction)) {
            $this->predictions->removeElement($prediction);
            // set the owning side to null (unless already changed)
            if ($prediction->getFantasyUser() === $this) {
                $prediction->setFantasyUser(null);
            }
        }

        return $this;
    }

    public function getPredictionForAward(Award $award): ?FantasyPrediction
    {
        return $this->predictions->filter(function (FantasyPrediction $prediction) use ($award) {
            return $prediction->getAward() === $award;
        })->first() ?: null;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getLastUpdated(): ?DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(DateTimeImmutable $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    public function getImageToken(): string
    {
        return $this->imageToken;
    }

    public function regenerateImageToken(): string
    {
        $factory = new Factory;
        $generator = $factory->getLowStrengthGenerator();
        return $this->imageToken = hash('sha1', $generator->generate(64));
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): self
    {
        $this->rank = $rank;
        return $this;
    }
}
