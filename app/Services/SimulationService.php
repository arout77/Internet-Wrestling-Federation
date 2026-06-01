<?php
namespace App\Services;

use App\Entities\Move;
use App\Entities\WrestlerInterface;
use Core\Cache;
use Doctrine\ORM\EntityManager;

class SimulationService
{
    private float $vig                    = 0.10;
    private const FINISHER_HP_THRESHOLD   = 300;
    private const SUBMISSION_HP_THRESHOLD = 0.20; // 20% max HP required

    public function __construct(protected EntityManager $em, protected Cache $cache)
    {
    }

    public function generateOdds(WrestlerInterface $wrestler1, WrestlerInterface $wrestler2, int $numSims = 1000): array
    {
        $cacheKey   = "odds_w{$wrestler1->getSimulationId()}_w{$wrestler2->getSimulationId()}_s{$numSims}";
        $cachedOdds = $this->cache->get($cacheKey);
        if ($cachedOdds) {
            return $cachedOdds;
        }

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

        $turn     = 0;
        $maxTurns = 200;

        while ($stats['w1']['hp'] > 0 && $stats['w2']['hp'] > 0 && $turn < $maxTurns) {
            $turn++;
            if ($generateLog) {
                $log[] = ['type' => 'turn', 'data' => ['number' => $turn]];
            }

            $initiative = $this->determineInitiative($stats['w1'], $stats['w2']);
            if ($initiative['first'] === 'w1') {
                $attackerKey = 'w1';
                $defenderKey = 'w2';
            } else {
                $attackerKey = 'w2';
                $defenderKey = 'w1';
            }

            $attacker = ($attackerKey === 'w1') ? $w1 : $w2;
            $defender = ($defenderKey === 'w1') ? $w1 : $w2;

            if ($stats[$attackerKey]['stunned_turns'] > 0) {
                if ($generateLog) {
                    $log[] = ['type' => 'stunned', 'data' => ['name' => $attacker->getName()]];
                }
                $stats[$attackerKey]['stunned_turns']--;
                continue;
            }

            $move = $this->chooseMove($attacker, $stats[$attackerKey], $stats[$defenderKey]);

            $staminaCost = $move->getStaminaCost();
            if (in_array('Workhorse', $stats[$attackerKey]['traits'])) {
                $staminaCost = max(1, (int) round($staminaCost * 0.8));
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
                continue;
            }

            $stats[$attackerKey]['stamina'] -= $staminaCost;

            if ($this->attemptReversal($defender, $attacker, $move, $stats[$defenderKey])) {
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
                continue;
            }

            $hitChance = $this->calculateHitChance($attacker, $defender, $move, $stats[$attackerKey]);
            $roll      = mt_rand(0, 10000) / 10000;

            if ($roll < $hitChance) {
                $damage                           = $this->calculateDamage($attacker, $defender, $move, $stats[$attackerKey], $stats[$defenderKey]);
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

                if ($stats[$defenderKey]['hp'] <= 0) {
                    $victory_method = "Pinfall";
                    if ($move->getType() === 'finisher') {
                        $victory_method = "Finisher";
                    } elseif ($move->getPinAttemptChance() > 0) {
                        $victory_method = "Pinfall";
                    } elseif ($move->getSubmissionAttemptChance() > 0) {
                        $victory_method = "Submission";
                    }

                    $log[] = ['type' => 'end', 'data' => ['winner_id' => $attackerKey, 'winner_name' => $attacker->getName(), 'victory_method' => $victory_method]];
                    break;
                }

                $pinChance = $move->getPinAttemptChance();
                $subChance = $move->getSubmissionAttemptChance();
                $attempted = false;

                if ($pinChance > 0 && ! $attempted) {
                    if ($this->attemptPin($attacker, $defender, $move, $stats[$attackerKey], $stats[$defenderKey])) {
                        $log[] = ['type' => 'end', 'data' => ['winner_id' => $attackerKey, 'winner_name' => $attacker->getName(), 'victory_method' => 'Pinfall']];
                        break;
                    } else {
                        $stats[$attackerKey]['momentum'] = max(0, $stats[$attackerKey]['momentum'] - 10);
                        $stats[$defenderKey]['momentum'] = min(100, $stats[$defenderKey]['momentum'] + 10);
                        if ($generateLog) {
                            $log[] = ['type' => 'pin_failed', 'data' => ['attacker_name' => $attacker->getName(), 'defender_name' => $defender->getName()]];
                        }

                        $attempted = true;
                    }
                }

                if ($subChance > 0 && ! $attempted) {
                    $maxHp = $stats[$defenderKey]['max_hp'];
                    if ($stats[$defenderKey]['hp'] <= $maxHp * self::SUBMISSION_HP_THRESHOLD) {
                        if ($this->attemptSubmission($attacker, $defender, $move, $stats[$attackerKey], $stats[$defenderKey])) {
                            $log[] = ['type' => 'end', 'data' => ['winner_id' => $attackerKey, 'winner_name' => $attacker->getName(), 'victory_method' => 'Submission']];
                            break;
                        } else {
                            $stats[$attackerKey]['momentum'] = max(0, $stats[$attackerKey]['momentum'] - 10);
                            $stats[$defenderKey]['momentum'] = min(100, $stats[$defenderKey]['momentum'] + 10);
                            if ($generateLog) {
                                $log[] = ['type' => 'submission_failed', 'data' => ['attacker_name' => $attacker->getName(), 'defender_name' => $defender->getName()]];
                            }

                            $attempted = true;
                        }
                    }
                }
            } else {
                if ($generateLog) {
                    $log[] = ['type' => 'miss', 'data' => ['attacker_name' => $attacker->getName(), 'move_name' => $move->getMoveName()]];
                }

            }

            $stats[$attackerKey]['stamina'] = min(100, $stats[$attackerKey]['stamina'] + $stats[$attackerKey]['stamina_recovery']);
            $stats[$defenderKey]['stamina'] = min(100, $stats[$defenderKey]['stamina'] + $stats[$defenderKey]['stamina_recovery']);
            if ($generateLog) {
                $log[] = ['type' => 'update', 'data' => ['w1' => $stats['w1'], 'w2' => $stats['w2']]];
            }

        }

        if ($turn >= $maxTurns && $stats['w1']['hp'] > 0 && $stats['w2']['hp'] > 0) {
            $log[] = ['type' => 'end', 'data' => ['winner_id' => null, 'winner_name' => null, 'victory_method' => "Time Limit Draw"]];
        }

        return $log;
    }

