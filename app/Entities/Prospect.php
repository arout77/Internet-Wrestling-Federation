<?php

namespace App\Entities;

use App\Entities\User;
use App\Entities\WrestlerTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table( name: 'prospects' )]
#[ORM\HasLifecycleCallbacks]
class Prospect implements WrestlerInterface// <-- 1. IMPLEMENT THE INTERFACE

{
    #[ORM\Column( type: 'integer' )]
    private int $id;

    #[ORM\Id]
    #[ORM\Column( type: 'string', length: 255, unique: true )]
    private string $pid;

    /**
     * This defines the other side of the OneToOne relationship.
     * 'prospect' is the name of the property in the User entity.
     */
    #[ORM\OneToOne( mappedBy: 'prospect', targetEntity: User::class, cascade: ['persist'] )]
    private ?User $user;

    #[ORM\Column( type: 'string', length: 50, nullable: true )]
    private ?string $name;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $height;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $weight;

    #[ORM\Column( type: 'text', nullable: true )]
    private ?string $description;

    // Gold: For career progression (match rewards, training, store purchases)
    #[ORM\Column( type: 'integer', options: ['default' => 0] )]
    private int $gold;

    #[ORM\Column( type: 'integer', options: ['default' => 0] )]
    private int $current_xp;

    #[ORM\Column( type: 'integer', options: ['default' => 1] )]
    private int $lvl;

    #[ORM\Column( type: 'integer', options: ['default' => 25] )]
    private int $attribute_points;

    #[ORM\Column( type: 'string', length: 255, nullable: true )]
    private ?string $archetype;

    #[ORM\Column( type: 'boolean', options: ['default' => 0] )]
    private bool $is_world_champ;

    #[ORM\Column( type: 'integer', options: ['default' => 800] )]
    private int $baseHp;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $strength;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $technicalAbility;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $brawlingAbility;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $stamina;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $aerialAbility;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $toughness;

    #[ORM\Column( type: 'integer', options: ['default' => 60] )]
    private int $reversalAbility;

    #[ORM\Column( type: 'integer', options: ['default' => 60] )]
    private int $submissionDefense;

    #[ORM\Column( type: 'integer', options: ['default' => 4] )]
    private int $staminaRecoveryRate;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $tendency_strike;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $tendency_grapple;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $tendency_submission;

    #[ORM\Column( type: 'integer', options: ['default' => 50] )]
    private int $tendency_highfly;

    #[ORM\Column( type: 'string', length: 255 )]
    private string $image;

    #[ORM\Column( type: 'integer', nullable: true )]
    private ?int $manager_id;

    /**
     * Relationship to Moves (via prospect_moves table)
     */
    #[ORM\ManyToMany( targetEntity: Move::class, cascade: ['persist'] )]
    #[ORM\JoinTable( name: 'prospect_moves' )]
    #[ORM\JoinColumn( name: 'prospect_id', referencedColumnName: 'pid' )] // <-- FIX: Was 'id', changed to 'pid'
    #[ORM\InverseJoinColumn( name: 'move_id', referencedColumnName: 'move_id' )]
    private Collection $moves;

    /**
     * Relationship to Traits (via prospect_traits table)
     */
    #[ORM\ManyToMany( targetEntity: WrestlerTrait::class, cascade: ['persist'] )]
    #[ORM\JoinTable( name: 'prospect_traits' )]
    #[ORM\JoinColumn( name: 'prospect_id', referencedColumnName: 'id' )]
    #[ORM\InverseJoinColumn( name: 'trait_id', referencedColumnName: 'trait_id' )]
    private Collection $traits;

    public function __construct()
    {
        // This line generates the prospect ID.
        $this->gold                = 0;
        $this->current_xp          = 0;
        $this->lvl                 = 1;
        $this->attribute_points    = 25;
        $this->is_world_champ      = false;
        $this->baseHp              = 800;
        $this->strength            = 50;
        $this->technicalAbility    = 50;
        $this->brawlingAbility     = 50;
        $this->stamina             = 50;
        $this->aerialAbility       = 50;
        $this->toughness           = 50;
        $this->reversalAbility     = 60;
        $this->submissionDefense   = 60;
        $this->staminaRecoveryRate = 4;
        $this->tendency_strike     = 50;
        $this->tendency_grapple    = 50;
        $this->tendency_submission = 50;
        $this->tendency_highfly    = 50;
        $this->user                = null;
        $this->moves               = new ArrayCollection();
        $this->traits              = new ArrayCollection();
    }

