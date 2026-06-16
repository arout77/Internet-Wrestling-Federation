<?php
namespace App\Services;

use App\Entities\Move;
use App\Entities\WrestlerInterface;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\Cache;

/**
 * SimulationService
 *
 * Handles all match simulations for the IWF game engine.
 * Provides:
 * - Bulk odds calculation with caching (generateOdds)
 * - Turn-by-turn visual match simulation (runVisualMatch)
 * - Detailed formulas for hit chance, damage, reversals, pins, submissions
 * - Special handling for giants, high-flyers, technical wrestlers, etc.
 * - Full support for match stipulations (Steel Cage, Ladder, I Quit,
 *   Last Man Standing, No DQ) with custom victory conditions and bonuses
 *   for traits like Dirty Player in No DQ matches.
 */
class SimulationService
{
    // Vigorish (house edge) for moneyline odds calculation (10%)
    private float $vig = 0.10;

    // HP threshold below which a finisher can be attempted (300 HP)
    private const FINISHER_HP_THRESHOLD = 300;

    // HP percentage threshold for submission attempts (20% of max HP)
    private const SUBMISSION_HP_THRESHOLD = 0.20;

    /**
     * Constructor – inject dependencies
     *
     * @param EntityManager $em Doctrine entity manager
     * @param Cache $cache Cache service for odds storage
     */
    public function __construct(protected EntityManager $em, protected Cache $cache)
    {}

    // ----------------------------------------------------------------------
    // Public methods
    // ----------------------------------------------------------------------

    /**
     * Calculates win probabilities for two wrestlers by running multiple simulations.
     * Results are cached to avoid recalculation.
     *
     * @param WrestlerInterface $wrestler1 First wrestler
     * @param WrestlerInterface $wrestler2 Second wrestler
     * @param int $numSims Number of simulations to run (default 100)
     * @return array Associative array with win percentages, moneyline odds, and raw counts
     */
    public function generateOdds(WrestlerInterface $wrestler1, WrestlerInterface $wrestler2, int $numSims = 100): array
    {
        // Create a cache key that includes both wrestler IDs and the simulation count
        $cacheKey   = "odds_w{$wrestler1->getSimulationId()}_w{$wrestler2->getSimulationId()}_s{$numSims}";
        $cachedOdds = $this->cache->get($cacheKey);
        if ($cachedOdds) {
            return $cachedOdds; // Return cached result to save time
        }

        $w1_wins = $w2_wins = $draws = 0;

        // Run the requested number of simulations
        for ($i = 0; $i < $numSims; $i++) {
            // Run a match without generating a detailed log (faster)
            $log      = $this->runVisualMatch($wrestler1, $wrestler2, false, null);
            $endEvent = end($log);
            if ($endEvent['type'] === 'end') {
                if ($endEvent['data']['winner_id'] === 'w1') {
                    $w1_wins++;
                } elseif ($endEvent['data']['winner_id'] === 'w2') {
                    $w2_wins++;
                } else {
                    $draws++;
                }
            }
        }

        // Calculate percentages
        $w1_win_percent = $numSims > 0 ? round(($w1_wins / $numSims) * 100, 1) : 0;
        $w2_win_percent = $numSims > 0 ? round(($w2_wins / $numSims) * 100, 1) : 0;

        // Build the result array with win counts, percentages, and moneyline odds
        $results = [
            'success'        => true,
            'w1_name'        => $wrestler1->getName(),
            'w1_wins'        => $w1_wins,
            'w1_win_percent' => $w1_win_percent,
            'w1_odds'        => $this->calculateMoneyline($w1_win_percent),
            'w2_name'        => $wrestler2->getName(),
            'w2_wins'        => $w2_wins,
            'w2_win_percent' => $w2_win_percent,
            'w2_odds'        => $this->calculateMoneyline($w2_win_percent),
            'draws'          => $draws,
            'draw_percent'   => $numSims > 0 ? round(($draws / $numSims) * 100, 1) : 0,
            'total_sims'     => $numSims,
        ];

        // Cache the results for a very long time (846000 minutes = about 1.6 years)
        $this->cache->put($cacheKey, $results, 846000);
        return $results;
    }