    private function initWrestlerStats(WrestlerInterface $wrestler): array
    {
        $baseHp = $wrestler->getBaseHp();
        $maxHp  = $baseHp + ($baseHp * ($wrestler->getToughness() / 200));
        return [
            'max_hp'           => $maxHp, 'hp'                                         => $maxHp, 'stamina' => 100, 'momentum' => 0,
            'stamina_recovery' => $wrestler->getStaminaRecoveryRate(), 'stunned_turns' => 0,
            'traits'           => $wrestler->getTraitNames(), 'archetype'              => method_exists($wrestler, 'getArchetype') ? $wrestler->getArchetype() : null,
        ];
    }

    private function determineInitiative(array $statsW1, array $statsW2): array
    {
        $scoreW1 = mt_rand(1, 100) + ($statsW1['momentum'] / 2) + ($statsW1['stamina'] / 4);
        $scoreW2 = mt_rand(1, 100) + ($statsW2['momentum'] / 2) + ($statsW2['stamina'] / 4);
        return ['first' => $scoreW1 >= $scoreW2 ? 'w1' : 'w2'];
    }

    private function attemptReversal(WrestlerInterface $defender, WrestlerInterface $attacker, Move $move, array $defenderStats): bool
    {
        $moveType       = $move->getType();
        $baseReversal   = $defender->getReversalAbility();
        $technicalBonus = $defender->getTechnicalAbility() / 1000;
        $reversalChance = ($baseReversal / 600) + $technicalBonus;

        if (in_array('Giant', $defenderStats['traits']) && $moveType === 'grapple') {
            $reversalChance *= 1.3;
        }

        if (in_array('Technician', $defenderStats['traits'])) {
            $reversalChance += 0.00;
        }
        // removed
        if ($defenderStats['archetype'] === 'technician') {
            $reversalChance += 0.02;
        }
        // reduced
        if ($defenderStats['archetype'] === 'powerhouse' && $moveType === 'grapple') {
            $reversalChance += 0.20;
        }
        // increased
        $reversalChance = min(0.35, $reversalChance);
        return (mt_rand(0, 10000) / 10000) < $reversalChance;
    }

    private function calculateHitChance(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats): float
    {
        $baseHitChance = $move->getBaseHitChance();
        $moveType      = $move->getType();
        $offenseStat   = $defenseStat   = 0;
        switch ($moveType) {
            case 'grapple':$offenseStat = $attacker->getStrength();
                $defenseStat                = $defender->getStrength();
                $statDiff                   = ($offenseStat - $defenseStat) / 200;
                break;
            case 'strike':$offenseStat = $attacker->getBrawlingAbility();
                $defenseStat               = $defender->getToughness();
                $statDiff                  = ($offenseStat - $defenseStat) / 300;
                break;
            case 'highFlying':$offenseStat = $attacker->getAerialAbility();
                $defenseStat                   = $defender->getAerialAbility();
                $statDiff                      = ($offenseStat - $defenseStat) / 300;
                break;
            case 'submission':$offenseStat = $attacker->getTechnicalAbility();
                $defenseStat                   = $defender->getSubmissionDefense();
                $statDiff                      = ($offenseStat - $defenseStat) / 500;
                break;
            default: $statDiff = 0;
        }
        $hitChance = $baseHitChance + $statDiff;
        if ($attackerStats['archetype'] === 'high-flyer' && $moveType === 'highFlying') {
            $hitChance += 0.10;
        }

        if (in_array('High-Flyer', $attackerStats['traits']) && $moveType === 'highFlying') {
            $hitChance += 0.10;
        }

        if ($move->getType() === 'finisher') {
            $hitChance += 0.10;
        }

        if ($attackerStats['stamina'] < 15) {
            $hitChance *= 0.75;
        } elseif ($attackerStats['stamina'] < 30) {
            $hitChance *= 0.9;
        }

        return max(0.20, min(0.90, $hitChance));
    }