    /**
     * This Doctrine callback runs just before the entity is saved.
     * This is the correct place to generate the PID.
     */
    #[ORM\PrePersist]
    public function generatePid(): void
    {
        // Only generate a new PID if one isn't already set
        if ( !isset( $this->pid ) ) {
            $this->pid = bin2hex( random_bytes( 16 ) );
        }
    }

    // --- Getters and Setters ---

    /**
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getPid(): ?string
    {
        return $this->pid;
    }

    /**
     * @return mixed
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser( ?User $user ): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName( ?string $name ): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getHeight(): ?string
    {
        return $this->height;
    }

    /**
     * @param string $height
     */
    public function setHeight( ?string $height ): void
    {
        $this->height = $height;
    }

    /**
     * @return mixed
     */
    public function getWeight(): ?string
    {
        return $this->weight;
    }

    /**
     * @param string $weight
     */
    public function setWeight( ?string $weight ): void
    {
        $this->weight = $weight;
    }

    /**
     * @return mixed
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription( ?string $description ): void
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getGold(): int
    {
        return $this->gold;
    }

    /**
     * @param int $gold
     */
    public function setGold( int $gold ): void
    {
        $this->gold = $gold;
    }

    /**
     * @return mixed
     */
    public function getCurrentXp(): int
    {
        return $this->current_xp;
    }

    /**
     * @param int $current_xp
     */
    public function setCurrentXp( int $current_xp ): void
    {
        $this->current_xp = $current_xp;
    }

    /**
     * @return mixed
     */
    public function getLvl(): int
    {
        return $this->lvl;
    }

    /**
     * @param int $lvl
     */
    public function setLvl( int $lvl ): void
    {
        $this->lvl = $lvl;
    }

    /**
     * @return mixed
     */
    public function getAttributePoints(): int
    {
        return $this->attribute_points;
    }

    /**
     * @param int $attribute_points
     */
    public function setAttributePoints( int $attribute_points ): void
    {
        $this->attribute_points = $attribute_points;
    }

    /**
     * @return mixed
     */
    public function getArchetype(): ?string
    {
        return $this->archetype;
    }

    /**
     * @param string $archetype
     */
    public function setArchetype( ?string $archetype ): void
    {
        $this->archetype = $archetype;
    }

    /**
     * @return mixed
     */
    public function isIsWorldChamp(): bool
    {
        return $this->is_world_champ;
    }

    /**
     * @param bool $is_world_champ
     */
    public function setIsWorldChamp( bool $is_world_champ ): void
    {
        $this->is_world_champ = $is_world_champ;
    }

    /**
     * @return mixed
     */
    public function getBaseHp(): int
    {
        return $this->baseHp;
    }

    /**
     * @param int $baseHp
     */
    public function setBaseHp( int $baseHp ): void
    {
        $this->baseHp = $baseHp;
    }

    /**
     * @return mixed
     */
    public function getStrength(): int
    {
        return $this->strength;
    }

    /**
     * @param int $strength
     */
    public function setStrength( int $strength ): void
    {
        $this->strength = $strength;
    }

    /**
     * @return mixed
     */
    public function getTechnicalAbility(): int
    {
        return $this->technicalAbility;
    }

    /**
     * @param int $technicalAbility
     */
    public function setTechnicalAbility( int $technicalAbility ): void
    {
        $this->technicalAbility = $technicalAbility;
    }

    /**
     * @return mixed
     */
    public function getBrawlingAbility(): int
    {
        return $this->brawlingAbility;
    }

    /**
     * @param int $brawlingAbility
     */
    public function setBrawlingAbility( int $brawlingAbility ): void
    {
        $this->brawlingAbility = $brawlingAbility;
    }

    /**
     * @return mixed
     */
    public function getStamina(): int
    {
        return $this->stamina;
    }