    /**
     * Runs a single match simulation, optionally returning a detailed turn-by-turn log.
     * This is the core of the simulation engine.
     *
     * @param WrestlerInterface $wrestler1 First wrestler
     * @param WrestlerInterface $wrestler2 Second wrestler
     * @param bool $generateLog If true, returns array of log events; if false, returns minimal log
     * @param string|null $stipulation Match stipulation (cage, ladder, i_quit, last_man_standing, no_dq)
     * @return array Array of log entries (each with 'type' and 'data')
     */
    public function runVisualMatch(WrestlerInterface $wrestler1, WrestlerInterface $wrestler2, bool $generateLog = true, ?string $stipulation = null): array
    {
        $log = [];
        $w1  = $wrestler1;
        $w2  = $wrestler2;

        // Initialise wrestler stats (HP, stamina, momentum, traits, etc.)
        $stats = [
            'w1' => $this->initWrestlerStats($w1),
            'w2' => $this->initWrestlerStats($w2),
        ];

        // Log the start of the match if detailed log is requested
        if ($generateLog) {
            $log[] = [
                'type' => 'start',
                'data' => [
                    'w1' => ['name' => $w1->getName(), 'image' => $w1->getImage(), 'max_hp' => $stats['w1']['max_hp']],
                    'w2' => ['name' => $w2->getName(), 'image' => $w2->getImage(), 'max_hp' => $stats['w2']['max_hp']],
                ],
            ];
        }

        $turn     = 0;
        $maxTurns = 200; // Maximum turns to prevent infinite loops; draws are declared if this limit is reached

        // Main simulation loop: continues until one wrestler's HP reaches 0 or max turns exceeded
        while ($stats['w1']['hp'] > 0 && $stats['w2']['hp'] > 0 && $turn < $maxTurns) {
            $turn++;
            if ($generateLog) {
                $log[] = ['type' => 'turn', 'data' => ['number' => $turn]];
            }

            // Determine which wrestler gets to act this turn (initiative based on momentum and stamina)
            $initiative  = $this->determineInitiative($stats['w1'], $stats['w2']);
            $attackerKey = $initiative['first'];
            $defenderKey = ($attackerKey === 'w1') ? 'w2' : 'w1';
            $attacker    = ($attackerKey === 'w1') ? $w1 : $w2;
            $defender    = ($attackerKey === 'w1') ? $w1 : $w2;

            // If the attacker is stunned, skip their turn and reduce stun counter
            if ($stats[$attackerKey]['stunned_turns'] > 0) {
                if ($generateLog) {
                    $log[] = ['type' => 'stunned', 'data' => ['name' => $attacker->getName()]];
                }

                $stats[$attackerKey]['stunned_turns']--;
                continue;
            }

            // Choose a move based on wrestler tendencies and available stamina
            $move = $this->chooseMove($attacker, $stats[$attackerKey], $stats[$defenderKey]);

            // Apply Workhorse trait: reduces stamina cost by 20%
            $staminaCost = $move->getStaminaCost();
            if (in_array('Workhorse', $stats[$attackerKey]['traits'])) {
                $staminaCost = max(1, (int) round($staminaCost * 0.8));
            }

            // If not enough stamina, the wrestler rests and recovers extra stamina
            if ($stats[$attackerKey]['stamina'] < $staminaCost) {
                if ($generateLog) {
                    $log[] = ['type' => 'exhausted', 'data' => ['name' => $attacker->getName(), 'move_name' => $move->getMoveName()]];
                }

                $stats[$attackerKey]['stamina'] = min(100, $stats[$attackerKey]['stamina'] + $stats[$attackerKey]['stamina_recovery'] * 2);
                $stats[$defenderKey]['stamina'] = min(100, $stats[$defenderKey]['stamina'] + $stats[$defenderKey]['stamina_recovery']);
                if ($generateLog) {
                    $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
                }

                continue;
            }

            // Deduct stamina cost
            $stats[$attackerKey]['stamina'] -= $staminaCost;

            // Check for reversal
            if ($this->attemptReversal($defender, $attacker, $move, $stats[$defenderKey])) {
                if ($generateLog) {
                    $log[] = [
                        'type' => 'reversal',
                        'data' => ['defender_name' => $defender->getName(), 'move_name' => $move->getMoveName()],
                    ];
                }

                // Reversal penalises the attacker's stamina and gives momentum to the defender
                $stats[$defenderKey]['stamina']  = max(0, $stats[$defenderKey]['stamina'] - 5);
                $stats[$defenderKey]['momentum'] = min(100, $stats[$defenderKey]['momentum'] + 15);
                if ($generateLog) {
                    $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
                }

                continue;
            }

            // Calculate hit chance and roll
            $hitChance = $this->calculateHitChance($attacker, $defender, $move, $stats[$attackerKey], $stipulation);
            $roll      = mt_rand(0, 10000) / 10000;

            if ($roll < $hitChance) {
                // Move hits: calculate damage, reduce defender's HP, increase attacker's momentum
                $damage                           = $this->calculateDamage($attacker, $defender, $move, $stats[$attackerKey], $stats[$defenderKey], $stipulation);
                $stats[$defenderKey]['hp']       -= $damage;
                $stats[$attackerKey]['momentum']  = min(100, $stats[$attackerKey]['momentum'] + $move->getMomentumGain());

                if ($generateLog) {
                    $log[] = [
                        'type' => 'move',
                        'data' => [
                            'attacker_name' => $attacker->getName(),
                            'defender_id'   => $defenderKey,
                            'move_name'     => $move->getMoveName(),
                            'damage'        => $damage,
                        ],
                    ];
                }

                // Check for knockout (HP <= 0)
                if ($stats[$defenderKey]['hp'] <= 0) {
                    $victoryMethod = $this->determineVictoryMethod($move, $stipulation);
                    $log[]         = [
                        'type' => 'end',
                        'data' => [
                            'winner_id'      => $attackerKey,
                            'winner_name'    => $attacker->getName(),
                            'victory_method' => $victoryMethod,
                        ],
                    ];
                    break;
                }

                // Try to win via stipulation‑specific victory (pin/submission/weapon)
                $victory = $this->checkVictory($attacker, $defender, $move, $stats[$attackerKey], $stats[$defenderKey], $stipulation);
                if ($victory) {
                    $log[] = [
                        'type' => 'end',
                        'data' => [
                            'winner_id'      => $attackerKey,
                            'winner_name'    => $attacker->getName(),
                            'victory_method' => $victory,
                        ],
                    ];
                    break;
                }
            } else {
                // Move missed: no damage, but still log the miss
                if ($generateLog) {
                    $log[] = ['type' => 'miss', 'data' => ['attacker_name' => $attacker->getName(), 'move_name' => $move->getMoveName()]];
                }
            }

            // Recover stamina at the end of the turn (stamina_recovery points per turn)
            $stats[$attackerKey]['stamina'] = min(100, $stats[$attackerKey]['stamina'] + $stats[$attackerKey]['stamina_recovery']);
            $stats[$defenderKey]['stamina'] = min(100, $stats[$defenderKey]['stamina'] + $stats[$defenderKey]['stamina_recovery']);
            if ($generateLog) {
                $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
            }

        }

        // If max turns reached without a knockout, declare a time limit draw
        if ($turn >= $maxTurns && $stats['w1']['hp'] > 0 && $stats['w2']['hp'] > 0) {
            $log[] = [
                'type' => 'end',
                'data' => [
                    'winner_id'      => null,
                    'winner_name'    => null,
                    'victory_method' => "Time Limit Draw",
                ],
            ];
        }

        return $log;
    }

