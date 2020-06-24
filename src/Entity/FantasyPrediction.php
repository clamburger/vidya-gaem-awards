<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="fantasy_predictions")
 * @ORM\Entity
 */
class FantasyPrediction
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="last_updated", type="datetime_immutable")
     */
    private $lastUpdated;

    /**
     * @var Award
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Award", inversedBy="fantasyPredictions")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="award_id", referencedColumnName="id")
     * })
     */
    private $award;

    /**
     * @var Nominee
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Nominee", inversedBy="fantasyPredictions")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="nominee_id", referencedColumnName="id")
     * })
     */
    private $nominee;

    /**
     * @var FantasyUser
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\FantasyUser", inversedBy="predictions")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="fantasy_user_id", referencedColumnName="id")
     * })
     */
    private $fantasyUser;

    public function __construct()
    {
        $this->lastUpdated = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNominee(): ?Nominee
    {
        return $this->nominee;
    }

    public function setNominee(?Nominee $nominee): self
    {
        $this->nominee = $nominee;
        $this->lastUpdated = new DateTimeImmutable();
        $this->getFantasyUser()->setLastUpdated($this->lastUpdated);

        return $this;
    }

    public function getFantasyUser(): ?FantasyUser
    {
        return $this->fantasyUser;
    }

    public function setFantasyUser(?FantasyUser $fantasyUser): self
    {
        $this->fantasyUser = $fantasyUser;

        return $this;
    }

    public function getAward(): ?Award
    {
        return $this->award;
    }

    public function setAward(?Award $award): self
    {
        $this->award = $award;

        return $this;
    }
}