    /**
     * @param int $stamina
     */
    public function setStamina( int $stamina ): void
    {
        $this->stamina = $stamina;
    }

    /**
     * @return mixed
     */
    public function getAerialAbility(): int
    {
        return $this->aerialAbility;
    }

    /**
     * @param int $aerialAbility
     */
    public function setAerialAbility( int $aerialAbility ): void
    {
        $this->aerialAbility = $aerialAbility;
    }

    /**
     * @return mixed
     */
    public function getToughness(): int
    {
        return $this->toughness;
    }

    /**
     * @param int $toughness
     */
    public function setToughness( int $toughness ): void
    {
        $this->toughness = $toughness;
    }

    /**
     * @return mixed
     */
    public function getReversalAbility(): int
    {
        return $this->reversalAbility;
    }

    /**
     * @param int $reversalAbility
     */
    public function setReversalAbility( int $reversalAbility ): void
    {
        $this->reversalAbility = $reversalAbility;
    }

    /**
     * @return mixed
     */
    public function getSubmissionDefense(): int
    {
        return $this->submissionDefense;
    }

    /**
     * @param int $submissionDefense
     */
    public function setSubmissionDefense( int $submissionDefense ): void
    {
        $this->submissionDefense = $submissionDefense;
    }

    /**
     * @return mixed
     */
    public function getStaminaRecoveryRate(): int
    {
        return $this->staminaRecoveryRate;
    }

    /**
     * @param int $staminaRecoveryRate
     */
    public function setStaminaRecoveryRate( int $staminaRecoveryRate ): void
    {
        $this->staminaRecoveryRate = $staminaRecoveryRate;
    }

    /**
     * @return mixed
     */
    public function getTendencyStrike(): int
    {
        return $this->tendency_strike;
    }

    /**
     * @param int $tendency_strike
     */
    public function setTendencyStrike( int $tendency_strike ): void
    {
        $this->tendency_strike = $tendency_strike;
    }

    /**
     * @return mixed
     */
    public function getTendencyGrapple(): int
    {
        return $this->tendency_grapple;
    }

    /**
     * @param int $tendency_grapple
     */
    public function setTendencyGrapple( int $tendency_grapple ): void
    {
        $this->tendency_grapple = $tendency_grapple;
    }

    /**
     * @return mixed
     */
    public function getTendencySubmission(): int
    {
        return $this->tendency_submission;
    }

    /**
     * @param int $tendency_submission
     */
    public function setTendencySubmission( int $tendency_submission ): void
    {
        $this->tendency_submission = $tendency_submission;
    }

    /**
     * @return mixed
     */
    public function getTendencyHighfly(): int
    {
        return $this->tendency_highfly;
    }

    /**
     * @param int $tendency_highfly
     */
    public function setTendencyHighfly( int $tendency_highfly ): void
    {
        $this->tendency_highfly = $tendency_highfly;
    }

    /**
     * @return mixed
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @param string $image
     */
    public function setImage( string $image ): void
    {
        $this->image = $image;
    }

    /**
     * @return mixed
     */
    public function getManagerId(): ?int
    {
        return $this->manager_id;
    }