    // ----------------------------------------------------------------------
    // Private helper methods
    // ----------------------------------------------------------------------

    /**
     * Initialises the in‑match stats for a wrestler: HP, stamina, momentum, etc.
     * HP is increased based on Toughness (Toughness/350 * base HP).
     *
     * @param WrestlerInterface $wrestler
     * @return array
     */
    private function initWrestlerStats(WrestlerInterface $wrestler): array
    {
        $baseHp = $wrestler->getBaseHp();
        $maxHp  = $baseHp + ($baseHp * ($wrestler->getToughness() / 350));
        return [
            'max_hp'           => $maxHp,
            'hp'               => $maxHp,
            'stamina'          => 100,
            'momentum'         => 0,
            'stamina_recovery' => $wrestler->getStaminaRecoveryRate(),
            'stunned_turns'    => 0,
            'traits'           => $wrestler->getTraitNames(),
            'archetype'        => method_exists($wrestler, 'getArchetype') ? $wrestler->getArchetype() : null,
            'weight'           => (int) $wrestler->getWeight(),
        ];
    }

    /**
     * Determines which wrestler goes first in a turn.
     * Initiative is based on a random roll plus momentum and stamina bonuses.
     *
     * @param array $statsW1 Stats of wrestler 1
     * @param array $statsW2 Stats of wrestler 2
     * @return array ['first' => 'w1' or 'w2']
     */
    private function determineInitiative(array $statsW1, array $statsW2): array
    {
        $scoreW1 = mt_rand(1, 100) + ($statsW1['momentum'] / 2) + ($statsW1['stamina'] / 4);
        $scoreW2 = mt_rand(1, 100) + ($statsW2['momentum'] / 2) + ($statsW2['stamina'] / 4);
        return ['first' => $scoreW1 >= $scoreW2 ? 'w1' : 'w2'];
    }