    private function calculateDamage(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, array $defenderStats): int
    {
        $baseDamage  = mt_rand($move->getMinDamage(), $move->getMaxDamage());
        $moveType    = $move->getType();
        $offenseStat = match ($moveType) {
            'grapple'    => $attacker->getStrength(),
            'strike'     => $attacker->getBrawlingAbility(),
            'highFlying' => $attacker->getAerialAbility(),
            'submission' => $attacker->getTechnicalAbility(),
            default      => 50,
        };
        $defenderToughness = $defender->getToughness();
        $denominator       = ($moveType === 'submission') ? 150 : 90; // grapples denominator 90 (increased strength scaling)
        $attrMultiplier    = 1 + ($offenseStat - $defenderToughness) / $denominator;
        $attrMultiplier    = max(0.5, min(2.0, $attrMultiplier));

        $bonusTotal = 1.0;
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
        // reduced
        if (in_array('Powerhouse', $attackerStats['traits']) && $moveType === 'grapple') {
            $bonusTotal += 0.15;
        }

        if (in_array('Submission Specialist', $attackerStats['traits']) && $moveType === 'submission') {
            $bonusTotal += 0.10;
        }

        $critMultiplier = 1.0;
        if (in_array('Brawler', $attackerStats['traits']) && $moveType === 'strike' && (mt_rand(0, 100) / 100) < 0.20) {
            $critMultiplier = 1.5;
        }

        $momentumBonus      = 1 + ($attackerStats['momentum'] / 400);
        $finisherMultiplier = ($move->getType() === 'finisher') ? 1.5 : 1.0;
        $staminaPenalty     = ($attackerStats['stamina'] < 15) ? 0.75 : (($attackerStats['stamina'] < 30) ? 0.9 : 1.0);

        $finalDamage = $baseDamage * $attrMultiplier * $bonusTotal * $critMultiplier * $momentumBonus * $finisherMultiplier * $staminaPenalty;
        if (in_array('Brick Wall', $defenderStats['traits'])) {
            $finalDamage *= 0.90;
        }

        if (in_array('Giant', $defenderStats['traits']) && $moveType === 'grapple') {
            $finalDamage *= 0.90;
        }

        return max(5, (int) round($finalDamage));
    }

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

    private function attemptSubmission(WrestlerInterface $attacker, WrestlerInterface $defender, Move $move, array $attackerStats, array $defenderStats): bool
    {
        $baseChance = (float) $move->getSubmissionAttemptChance();
        if ($baseChance <= 0) {
            return false;
        }

        $hpRatio         = $defenderStats['hp'] / $defenderStats['max_hp'];
        $hpFactor        = (1 - $hpRatio) ** 3;
        $defenseFactor   = 1 - ($defender->getSubmissionDefense() / 200);
        $momentumFactor  = 1 + ($attackerStats['momentum'] / 200);
        $effectiveChance = $baseChance * $hpFactor * $defenseFactor * $momentumFactor;
        $effectiveChance = max(0.05, min(0.95, $effectiveChance));
        return (mt_rand(0, 10000) / 10000) < $effectiveChance;
    }

    private function chooseMove(WrestlerInterface $wrestler, array $attackerStats, array $defenderStats): Move
    {
        $moves = $wrestler->getMoves();
        if ($moves->isEmpty()) {
            throw new \Exception("Wrestler {$wrestler->getName()} has no moves.");
        }

        $canFinisher    = ($attackerStats['momentum'] >= 80 && $defenderStats['hp'] <= self::FINISHER_HP_THRESHOLD);
        $availableMoves = $moves->filter(function ($move) use ($attackerStats) {
            $cost = $move->getStaminaCost();
            if (in_array('Workhorse', $attackerStats['traits'])) {
                $cost = max(1, (int) round($cost * 0.8));
            }

            return $cost <= $attackerStats['stamina'];
        });

        if ($availableMoves->isEmpty()) {
            $basic = $moves->filter(fn($move) => in_array($move->getType(), ['strike', 'grapple']));
            return $basic->isEmpty() ? $moves->first() : $basic->first();
        }

        if ($canFinisher && (mt_rand(0, 100) / 100) < 0.5) {
            $finishers = $availableMoves->filter(fn($move) => $move->getType() === 'finisher');
            if (! $finishers->isEmpty()) {
                $randomIndex = array_rand($finishers->toArray());
                return $finishers->get($randomIndex);
            }
        }

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

        $filtered = $availableMoves->filter(fn($move) => $move->getType() === $selectedType);
        if ($filtered->isEmpty()) {
            $filtered = $availableMoves;
        }

        $randomIndex = array_rand($filtered->toArray());
        return $filtered->get($randomIndex);
    }
}
