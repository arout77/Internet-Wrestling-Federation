<?php

namespace App\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection; // <-- ADD THIS
use Doctrine\ORM\Mapping as ORM;
// <-- ADD THIS

#[ORM\Entity]
#[ORM\Table( name: 'all_moves' )]
class Move
{
    #[ORM\Id]
    #[ORM\Column( type: 'integer' )]
    #[ORM\GeneratedValue]
    private int $move_id;

    #[ORM\Column( type: 'string', length: 50, nullable: true )]
    private ?string $move_name;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $type;

    #[ORM\Column( type: 'string', length: 50 )]
    private string $min_damage;

    #[ORM\Column( type: 'integer' )]
    private int $max_damage;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $baseHitChance;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $stat;

    #[ORM\Column( type: 'integer', nullable: true )]
    private ?int $stamina_cost;

    #[ORM\Column( type: 'integer', nullable: true )]
    private ?int $momentumGain;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $pinAttemptChance;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $submissionAttemptChance;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $move_description;

    #[ORM\Column( type: 'integer', options: ['default' => 1] )]
    private int $level_requirement;

    #[ORM\Column( type: 'integer', options: ['default' => 500] )]
    private int $cost;

    #[ORM\Column( type: 'integer', nullable: true )]
    private ?int $weight_limit;

    /**
     * NEW: Inverse side of the relationship
     */
    #[ORM\ManyToMany( targetEntity: Prospect::class, mappedBy: 'moves' )]
    private Collection $prospects;

    /**
     * NEW: Inverse side of the relationship
     */
    #[ORM\ManyToMany( targetEntity: Roster::class, mappedBy: 'moves' )]
    private Collection $rosterWrestlers;

    public function __construct()
    {
        $this->prospects       = new ArrayCollection(); // <-- ADD THIS
        $this->rosterWrestlers = new ArrayCollection(); // <-- ADD THIS
    }

    // --- Getters ---

    /**
     * @return mixed
     */
    public function getMoveId(): int
    {
        return $this->move_id;
    }

    /**
     * @return mixed
     */
    public function getMoveName(): ?string
    {
        return $this->move_name;
    }

    /**
     * @return mixed
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getMinDamage(): string
    {
        return $this->min_damage;
    }

    /**
     * @return mixed
     */
    public function getMaxDamage(): int
    {
        return $this->max_damage;
    }

    /**
     * @return mixed
     */
    public function getBaseHitChance(): ?string
    {
        return $this->baseHitChance;
    }

    /**
     * @return mixed
     */
    public function getStat(): ?string
    {
        return $this->stat;
    }

    /**
     * @return mixed
     */
    public function getStaminaCost(): ?int
    {
        return $this->stamina_cost;
    }

    /**
     * @return mixed
     */
    public function getMomentumGain(): ?int
    {
        return $this->momentumGain;
    }

    /**
     * @return mixed
     */
    public function getPinAttemptChance(): ?string
    {
        return $this->pinAttemptChance;
    }

    /**
     * @return mixed
     */
    public function getSubmissionAttemptChance(): ?string
    {
        return $this->submissionAttemptChance;
    }

    /**
     * @return mixed
     */
    public function getMoveDescription(): ?string
    {
        return $this->move_description;
    }
}