    /**
     * Attempts a reversal by the defender.
     * Reversal chance is based on defender's Reversal Ability and Technical Ability,
     * plus bonuses for giants (against grapple moves) and the Powerhouse archetype.
     *
     * @param WrestlerInterface $defender The defender trying to reverse
     * @param WrestlerInterface $attacker The attacker
     * @param Move $move The move being attempted
     * @param array $defenderStats Defender's in‑match stats
     * @return bool True if reversal succeeds
     */
    private function attemptReversal(WrestlerInterface $defender, WrestlerInterface $attacker, Move $move, array $defenderStats): bool
    {
        $moveType       = $move->getType();
        $baseReversal   = $defender->getReversalAbility();
        $technicalBonus = $defender->getTechnicalAbility() / 1000;
        $reversalChance = ($baseReversal / 600) + $technicalBonus;

        // Giants have a 20% higher reversal chance overall (for all move types)
        $isGiant = $this->isGiant($defender, $defenderStats);
        if ($isGiant) {
            $reversalChance *= 1.2;
        }

        // Extra giant bonus against grapple moves
        if (in_array('Giant', $defenderStats['traits']) && $moveType === 'grapple') {
            $reversalChance *= 1.3;
        }

        // Powerhouse archetype gets a 20% reversal bonus against grapple moves
        if ($defenderStats['archetype'] === 'powerhouse' && $moveType === 'grapple') {
            $reversalChance += 0.20;
        }

        // Cap reversal chance at 25% to prevent too many reversals
        $reversalChance = min(0.25, $reversalChance);
        return (mt_rand(0, 10000) / 10000) < $reversalChance;
    }

    /**
     * Determines if a wrestler is a "giant" (has the Giant trait or weighs >= 350 lbs).
     * Weight is cast to integer to avoid string comparison issues.
     *
     * @param WrestlerInterface $wrestler
     * @param array|null $stats Optional pre‑fetched stats array
     * @return bool
     */
    private function isGiant(WrestlerInterface $wrestler, ?array $stats = null): bool
    {
        $hasTrait = ($stats && in_array('Giant', $stats['traits'])) || in_array('Giant', $wrestler->getTraitNames());
        $weight   = (int) $wrestler->getWeight();
        return $hasTrait || $weight >= 350;
    }

    /**
     * Checks if a wrestler has the Dirty Player trait.
     *
     * @param WrestlerInterface $wrestler
     * @param array $stats
     * @return bool
     */
    private function hasDirtyPlayerTrait(WrestlerInterface $wrestler, array $stats): bool
    {
        return in_array('Dirty Player', $stats['traits']);
    }

    /**
     * Calculates the chance that a move successfully hits the opponent.
     * The base hit chance comes from the move's data, then modified by:
     * - Attribute differences (offense vs defense), scaled by move type.
     * - Archetype/trait bonuses (e.g., High‑Flyer gets +10% for aerial moves).
     * - Giant penalty: non‑grapple moves are 20% harder to land on a giant.
     * - Stamina penalties when below 15 or 30.
     * - Finisher bonus (+10%).
     * - Dirty Player bonus in No DQ matches (+15%).
     *
     * @param WrestlerInterface $attacker
     * @param WrestlerInterface $defender
     * @param Move $move
     * @param array $attackerStats Attacker's in‑match stats
     * @param string|null $stipulation Match stipulation (for No DQ bonus)
     * @return float Hit chance between 0.20 and 0.90
     */
    private function calculateHitChance(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, ?string $stipulation): float
    {
        $baseHitChance = $move->getBaseHitChance();
        $moveType      = $move->getType();

        // Determine which attributes to use based on move type
        $offenseStat = $defenseStat = 0;
        switch ($moveType) {
            case 'grapple':
                $offenseStat = $attacker->getStrength();
                $defenseStat = $defender->getStrength();
                $statDiff    = ($offenseStat - $defenseStat) / 200;
                break;
            case 'strike':
                $offenseStat = $attacker->getBrawlingAbility();
                $defenseStat = $defender->getToughness();
                $statDiff    = ($offenseStat - $defenseStat) / 200;
                break;
            case 'highFlying':
                $offenseStat = $attacker->getAerialAbility();
                $defenseStat = $defender->getAerialAbility();
                $statDiff    = ($offenseStat - $defenseStat) / 300;
                break;
            case 'submission':
                $offenseStat = $attacker->getTechnicalAbility();
                $defenseStat = $defender->getSubmissionDefense();
                $statDiff    = ($offenseStat - $defenseStat) / 400;
                break;
            default: $statDiff = 0;
        }

        $hitChance = $baseHitChance + $statDiff;

        // Archetype/trait bonuses
        if ($attackerStats['archetype'] === 'high-flyer' && $moveType === 'highFlying') {
            $hitChance += 0.10;
        }

        if (in_array('High-Flyer', $attackerStats['traits']) && $moveType === 'highFlying') {
            $hitChance += 0.10;
        }

        // Giants are harder to hit with non‑grapple moves (20% reduction)
        $isDefenderGiant = $this->isGiant($defender, null);
        if ($isDefenderGiant && $moveType !== 'grapple') {
            $hitChance *= 0.8;
        }

        // Finishers are easier to land (10% bonus)
        if ($move->getType() === 'finisher') {
            $hitChance += 0.10;
        }

        // Stamina penalties
        if ($attackerStats['stamina'] < 15) {
            $hitChance *= 0.75;
        } elseif ($attackerStats['stamina'] < 30) {
            $hitChance *= 0.9;
        }

        // Dirty Player bonus in No DQ matches
        if (StipulationRules::isNoDQ($stipulation) && $this->hasDirtyPlayerTrait($attacker, $attackerStats)) {
            $hitChance += 0.15;
        }

        // Clamp hit chance between 20% and 90%
        return max(0.20, min(0.90, $hitChance));
    }

