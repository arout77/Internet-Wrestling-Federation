<?php
namespace App\Entities;

use App\Entities\WrestlerTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roster')]
class Roster implements WrestlerInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $wrestler_id;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $height;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $weight;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'integer')]
    private int $lvl;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $is_world_champ;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $tag_team;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $baseHp;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $strength;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $technicalAbility;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $brawlingAbility;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stamina;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $aerialAbility;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $toughness;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $reversalAbility;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $submissionDefense;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $staminaRecoveryRate;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $tendency_strike;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $tendency_grapple;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $tendency_submission;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $tendency_highfly;

    #[ORM\Column(type: 'string', length: 50)]
    private string $image;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $archetype = null;

    /**
     * Relationship to Moves (via roster_moves table)
     */
    #[ORM\ManyToMany(targetEntity: Move::class)]
    #[ORM\JoinTable(name: 'roster_moves')]
    #[ORM\JoinColumn(name: 'roster_wrestler_id', referencedColumnName: 'wrestler_id')]
    #[ORM\InverseJoinColumn(name: 'move_id', referencedColumnName: 'move_id')]
    private Collection $moves;

    /**
     * Relationship to Traits (via roster_traits table)
     */
    #[ORM\ManyToMany(targetEntity: WrestlerTrait::class)]
    #[ORM\JoinTable(name: 'roster_traits')]
    #[ORM\JoinColumn(name: 'roster_wrestler_id', referencedColumnName: 'wrestler_id')]
    #[ORM\InverseJoinColumn(name: 'trait_id', referencedColumnName: 'trait_id')]
    private Collection $traits;

    public function __construct()
    {
        $this->is_world_champ      = false;
        $this->tendency_strike     = 50;
        $this->tendency_grapple    = 50;
        $this->tendency_submission = 50;
        $this->tendency_highfly    = 50;
        $this->moves               = new ArrayCollection();
        $this->traits              = new ArrayCollection();
    }

    // --- Getters and Setters ---

    /**
     * @return mixed
     */
    public function getWrestlerId(): int
    {
        return $this->wrestler_id;
    }

    /**
     * @return mixed
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    public function getArchetype(): ?string
    {
        return $this->archetype;
    }

    public function setArchetype(?string $archetype): void
    {
        $this->archetype = $archetype;
    }

    /**
     * @return mixed
     */
    public function getHeight(): ?string
    {
        return $this->height;
    }

    /**
     * @return mixed
     */
    public function getWeight(): ?string
    {
        return $this->weight;
    }

    /**
     * @return mixed
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return mixed
     */
    public function getLvl(): int
    {
        return $this->lvl;
    }

    /**
     * @return mixed
     */
    public function isIsWorldChamp(): bool
    {
        return $this->is_world_champ;
    }

    /**
     * @return mixed
     */
    public function getTagTeam(): ?string
    {
        return $this->tag_team;
    }

    /**
     * @return mixed
     */
    public function getBaseHp(): ?int
    {
        return $this->baseHp;
    }

    /**
     * @return mixed
     */
    public function getStrength(): ?int
    {
        return $this->strength;
    }

    /**
     * @return mixed
     */
    public function getTechnicalAbility(): ?int
    {
        return $this->technicalAbility;
    }

    /**
     * @return mixed
     */
    public function getBrawlingAbility(): ?int
    {
        return $this->brawlingAbility;
    }

    /**
     * @return mixed
     */
    public function getStamina(): ?int
    {
        return $this->stamina;
    }

    /**
     * @return mixed
     */
    public function getAerialAbility(): ?int
    {
        return $this->aerialAbility;
    }

    /**
     * @return mixed
     */
    public function getToughness(): ?int
    {
        return $this->toughness;
    }

    /**
     * @return mixed
     */
    public function getReversalAbility(): ?int
    {
        return $this->reversalAbility;
    }

    /**
     * @return mixed
     */
    public function getSubmissionDefense(): ?int
    {
        return $this->submissionDefense;
    }

    /**
     * @return mixed
     */
    public function getStaminaRecoveryRate(): ?int
    {
        return $this->staminaRecoveryRate;
    }

    /**
     * @return mixed
     */
    public function getTendencyStrike(): int
    {
        return $this->tendency_strike;
    }

    /**
     * @return mixed
     */
    public function getTendencyGrapple(): int
    {
        return $this->tendency_grapple;
    }

    /**
     * @return mixed
     */
    public function getTendencySubmission(): int
    {
        return $this->tendency_submission;
    }

    /**
     * @return mixed
     */
    public function getTendencyHighfly(): int
    {
        return $this->tendency_highfly;
    }

    /**
     * @return mixed
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @return Collection|Move[]
     */
    public function getMoves(): Collection
    {
        return $this->moves;
    }

    /**
     * @return Collection|WrestlerTrait[]
     */
    public function getTraits(): Collection
    {
        return $this->traits;
    }

    /**
     * Helper function to get an array of trait names.
     * @return string[]
     */
    public function getTraitNames(): array
    {
        return $this->traits->map(fn(WrestlerTrait $trait) => $trait->getName())->toArray();
    }

    /**
     * NEW: Gets the simulation ID for this wrestler.
     * Required by WrestlerInterface.
     * @return int|string
     */
    public function getSimulationId(): int | string
    {
        return $this->getWrestlerId();
    }

    /**
     * UPDATED: Calculates the wrestler's Overall Rating using your weighted formula.
     */
    public function getOverallRating(): int
    {
        $coreSkillSum =
        ($this->strength * 1.01) +
        ($this->technicalAbility * 1.2) +
        $this->brawlingAbility +
            ($this->aerialAbility * 1.15);

        $coreSkillAvg       = $coreSkillSum / 4.36;
        $durabilityAvg      = ($this->stamina + $this->toughness) / 2;
        $preliminaryOverall = ($coreSkillAvg * 0.7) + ($durabilityAvg * 0.3);

        $num_stats_over_80 = 0;
        $num_stats_over_95 = 0;

        if ($this->strength >= 80) {$num_stats_over_80++;}
        if ($this->technicalAbility >= 80) {$num_stats_over_80++;}
        if ($this->brawlingAbility >= 80) {$num_stats_over_80++;}
        if ($this->aerialAbility >= 80) {$num_stats_over_80++;}
        if ($this->strength >= 95) {$num_stats_over_95++;}
        if ($this->technicalAbility >= 95) {$num_stats_over_95++;}
        if ($this->brawlingAbility >= 95) {$num_stats_over_95++;}
        if ($this->aerialAbility >= 95) {$num_stats_over_95++;}
        $bonus = 0;
        if ($num_stats_over_80 >= 4 && $durabilityAvg >= 90) {
            $bonus = 5 + $num_stats_over_95; // Icon Bonus
        } elseif ($num_stats_over_80 >= 3) {
            $bonus = 3 + $num_stats_over_95; // Legend Bonus
        } elseif ($num_stats_over_80 >= 2) {
            $bonus = 1 + $num_stats_over_95; // Prime Bonus
        }

        return (int) round($preliminaryOverall + $bonus);
    }
}
