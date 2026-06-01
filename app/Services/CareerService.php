<?php

namespace App\Services;

use App\Entities\Prospect;
use Doctrine\ORM\EntityManager;

/**
 * CareerService
 *
 * This service handles the core gameplay loop for a Prospect's career,
 * including calculating match rewards (XP, Gold) and triggering level-ups.
 */
class CareerService
{
    /**
     * Inject the EntityManager to save changes to the database.
     *
     * @param EntityManager $em
     */
    public function __construct( protected EntityManager $em )
    {
    }

    /**
     * Processes the result of a match for a Prospect.
     * Calculates Gold and XP rewards, applies them, and triggers level-up checks.
     *
     * @param Prospect $prospect      The Prospect who just fought.
     * @param bool     $isWin         Whether the Prospect won the match.
     * @param int      $opponentLevel The level of the opponent they faced.
     * @return array                An array summarizing the rewards.
     */
    public function processMatchResult( Prospect $prospect, bool $isWin, int $opponentLevel ): array
    {
        // --- 1. Calculate Base Rewards (based on your rules) ---
        $baseGoldMin = 30; // Base win gold (Lvl 1)
        $baseGoldMax = 50;
        $baseXpMin   = 15; // Base win XP (Lvl 1)
        $baseXpMax   = 25;

        // --- 2. Apply Level Scaling ---
        // Rewards increase as the prospect levels up.
        // e.g., Level 1 = 1.1x, Level 10 = 2.0x, Level 50 = 6.0x
        $levelMultiplier = 1 + ( $prospect->getLvl() * 0.1 );

        // Calculate base amounts
        $gold = rand( $baseGoldMin, $baseGoldMax ) * $levelMultiplier;
        $xp   = rand( $baseXpMin, $baseXpMax ) * $levelMultiplier;

        // --- 3. Apply Win/Loss Modifier ---
        if ( !$isWin ) {
            $gold *= 0.5; // 50% reward for losing
            $xp *= 0.5; // 50% reward for losing
        }

        // --- 4. Apply Opponent Level Bonus ---
        // Add a small XP bonus for defeating a higher-level opponent (or penalty for a lower)
        // This uses the opponentLevel you can pass in from the controller.
        $opponentMultiplier = 1 + ( ( $opponentLevel - $prospect->getLvl() ) * 0.05 ); // +/- 5% per level difference
        $xp *= max( 0.5, $opponentMultiplier ); // Cap penalty at 50%, no cap on bonus

        // --- 5. Finalize Amounts ---
        $gold = (int) floor( $gold );
        $xp   = (int) floor( $xp );

        // --- 6. Apply to Prospect Entity ---
        $prospect->setGold( $prospect->getGold() + $gold );

        // addXp() will check for level-ups and apply attribute/gold rewards
        $levelResult = $prospect->addXp( $xp );

        // --- 7. Save to Database ---
        $managedProspect = $this->em->merge( $prospect ); // Use merge to re-attach the entity
        $this->em->flush();

        // --- 8. Return Summary ---
        return [
            'gold_earned' => $gold,
            'xp_earned'   => $xp,
            'leveled_up'  => $levelResult['leveled_up'],
            'new_level'   => $managedProspect->getLvl(),
            'new_gold'    => $managedProspect->getGold(),
            'new_xp'      => $managedProspect->getCurrentXp(),
            'xp_needed'   => $managedProspect->getXpRequiredForNextLevel(),
        ];
    }
}