    /**
     * Calculates the damage inflicted by a successful move.
     * Factors include:
     * - Base damage (random between min and max of the move)
     * - Attribute multiplier (offense stat vs defender toughness), with move‑specific denominators
     * - Giant damage reduction (non‑grapple moves: 40% reduction)
     * - Archetype/trait bonuses (Powerhouse, Brawler, High‑Flyer, Technician)
     * - Giant offensive bonus on grapple moves (+30%)
     * - High‑flyer penalty against high toughness (≥85) : 15% reduction
     * - Critical hits for Brawler trait (20% chance for 1.5x damage)
     * - Momentum bonus (up to +25%)
     * - Finisher bonus (1.5x)
     * - Stamina penalty (low stamina reduces damage)
     * - Brick Wall trait (10% damage reduction)
     * - Size advantage for grapple moves (weight difference >100 lbs gives ±25%)
     * - Dirty Player bonus in No DQ matches (+20%)
     *
     * @param WrestlerInterface $attacker
     * @param WrestlerInterface $defender
     * @param Move $move
     * @param array $attackerStats
     * @param array $defenderStats
     * @param string|null $stipulation
     * @return int Final damage (at least 5)
     */
    private function calculateDamage(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, array $defenderStats, ?string $stipulation): int
    {
        $baseDamage = mt_rand($move->getMinDamage(), $move->getMaxDamage());
        $moveType   = $move->getType();

        // Offense stat depends on move type
        $offenseStat = match ($moveType) {
            'grapple'    => $attacker->getStrength(),
            'strike'     => $attacker->getBrawlingAbility(),
            'highFlying' => $attacker->getAerialAbility(),
            'submission' => $attacker->getTechnicalAbility(),
            default      => 50,
        };
        $defenderToughness = $defender->getToughness();

        // Denominators control how much attribute differences matter. Lower denominator = more impact.
        $denominator = match ($moveType) {
            'submission' => 120,
            'strike'     => 200,
            default      => 65,
        };
        $attrMultiplier = 1 + ($offenseStat - $defenderToughness) / $denominator;
        $attrMultiplier = max(0.5, min(2.0, $attrMultiplier));

        $isDefenderGiant = $this->isGiant($defender, $defenderStats);
        // Giants take 35% less damage from non‑grapple moves
        if ($isDefenderGiant && $moveType !== 'grapple') {
            $attrMultiplier *= 0.65;
        }

        $bonusTotal = 1.0;

        // Archetype bonuses
        if ($attackerStats['archetype'] === 'powerhouse' && $moveType === 'grapple') {
            $bonusTotal += 0.20;
        }

        if ($attackerStats['archetype'] === 'brawler' && $moveType === 'strike') {
            $bonusTotal += 0.20;
        }

        if ($attackerStats['archetype'] === 'high-flyer' && $moveType === 'highFlying') {
            $bonusTotal += 0.20;
        }

        if ($attackerStats['archetype'] === 'technician' && $moveType === 'submission') {
            $bonusTotal += 0.05;
        }

        // Trait bonuses
        if (in_array('Powerhouse', $attackerStats['traits']) && $moveType === 'grapple') {
            $bonusTotal += 0.15;
        }

        if (in_array('Submission Specialist', $attackerStats['traits']) && $moveType === 'submission') {
            $bonusTotal += 0.10;
        }

        // Giant offensive bonus on grapple moves (+40%, +60% if facing another giant)
        $isAttackerGiant = $this->isGiant($attacker, $attackerStats);
        if ($isAttackerGiant && $moveType === 'grapple') {
            $bonusTotal += 0.40;
            if ($this->isGiant($defender, $defenderStats)) {
                $bonusTotal += 0.20;
            }

        }

        // 20% bonus damage for powerhouses vs Giants
        if ($isDefenderGiant && $attackerStats['archetype'] === 'powerhouse') {
            $bonusTotal += 0.20;
        }

        // Critical hit for Brawler trait (20% chance, 1.5x damage)
        $critMultiplier = 1.0;
        if (in_array('Brawler', $attackerStats['traits']) && $moveType === 'strike' && (mt_rand(0, 100) / 100) < 0.20) {
            $critMultiplier = 1.5;
        }

        // Momentum bonus: at 100 momentum, +25% damage
        $momentumBonus = 1 + ($attackerStats['momentum'] / 400);
        // Finisher bonus: +50% damage
        $finisherMultiplier = ($move->getType() === 'finisher') ? 1.5 : 1.0;
        // Stamina penalty: below 15 stamina → 25% reduction; below 30 → 10% reduction
        $staminaPenalty = ($attackerStats['stamina'] < 15) ? 0.75 : (($attackerStats['stamina'] < 30) ? 0.9 : 1.0);

        $finalDamage = $baseDamage * $attrMultiplier * $bonusTotal * $critMultiplier * $momentumBonus * $finisherMultiplier * $staminaPenalty;

        // Dirty Player bonus in No DQ matches
        if (StipulationRules::isNoDQ($stipulation) && $this->hasDirtyPlayerTrait($attacker, $attackerStats)) {
            $finalDamage *= 1.2;
        }

        // Defender traits that reduce damage
        if (in_array('Brick Wall', $defenderStats['traits'])) {
            $finalDamage *= 0.90;
        }

        if (in_array('Giant', $defenderStats['traits']) && $moveType === 'grapple') {
            $finalDamage *= 0.90;
        }

        // Size advantage for grapple moves (additional multiplier)
        $weightDiff = ($attacker->getWeight() ?? 0) - ($defender->getWeight() ?? 0);
        if ($moveType === 'grapple') {
            if ($weightDiff >= 100) {
                $finalDamage *= 1.25;
            } elseif ($weightDiff <= -100) {
                $finalDamage *= 0.75;
            }

        }

        // Ensure damage is at least 5 (prevent zero damage loops)
        return max(5, (int) round($finalDamage));
    }

