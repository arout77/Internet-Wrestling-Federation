<?php
namespace App\Services;

use App\Entities\Move;
use App\Entities\WrestlerInterface;
use Core\Cache;
use Doctrine\ORM\EntityManager;

class SimulationService
{
    private float $vig = 0.10;

    public function __construct(protected EntityManager $em, protected Cache $cache)
    {
    }

    /**
     * Runs headless simulations to generate odds.
     */
    public function generateOdds(WrestlerInterface $wrestler1, WrestlerInterface $wrestler2, int $numSims = 1000): array
    {
        $cacheKey = "odds_w{$wrestler1->getSimulationId()}_w{$wrestler2->getSimulationId()}_s{$numSims}";
        // $cachedOdds = $this->cache->get($cacheKey);
        // if ($cachedOdds) {
        //     return $cachedOdds;
        // }

        $w1_wins = 0;
        $w2_wins = 0;
        $draws   = 0;

        for ($i = 0; $i < $numSims; $i++) {
            $log      = $this->runVisualMatch($wrestler1, $wrestler2, false);
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

        $w1_win_percent = $numSims > 0 ? round(($w1_wins / $numSims) * 100, 1) : 0;
        $w2_win_percent = $numSims > 0 ? round(($w2_wins / $numSims) * 100, 1) : 0;
        $draw_percent   = $numSims > 0 ? round(($draws / $numSims) * 100, 1) : 0;

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
            'draw_percent'   => $draw_percent,
            'total_sims'     => $numSims,
        ];

        $this->cache->put($cacheKey, $results, 846000);
        return $results;
    }

    /**
     * Calculates American moneyline odds with 10% vig.
     */
    private function calculateMoneyline(float $percent): int
    {
        if ($percent <= 0.0) {
            return 9999;
        }

        if ($percent >= 100.0) {
            return -9999;
        }

        if ($percent >= 50) {
            $true_odds       = -(($percent / (100 - $percent)) * 100);
            $risk            = abs($true_odds);
            $payout_with_vig = 100 * (1 - $this->vig);
            return (int) round(-($risk / $payout_with_vig) * 100);
        } else {
            $true_odds       = ((100 - $percent) / $percent) * 100;
            $payout_with_vig = $true_odds * (1 - $this->vig);
            return (int) round($payout_with_vig);
        }
    }