    /**
     * @param int $manager_id
     */
    public function setManagerId( ?int $manager_id ): void
    {
        $this->manager_id = $manager_id;
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
     * NEW: Helper function to get an array of trait names.
     * Required by WrestlerInterface.
     * @return string[]
     */
    public function getTraitNames(): array
    {
        return $this->traits->map( fn( WrestlerTrait $trait ) => $trait->getName() )->toArray();
    }

    /**
     * NEW: Gets the simulation ID for this wrestler.
     * Required by WrestlerInterface.
     * @return int|string
     */
    public function getSimulationId(): int | string
    {
        return $this->getPid();
    }

    /**
     * Calculates the wrestler's Overall Rating using your weighted formula.
     */
    public function getOverallRating(): int
    {
        $coreSkillSum =
        ( $this->strength * 1.01 ) +
        ( $this->technicalAbility * 1.2 ) +
        $this->brawlingAbility +
            ( $this->aerialAbility * 1.15 );

        $coreSkillAvg       = $coreSkillSum / 4.36;
        $durabilityAvg      = ( $this->stamina + $this->toughness ) / 2;
        $preliminaryOverall = ( $coreSkillAvg * 0.7 ) + ( $durabilityAvg * 0.3 );

        $num_stats_over_80 = 0;
        $num_stats_over_95 = 0;

        if ( $this->strength >= 80 ) {$num_stats_over_80++;}
        if ( $this->technicalAbility >= 80 ) {$num_stats_over_80++;}
        if ( $this->brawlingAbility >= 80 ) {$num_stats_over_80++;}
        if ( $this->aerialAbility >= 80 ) {$num_stats_over_80++;}
        if ( $this->strength >= 95 ) {$num_stats_over_95++;}
        if ( $this->technicalAbility >= 95 ) {$num_stats_over_95++;}
        if ( $this->brawlingAbility >= 95 ) {$num_stats_over_95++;}
        if ( $this->aerialAbility >= 95 ) {$num_stats_over_95++;}
        $bonus = 0;
        if ( $num_stats_over_80 >= 4 && $durabilityAvg >= 90 ) {
            $bonus = 5 + $num_stats_over_95; // Icon Bonus
        } elseif ( $num_stats_over_80 >= 3 ) {
            $bonus = 3 + $num_stats_over_95; // Legend Bonus
        } elseif ( $num_stats_over_80 >= 2 ) {
            $bonus = 1 + $num_stats_over_95; // Prime Bonus
        }

        return (int) round( $preliminaryOverall + $bonus );
    }

    // --- NEW CAREER/LEVELING METHODS ---

    /**
     * Adds XP to the prospect and checks for level-ups.
     *
     * @param int $amount The amount of XP to add.
     * @return array An array indicating if a level-up occurred.
     */
    public function addXp( int $amount ): array
    {
        if ( $this->lvl >= 100 ) {
            return ['leveled_up' => false]; // Max level reached
        }

        $this->current_xp += $amount;
        $leveledUp = false;

        $xpNeeded = $this->getXpRequiredForNextLevel();

        // Use a while loop in case they earn enough XP for multiple levels
        while ( $this->current_xp >= $xpNeeded && $this->lvl < 100 ) {
            $this->current_xp -= $xpNeeded; // Subtract the cost for this level
            $this->levelUp(); // Perform the level-up
            $leveledUp = true;

            // Get the XP needed for the *new* level
            $xpNeeded = $this->getXpRequiredForNextLevel();
        }

        return ['leveled_up' => $leveledUp];
    }

    /**
     * Calculates the total XP required to reach the *next* level.
     * Formula: Base 100, scaling with an exponent.
     *
     * @return int
     */
    public function getXpRequiredForNextLevel(): int
    {
        if ( $this->lvl >= 100 ) {
            return 0; // No more XP needed
        }

        // Formula: 100 * (Current Level ^ 1.2)
        // Lvl 1 -> Lvl 2: 100 * (1^1.2) = 100 XP
        // Lvl 2 -> Lvl 3: 100 * (2^1.2) = 230 XP
        // Lvl 10 -> Lvl 11: 100 * (10^1.2) = 1585 XP
        // Lvl 50 -> Lvl 51: 100 * (50^1.2) = 11180 XP
        return (int) floor( 100 * pow( $this->lvl, 1.2 ) );
    }

    /**
     * Performs the actual level-up, applying rewards.
     * This is a private method called by addXp().
     */
    private function levelUp(): void
    {
        if ( $this->lvl >= 100 ) {
            return;
        }

        $this->lvl++;

        // Rule: 1 Attribute Point per level, 3 points every 10th level
        if ( $this->lvl % 10 === 0 ) {
            // Bonus for 10th level
            $this->attribute_points += 3;
        } else {
            // Standard level up
            $this->attribute_points++;
        }

        // Rule: Gold = New Level * 100
        $levelUpGold = $this->lvl * 100;
        $this->gold += $levelUpGold;

        // Rule: Max Level 100
        if ( $this->lvl > 100 ) {
            $this->lvl = 100;
        }
    }
}