    /**
     * Attempts a pinfall after a move that has a pin attempt chance.
     * Success chance depends on:
     * - Base pin chance of the move
     * - How low the defender's HP is (hpFactor)
     * - Defender's Toughness (higher toughness reduces chance)
     * - Attacker's momentum (higher momentum increases chance)
     *
     * @param WrestlerInterface $attacker
     * @param WrestlerInterface $defender
     * @param Move $move
     * @param array $attackerStats
     * @param array $defenderStats
     * @return bool True if pin is successful
     */
    private function attemptPin(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, array $defenderStats): bool
    {
        $baseChance = (float) $move->getPinAttemptChance();
        if ($baseChance <= 0) {
            return false;
        }

        $hpFactor        = 1 - ($defenderStats['hp'] / $defenderStats['max_hp']);
        $toughnessFactor = 1 - ($defender->getToughness() / 200);
        $momentumFactor  = 1 + ($attackerStats['momentum'] / 200);
        $effectiveChance = $baseChance * $hpFactor * $toughnessFactor * $momentumFactor;
        $effectiveChance = max(0.05, min(0.95, $effectiveChance));
        return (mt_rand(0, 10000) / 10000) < $effectiveChance;
    }

    /**
     * Attempts a submission after a move that has a submission attempt chance.
     * Success chance depends on:
     * - Base submission chance of the move
     * - Defender's HP (very low HP gives much higher chance, cubed factor)
     * - Defender's Submission Defense (higher defense reduces chance)
     * - Attacker's momentum
     *
     * @param WrestlerInterface $attacker
     * @param WrestlerInterface $defender
     * @param Move $move
     * @param array $attackerStats
     * @param array $defenderStats
     * @return bool True if submission is successful
     */
    private function attemptSubmission(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, array $defenderStats): bool
    {
        $baseChance = (float) $move->getSubmissionAttemptChance();
        if ($baseChance <= 0) {
            return false;
        }

        $hpRatio         = $defenderStats['hp'] / $defenderStats['max_hp'];
        $hpFactor        = (1 - $hpRatio) ** 3; // Very low HP gives huge boost
        $defenseFactor   = 1 - ($defender->getSubmissionDefense() / 200);
        $momentumFactor  = 1 + ($attackerStats['momentum'] / 200);
        $effectiveChance = $baseChance * $hpFactor * $defenseFactor * $momentumFactor;
        $effectiveChance = max(0.05, min(0.95, $effectiveChance));
        return (mt_rand(0, 10000) / 10000) < $effectiveChance;
    }

