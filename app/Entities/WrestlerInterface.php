<?php
namespace App\Entities;

use Doctrine\Common\Collections\Collection;

/**
 * Interface WrestlerInterface
 *
 * Defines a common "contract" for any entity that can be simulated in a match.
 * Both Roster and Prospect entities will implement this interface.
 */
interface WrestlerInterface
{
    /**
     * Gets the unique ID used for simulation (WrestlerID for Roster, PID for Prospect).
     * @return int|string
     */
    public function getSimulationId(): int | string;

    /**
     * Gets the wrestler's name.
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Gets the wrestler's image path.
     * @return string
     */
    public function getImage(): string;

    /**
     * Gets the wrestler's base HP.
     * @return int|null
     */
    public function getBaseHp(): ?int;

    /**
     * Gets the wrestler's Toughness stat.
     * @return int|null
     */
    public function getToughness(): ?int;

    /**
     * Gets the wrestler's Stamina Recovery Rate.
     * @return int|null
     */
    public function getStaminaRecoveryRate(): ?int;

    /**
     * Gets the wrestler's Reversal Ability stat.
     * @return int|null
     */
    public function getReversalAbility(): ?int;

    /**
     * Gets the wrestler's Technical Ability stat.
     * @return int|null
     */
    public function getTechnicalAbility(): ?int;

    /**
     * Gets the wrestler's Strength stat.
     * @return int|null
     */
    public function getStrength(): ?int;

    /**
     * Gets the wrestler's Brawling Ability stat.
     * @return int|null
     */
    public function getBrawlingAbility(): ?int;

    /**
     * Gets the wrestler's Aerial Ability stat.
     * @return int|null
     */
    public function getAerialAbility(): ?int;

    /**
     * Gets the wrestler's collection of Moves.
     * @return Collection
     */
    public function getMoves(): Collection;

    /**
     * Gets the wrestler's collection of Traits.
     * @return Collection
     */
    public function getTraits(): Collection;

    /**
     * Gets a simple array of the wrestler's trait names.
     * @return string[]
     */
    public function getTraitNames(): array;

    /**
     * Gets the wrestler's calculated Overall Rating.
     * @return int
     */
    public function getOverallRating(): int;

    /**
     * Gets the wrestler's Level.
     * @return int
     */
    public function getLvl(): int;

    /**
     * Gets the wrestler's archetype (if any).
     * @return string|null
     */
    public function getArchetype(): ?string;
}