    /**
     * Runs a single visual match and returns the log.
     */
    public function runVisualMatch(WrestlerInterface $wrestler1, WrestlerInterface $wrestler2, bool $generateLog = true): array
    {
        $log = [];
        $w1  = $wrestler1;
        $w2  = $wrestler2;

        $stats = [
            'w1' => $this->initWrestlerStats($w1),
            'w2' => $this->initWrestlerStats($w2),
        ];

        if ($generateLog) {
            $log[] = [
                'type' => 'start',
                'data' => [
                    'w1' => ['name' => $w1->getName(), 'image' => $w1->getImage(), 'max_hp' => $stats['w1']['max_hp']],
                    'w2' => ['name' => $w2->getName(), 'image' => $w2->getImage(), 'max_hp' => $stats['w2']['max_hp']],
                ],
            ];
        }

        $attackerKey = 'w1';
        $defenderKey = 'w2';
        $turn        = 0;
        $maxTurns    = 200;

        while ($stats['w1']['hp'] > 0 && $stats['w2']['hp'] > 0 && $turn < $maxTurns) {
            $turn++;
            if ($generateLog) {
                $log[] = ['type' => 'turn', 'data' => ['number' => $turn]];
            }

            $attacker = ($attackerKey === 'w1') ? $w1 : $w2;
            $defender = ($defenderKey === 'w1') ? $w1 : $w2;

            // Stun check
            if ($stats[$attackerKey]['stunned_turns'] > 0) {
                if ($generateLog) {
                    $log[] = ['type' => 'stunned', 'data' => ['name' => $attacker->getName()]];
                }
                $stats[$attackerKey]['stunned_turns']--;
                [$attackerKey, $defenderKey] = [$defenderKey, $attackerKey];
                continue;
            }

            // Choose move
            $move = $this->chooseMove($attacker, $stats[$attackerKey]);

            // Stamina check (with Workhorse trait adjustment)
            $staminaCost = $move->getStaminaCost();
            if (in_array('Workhorse', $stats[$attackerKey]['traits'])) {
                $staminaCost = max(1, (int) round($staminaCost * 0.8)); // 20% reduction
            }

            if ($stats[$attackerKey]['stamina'] < $staminaCost) {
                if ($generateLog) {
                    $log[] = ['type' => 'exhausted', 'data' => ['name' => $attacker->getName(), 'move_name' => $move->getMoveName()]];
                }
                $stats[$attackerKey]['stamina'] = min(100, $stats[$attackerKey]['stamina'] + $stats[$attackerKey]['stamina_recovery'] * 2);
                $stats[$defenderKey]['stamina'] = min(100, $stats[$defenderKey]['stamina'] + $stats[$defenderKey]['stamina_recovery']);
                if ($generateLog) {
                    $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
                }
                [$attackerKey, $defenderKey] = [$defenderKey, $attackerKey];
                continue;
            }

            $stats[$attackerKey]['stamina'] -= $staminaCost;

            // Attempt reversal
            if ($this->attemptReversal($defender, $attacker, $move)) {
                if ($generateLog) {
                    $log[] = [
                        'type' => 'reversal',
                        'data' => ['defender_name' => $defender->getName(), 'move_name' => $move->getMoveName()],
                    ];
                }
                $stats[$defenderKey]['stamina']  = max(0, $stats[$defenderKey]['stamina'] - 5);
                $stats[$defenderKey]['momentum'] = min(100, $stats[$defenderKey]['momentum'] + 15);
                if ($generateLog) {
                    $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
                }
                [$attackerKey, $defenderKey] = [$defenderKey, $attackerKey];
                continue;
            }

            // Hit check
            if ($this->doesMoveHit($attacker, $defender, $move)) {
                $damage                           = $this->calculateDamage($attacker, $defender, $move, $stats[$attackerKey]);
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
            } else {
                if ($generateLog) {
                    $log[] = [
                        'type' => 'miss',
                        'data' => ['attacker_name' => $attacker->getName(), 'move_name' => $move->getMoveName()],
                    ];
                }
            }

            // Stamina regeneration
            $stats[$attackerKey]['stamina'] = min(100, $stats[$attackerKey]['stamina'] + $stats[$attackerKey]['stamina_recovery']);
            $stats[$defenderKey]['stamina'] = min(100, $stats[$defenderKey]['stamina'] + $stats[$defenderKey]['stamina_recovery']);

            if ($generateLog) {
                $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
            }

            // Check for end of match
            if ($stats[$defenderKey]['hp'] <= 0) {
                $victory_method = "Pinfall";
                if ($move->getType() === 'finisher') {
                    $victory_method = "Finisher";
                } elseif ($move->getPinAttemptChance() > 0 && (mt_rand(0, 100) / 100) < $move->getPinAttemptChance()) {
                    $victory_method = "Pinfall";
                } elseif ($move->getSubmissionAttemptChance() > 0 && (mt_rand(0, 100) / 100) < $move->getSubmissionAttemptChance()) {
                    $victory_method = "Submission";
                }

                $log[] = [
                    'type' => 'end',
                    'data' => [
                        'winner_id'      => $attackerKey,
                        'winner_name'    => $attacker->getName(),
                        'victory_method' => $victory_method,
                    ],
                ];
                break;
            }

            // Swap attacker and defender for next turn
            [$attackerKey, $defenderKey] = [$defenderKey, $attackerKey];
        }

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

    /**
     * Initializes wrestler stats for a match.
     */
    private function initWrestlerStats(WrestlerInterface $wrestler): array
    {
        $baseHp = $wrestler->getBaseHp();
        $maxHp  = $baseHp + ($baseHp * ($wrestler->getToughness() / 200));
        return [
            'max_hp'           => $maxHp,
            'hp'               => $maxHp,
            'stamina'          => 100,
            'momentum'         => 0,
            'stamina_recovery' => $wrestler->getStaminaRecoveryRate(),
            'stunned_turns'    => 0,
            'traits'           => $wrestler->getTraitNames(),
        ];
    }

    /**
     * Attempts a reversal – adjusted for balance.
     */
    private function attemptReversal(WrestlerInterface $defender, WrestlerInterface $attacker, Move $move): bool
    {
        $moveType     = $move->getType();
        $baseReversal = $defender->getReversalAbility();

        // Base chance increased slightly (was /600, now /500)
        if ($moveType === 'strike') {
            $statBonus = $defender->getBrawlingAbility() / 800;
        } elseif ($moveType === 'grapple') {
            $statBonus = ($defender->getStrength() + $defender->getTechnicalAbility()) / 1600;
        } elseif ($moveType === 'highFlying') {
            $statBonus = $defender->getAerialAbility() / 800;
        } elseif ($moveType === 'submission') {
            $statBonus = $defender->getTechnicalAbility() / 600;
        } else {
            $statBonus = 0;
        }

        $reversalChance = ($baseReversal / 500) + $statBonus;

        // Trait bonuses
        if (in_array('Giant', $defender->getTraitNames()) && $moveType === 'grapple') {
            $reversalChance *= 1.2;
        }
        if (in_array('Technician', $defender->getTraitNames())) {
            $reversalChance *= 1.1;
        }
        // Archetype bonus (now 25% for Technician)
        if (method_exists($defender, 'getArchetype') && $defender->getArchetype() === 'technician') {
            $reversalChance *= 1.25;
        }

        $reversalChance = min(0.30, $reversalChance); // cap at 30%
        return (mt_rand(0, 10000) / 10000) < $reversalChance;
    }

    /**
     * Determines if a move hits.
     */
    private function doesMoveHit(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move): bool
    {
        $baseHitChance = $move->getBaseHitChance();
        $statName      = $move->getStat();
        $statVal       = match ($statName) {
            'strength'         => $attacker->getStrength(),
            'technicalAbility' => $attacker->getTechnicalAbility(),
            'brawlingAbility'  => $attacker->getBrawlingAbility(),
            'aerialAbility'    => $attacker->getAerialAbility(),
            default            => 50,
        };

        // Diminishing returns above 85
        $effectiveStat = min(85, $statVal) + max(0, $statVal - 85) * 0.5;
        $statBonus     = $effectiveStat / 900;
        $hitChance     = $baseHitChance + $statBonus;

        if (in_array('High-Flyer', $attacker->getTraitNames()) && $move->getType() === 'highFlying') {
            $hitChance *= 1.1;
        }

        return (mt_rand(0, 10000) / 10000) < $hitChance;
    }

    /**
     * Calculates damage – fully balanced with additive bonuses and caps.
     */
    private function calculateDamage(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats): int
    {
        $baseDamage = mt_rand($move->getMinDamage(), $move->getMaxDamage());
        $moveType   = $move->getType();
        $strength   = $attacker->getStrength();
        $brawling   = $attacker->getBrawlingAbility();
        $technical  = $attacker->getTechnicalAbility();

        // --- BASE MULTIPLIER BY MOVE TYPE ---
        if ($moveType === 'grapple') {
            // Strength scaling reduced: max +66% at 100 (was +100%)
            $damage = $baseDamage * (1 + ($strength / 150));
        } elseif ($moveType === 'strike') {
            $damage = $baseDamage * (1 + ($brawling / 150));
        } elseif ($moveType === 'highFlying') {
            $aerial = $attacker->getAerialAbility();
            $damage = $baseDamage * (1 + ($aerial / 150));
        } elseif ($moveType === 'submission') {
            // Technical scaling increased for submissions
            $damage = $baseDamage * (1 + ($technical / 100));
        } else {
            $damage = $baseDamage;
        }

        // --- ADDITIVE BONUSES (instead of multiplicative stacking) ---
        $bonus = 1.0;
        // Powerhouse archetype
        if (method_exists($attacker, 'getArchetype') && $attacker->getArchetype() === 'powerhouse' && $moveType === 'grapple') {
            $bonus += 0.15; // 15% bonus
        }
        // Powerhouse trait
        if (in_array('Powerhouse', $attackerStats['traits']) && $moveType === 'grapple') {
            $bonus += 0.15; // 15% bonus
        }
        // Brawler trait (strike crit)
        $crit = 1.0;
        if (in_array('Brawler', $attackerStats['traits']) && $moveType === 'strike') {
            if ((mt_rand(0, 100) / 100) < 0.20) { // 20% crit chance
                $crit = 1.5;
            }
        }
        // Finisher bonus (separate, can be multiplicative)
        $finisherBonus = 1.0;
        if ($move->getType() === 'finisher') {
            $finisherBonus = 1.5; // 50% extra
        }

        // Apply bonuses
        $damage = $damage * $bonus * $crit * $finisherBonus;

        // --- Strength difference bonus (capped at 30%) ---
        $strengthDiff = max(0, $strength - $defender->getStrength());
        $diffBonus    = 1 + min(0.30, $strengthDiff / 200);
        $damage       *= $diffBonus;

        // --- Momentum bonus (reduced) ---
        $damage = $damage * (1 + ($attackerStats['momentum'] / 400));

        // --- Defender toughness reduction (capped at 50% reduction) ---
        $toughness       = min(80, $defender->getToughness());
        $damageReduction = 1 - ($toughness / 250);
        $damageReduction = max(0.5, $damageReduction);
        $finalDamage     = $damage * $damageReduction;

        if (in_array('Brick Wall', $defender->getTraitNames())) {
            $finalDamage *= 0.85;
        }

        // Minimum damage
        $minDamage = max(5, $baseDamage * 0.1);
        return max($minDamage, (int) round($finalDamage));
    }

    /**
     * Selects a move based on the wrestler's tendencies.
     */
    private function chooseMove(WrestlerInterface $wrestler, array $stats): Move
    {
        $moves = $wrestler->getMoves();
        if ($moves->isEmpty()) {
            throw new \Exception("Wrestler " . $wrestler->getName() . " has no moves.");
        }

        $availableMoves = $moves->filter(fn($move) => $move->getStaminaCost() <= $stats['stamina']);

        if ($availableMoves->isEmpty()) {
            $basic = $moves->filter(fn($move) => in_array($move->getType(), ['strike', 'grapple']));
            return $basic->isEmpty() ? $moves->first() : $basic->first();
        }

        // Use tendencies (stored in entity)
        $tendencyStrike     = $wrestler->getTendencyStrike() ?? 25;
        $tendencyGrapple    = $wrestler->getTendencyGrapple() ?? 25;
        $tendencySubmission = $wrestler->getTendencySubmission() ?? 25;
        $tendencyHighfly    = $wrestler->getTendencyHighfly() ?? 25;

        $total = $tendencyStrike + $tendencyGrapple + $tendencySubmission + $tendencyHighfly;
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

        $filtered = $availableMoves->filter(fn($move) => $move->getType() === $selectedType);
        if ($filtered->isEmpty()) {
            $filtered = $availableMoves;
        }

        $randomIndex = array_rand($filtered->toArray());
        return $filtered->get($randomIndex);
    }
}