    /**
     * Chooses which move the wrestler will attempt this turn.
     * Logic:
     * - If momentum >= 80 and opponent HP <= finisher threshold, 50% chance to attempt a finisher.
     * - Otherwise, select a move type based on wrestler's tendencies (strike, grapple, submission, highfly)
     * - Then pick a random move of that type from the available moves (filtered by stamina).
     *
     * @param WrestlerInterface $wrestler
     * @param array $attackerStats
     * @param array $defenderStats
     * @return Move
     * @throws \Exception If wrestler has no moves
     */
    private function chooseMove(WrestlerInterface $wrestler, array $attackerStats, array $defenderStats): Move
    {
        $moves = $wrestler->getMoves();
        if ($moves->isEmpty()) {
            throw new \Exception("Wrestler {$wrestler->getName()} has no moves.");
        }

        // Filter moves based on weight limit (some moves cannot be used against much heavier opponents)
        $attackerWeight = $attackerStats['weight'] ?? 0;
        $defenderWeight = $defenderStats['weight'] ?? 0;
        $allowedMoves   = $moves->filter(function ($move) use ($defenderWeight, $attackerWeight) {
            $limit = $move->getWeightLimit();
            if ($limit === null) {
                return true;
            }

            if ($defenderWeight <= $limit) {
                return true;
            }

            $weightDiff = $defenderWeight - $attackerWeight;
            // Only block if defender is heavier by more than 75 lbs
            return ! ($weightDiff > 75);
        });

        // Fallback if no moves are allowed (should not happen)
        if ($allowedMoves->isEmpty()) {
            $allowedMoves = $moves;
        }

        // Can the wrestler attempt a finisher?
        $canFinisher = ($attackerStats['momentum'] >= 80 && $defenderStats['hp'] <= self::FINISHER_HP_THRESHOLD);

        // Filter moves that the wrestler has enough stamina for (from the allowed moves)
        $availableMoves = $allowedMoves->filter(function ($move) use ($attackerStats) {
            $cost = $move->getStaminaCost();
            if (in_array('Workhorse', $attackerStats['traits'])) {
                $cost = max(1, (int) round($cost * 0.8));
            }
            return $cost <= $attackerStats['stamina'];
        });

        // If no moves available due to low stamina, pick the cheapest basic move (strike or grapple)
        if ($availableMoves->isEmpty()) {
            $basic = $allowedMoves->filter(fn($move) => in_array($move->getType(), ['strike', 'grapple']));
            return $basic->isEmpty() ? $allowedMoves->first() : $basic->first();
        }

        // 50% chance to attempt a finisher if conditions are met
        if ($canFinisher && (mt_rand(0, 100) / 100) < 0.5) {
            $finishers = $availableMoves->filter(fn($move) => $move->getType() === 'finisher');
            if (! $finishers->isEmpty()) {
                $randomIndex = array_rand($finishers->toArray());
                return $finishers->get($randomIndex);
            }
        }

        // Determine move type based on wrestler's tendencies
        $tendencyStrike     = $wrestler->getTendencyStrike() ?? 25;
        $tendencyGrapple    = $wrestler->getTendencyGrapple() ?? 25;
        $tendencySubmission = $wrestler->getTendencySubmission() ?? 25;
        $tendencyHighfly    = $wrestler->getTendencyHighfly() ?? 25;
        $total              = $tendencyStrike + $tendencyGrapple + $tendencySubmission + $tendencyHighfly;
        if ($total == 0) {
            $total = 100;
        }

        $roll         = mt_rand(1, $total);
        $selectedType = '';
        if ($roll <= $tendencyStrike) {
            $selectedType = 'strike';
        } elseif ($roll <= $tendencyStrike + $tendencyGrapple) {
            $selectedType = 'grapple';
        } elseif ($roll <= $tendencyStrike + $tendencyGrapple + $tendencySubmission) {
            $selectedType = 'submission';
        } else {
            $selectedType = 'highFlying';
        }

        // Filter available moves by the selected type, fallback to all available if none found
        $filtered = $availableMoves->filter(fn($move) => $move->getType() === $selectedType);
        if ($filtered->isEmpty()) {
            $filtered = $availableMoves;
        }

        $randomIndex = array_rand($filtered->toArray());
        return $filtered->get($randomIndex);
    }

