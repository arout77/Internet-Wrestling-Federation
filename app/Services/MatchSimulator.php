<?php
namespace App\Services\Simulation;

use App\Models\Wrestler;

class MatchSimulator
{
    private const SCALING_CONSTANT = 0.05; // 'k' factor controls probability curves

    public function simulate(Wrestler $wrestlerA, Wrestler $wrestlerB): array
    {
        $state        = new MatchState($wrestlerA, $wrestlerB);
        $state->log[] = "Match Start: {$wrestlerA->name} vs. {$wrestlerB->name}!";

        // Determine initial attacker based on an initiative check (e.g., speed/agility)
        $attacker = ($wrestlerA->agility + rand(-10, 10) >= $wrestlerB->agility + rand(-10, 10))
            ? $wrestlerA
            : $wrestlerB;
        $defender = ($attacker->id === $wrestlerA->id) ? $wrestlerB : $wrestlerA;

        // Loop until a finish condition is reached (or hard limit to prevent infinite loops)
        while ($state->currentTurn <= 50) {
            $state->log[] = "--- Turn {$state->currentTurn} ---";

            // Calculate current phase modifier (Early, Mid, Late game scaling)
            $phaseModifier = $this->getPhaseModifier($state->currentTurn);

            // Calculate success probability using our logistic distribution formula
            $successProbability = $this->calculateSuccessOdds($attacker, $defender, $state, $phaseModifier);
            $roll               = rand(1, 100) / 100;

            if ($roll <= $successProbability) {
                                                                                              // MOVE SUCCESSFUL
                $damageDealt                    = rand(5, 12) + ($attacker->tier_rating * 3); // Tier advantage weight
                $state->damage[$defender->id]  += $damageDealt;
                $state->stamina[$attacker->id]  = max(10, $state->stamina[$attacker->id] - rand(1, 3));

                $state->adjustMomentum(15, $attacker->id);
                $state->log[] = "{$attacker->name} successfully executes a move on {$defender->name}! Dealt {$damageDealt} damage.";

                // Check for match finish if damage is substantial
                if ($state->damage[$defender->id] > 40) {
                    if ($this->checkPinfallThreshold($defender, $state)) {
                        $state->log[] = "1... 2... 3! It's over! {$attacker->name} wins by pinfall!";
                        break;
                    } else {
                        $state->log[] = "{$defender->name} barely kicks out!";
                        $state->adjustMomentum(-10, $attacker->id); // Momentum penalty for a near fall
                    }
                }
            } else {
                // MOVE REVERSED / FAILED (Counter-wrestling flow)
                $state->log[] = "{$defender->name} counters the attack and takes control!";
                $state->adjustMomentum(20, $defender->id);

                // Swap roles
                $temp     = $attacker;
                $attacker = $defender;
                $defender = $temp;
            }

            // Passive stamina decay from match fatigue
            $state->stamina[$attacker->id] = max(5, $state->stamina[$attacker->id] - 1);
            $state->stamina[$defender->id] = max(5, $state->stamina[$defender->id] - 1);

            $state->currentTurn++;
        }

        if ($state->currentTurn > 50) {
            $state->log[] = "Time Limit Draw!";
        }

        return [
            'winner' => $state->currentTurn <= 50 ? $attacker : null,
            'log'    => $state->log,
        ];
    }

    private function calculateSuccessOdds(Wrestler $attacker, Wrestler $defender, MatchState $state, float $phaseModifier): float
    {
        // Tier disparity weight (e.g., Tier 1 Main Eventer vs Tier 4 Jobber)
        $tierDiff = ($attacker->tier_rating - $defender->tier_rating) * 15;

        // Raw attribute comparison
        $attribDiff = ($attacker->offense - $defender->defense) + $tierDiff;

        // Current situational variables
        $momDiff     = $state->getMomentumModifier($attacker->id) * 25;
        $staminaDiff = ($state->stamina[$attacker->id] - $state->stamina[$defender->id]) * 0.5;

        // The core formula variable
        $x = $attribDiff + $momDiff + $staminaDiff;

        // Logistic function: 1 / (1 + e^(-k * x))
        $probability = 1 / (1 + exp(-self::SCALING_CONSTANT * $x));

        // Adjust slightly based on match phase progression
        return max(0.10, min(0.95, $probability * $phaseModifier));
    }

    private function checkPinfallThreshold(Wrestler $defender, MatchState $state): bool
    {
        // Base kickout chance influenced heavily by card tier and current health metrics
        $baseKickout = ($defender->resilience ?? 50) + ($defender->tier_rating * 10);

        $damagePenalty = $state->damage[$defender->id] * 1.5;
        $staminaBonus  = $state->stamina[$defender->id] * 0.4;

        $kickoutChance = $baseKickout - $damagePenalty + $staminaBonus;

        // If the calculation drops below a randomized threshold, they fail to kick out
        return rand(1, 100) > max(5, $kickoutChance);
    }

    private function getPhaseModifier(int $turn): float
    {
        if ($turn <= 10) {
            return 0.90;
        }
        // Early match: Moves are harder to hit cleanly, chain wrestling
        if ($turn <= 25) {
            return 1.00;
        }
                     // Mid match: Standard balance
        return 1.10; // Late match: Defensive guards are down, big moves connect easier
    }
}