    /**
     * Checks for stipulation‑specific victory conditions after a move.
     * Handles pinfall, submission (including I Quit), and No DQ weapon strikes.
     *
     * @param WrestlerInterface $attacker
     * @param WrestlerInterface $defender
     * @param Move $move
     * @param array $attackerStats
     * @param array $defenderStats
     * @param string|null $stipulation
     * @return string|null Victory method if achieved, null otherwise
     */
    private function checkVictory(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, array $defenderStats, ?string $stipulation): ?string
    {
        $allowed   = StipulationRules::getAllowedVictoryMethods($stipulation);
        $pinChance = $move->getPinAttemptChance();
        $subChance = $move->getSubmissionAttemptChance();

        // Pinfall victory (allowed in most stipulations except I Quit and Last Man Standing)
        if (in_array('pinfall', $allowed) && $pinChance > 0) {
            if ($this->attemptPin($attacker, $defender, $move, $attackerStats, $defenderStats)) {
                return 'Pinfall';
            }
        }

        // Submission victory – special handling for I Quit (no HP threshold)
        if (in_array('submission', $allowed) && $subChance > 0) {
            if ($stipulation === StipulationRules::I_QUIT) {
                // I Quit: submission can be attempted at any time
                if ($this->attemptSubmission($attacker, $defender, $move, $attackerStats, $defenderStats)) {
                    return 'I Quit';
                }
            } else {
                $maxHp = $defenderStats['max_hp'];
                if ($defenderStats['hp'] <= $maxHp * self::SUBMISSION_HP_THRESHOLD) {
                    if ($this->attemptSubmission($attacker, $defender, $move, $attackerStats, $defenderStats)) {
                        return 'Submission';
                    }
                }
            }
        }

        // No DQ: weapon strike victory (if move type is strike and HP is very low)
        if (StipulationRules::isNoDQ($stipulation) && in_array('weapon_strike', $allowed) && $move->getType() === 'strike') {
            if ($defenderStats['hp'] <= 30) {
                return 'Weapon Strike';
            }
        }

        return null;
    }

    /**
     * Determines the victory method when a knockout occurs (HP reaches 0).
     * Takes stipulation into account.
     *
     * @param Move $move
     * @param string|null $stipulation
     * @return string
     */
    private function determineVictoryMethod(Move $move, ?string $stipulation): string
    {
        if (StipulationRules::isNoDQ($stipulation) && $move->getType() === 'strike') {
            return 'Weapon Strike';
        }
        if ($move->getType() === 'finisher') {
            return 'Finisher';
        }

        if ($move->getPinAttemptChance() > 0) {
            return 'Pinfall';
        }

        if ($move->getSubmissionAttemptChance() > 0) {
            return 'Submission';
        }

        return 'Pinfall';
    }

    /**
     * Converts a win percentage (0-100) into a moneyline odd.
     * Uses a vigorish (house edge) to make the odds slightly in favour of the house.
     *
     * @param float $percent Win probability (0 to 100)
     * @return int Moneyline odd (e.g., +150, -200)
     */
    private function calculateMoneyline(float $percent): int
    {
        if ($percent <= 0.0) {
            return 9999;
        }
        // Infinite underdog
        if ($percent >= 100.0) {
            return -9999;
        }
        // Overwhelming favourite

        if ($percent >= 50) {
            // Favourite: negative moneyline
            $true_odds       = -(($percent / (100 - $percent)) * 100);
            $risk            = abs($true_odds);
            $payout_with_vig = 100 * (1 - $this->vig);
            return (int) round(-($risk / $payout_with_vig) * 100);
        } else {
            // Underdog: positive moneyline
            $true_odds       = ((100 - $percent) / $percent) * 100;
            $payout_with_vig = $true_odds * (1 - $this->vig);
            return (int) round($payout_with_vig);
        }
    }
}
