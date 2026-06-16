<?php
namespace App\Models;

use PDO;
use Rhapsody\Core\BaseModel;

class Simulator extends BaseModel
{
    private array $wrestler1;
    private array $wrestler2;
    private array $wrestler1Moves;
    private array $wrestler2Moves;
    private array $log         = [];
    private int $turn          = 0;
    private const MAX_TURNS    = 150;
    private const MAX_MOMENTUM = 200;

    private Api $api;
    private static array $moveCache = [];

    public function __construct(Api $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    /**
     * Runs a complete, turn-by-turn wrestling match simulation.
     */
    public function run($wrestler1_id, $wrestler2_id): array
    {
        $this->log  = [];
        $this->turn = 0;

        $this->initializeWrestlers($wrestler1_id, $wrestler2_id);

        while ($this->isMatchOngoing()) {
            $this->turn++;
            $this->executeTurn();
        }

        $winnerData = $this->determineWinner();

        $this->log[] = [
            'type'    => 'end',
            'message' => "The match is over! {$winnerData['name']} wins by {$winnerData['method']}!",
        ];

        return [
            'success'       => true,
            'winner_id'     => $winnerData['id'],
            'victoryMethod' => $winnerData['method'],
            'log'           => $this->log,
        ];
    }

    private function initializeWrestlers(int | string $wrestler1Id, int | string $wrestler2Id): void
    {
        $this->wrestler1 = $this->api->get_wrestler($wrestler1Id);
        $this->wrestler2 = $this->api->get_wrestler($wrestler2Id);

        if (! $this->wrestler1 || ! $this->wrestler2) {
            throw new \Exception("Could not find one or both wrestlers.");
        }

        $this->wrestler1Moves = $this->getWrestlerMoves($wrestler1Id);
        $this->wrestler2Moves = $this->getWrestlerMoves($wrestler2Id);

        foreach ([ &$this->wrestler1, &$this->wrestler2] as &$w) {
            $w['max_hp']          = $w['baseHp'];
            $w['current_hp']      = $w['baseHp'];
            $w['max_stamina']     = $w['stamina'];
            $w['current_stamina'] = $w['stamina'];
            $w['momentum']        = 50; // Start with even momentum
        }
    }

    private function executeTurn(): void
    {
        $momentumTotal = ($this->wrestler1['momentum'] ?? 50) + ($this->wrestler2['momentum'] ?? 50);
        $roll          = rand(1, $momentumTotal);

        if ($roll <= $this->wrestler1['momentum']) {
            $this->performAction($this->wrestler1, $this->wrestler2);
        } else {
            $this->performAction($this->wrestler2, $this->wrestler1);
        }
    }

    private function performAction(array &$attacker, array &$defender): void
    {
        $move = $this->selectMove($attacker);

        if (! $move) {
            $attacker['current_stamina'] = min($attacker['max_stamina'], $attacker['current_stamina'] + 10);
            return;
        }

        // Calculate hit chance with new formula
        $hitChance  = $this->calculateHitChance($move, $attacker, $defender);
                                                      // Adjust hit chance by momentum (small bonus)
        $hitChance += ($attacker['momentum'] / 2000); // max +10% at 200 momentum
        $hitChance  = max(0.20, min(0.90, $hitChance));

        if ((mt_rand() / mt_getrandmax()) <= $hitChance) {
            $damage                      = $this->calculateDamage($move, $attacker, $defender);
            $defender['current_hp']      = max(0, $defender['current_hp'] - $damage);
            $attacker['current_stamina'] = max(0, $attacker['current_stamina'] - ($move['stamina_cost'] ?? 5));

            // Momentum gain (capped)
            $attacker['momentum'] = min(self::MAX_MOMENTUM, $attacker['momentum'] + ($move['momentumGain'] ?? 10));

            $this->log[] = [
                'type'                      => 'damage',
                'attacker_id'               => $attacker['wrestler_id'],
                'defender_id'               => $defender['wrestler_id'],
                'defender_hp_percent'       => ($defender['current_hp'] / $defender['max_hp']) * 100,
                'attacker_stamina_percent'  => ($attacker['current_stamina'] / $attacker['max_stamina']) * 100,
                'attacker_momentum_percent' => ($attacker['momentum'] / self::MAX_MOMENTUM) * 100,
                'message'                   => "{$attacker['name']} hits {$move['move_name']} on {$defender['name']} for {$damage} damage!",
            ];
        } else {
            $this->log[] = [
                'type'    => 'miss',
                'message' => "{$attacker['name']} attempts {$move['move_name']} but misses!",
            ];
        }
    }

    /**
     * New damage formula: base * (1 + (attacker_stat - defender_toughness)/150)
     * Also includes size advantage (grapple bonus for weight difference) and momentum bonus.
     */
    private function calculateDamage(array $move, array $attacker, array $defender): int
    {
        $statName          = trim($move['stat'] ?? 'strength');
        $attackerStat      = $attacker[$statName] ?? 50;
        $defenderToughness = $defender['toughness'] ?? 50;

        $baseDamage = rand((int) ($move['min_damage'] ?? 5), (int) ($move['max_damage'] ?? 15));

        // Attribute multiplier (difference between attacker's relevant stat and defender's toughness)
        $diff       = $attackerStat - $defenderToughness;
        $multiplier = 1 + ($diff / 150);
        $multiplier = max(0.5, min(1.5, $multiplier));

        // Size advantage for grapple moves
        $sizeBonus = 1.0;
        $moveType  = $move['type'] ?? 'strike';
        if ($moveType === 'grapple') {
            $weightDiff = ($attacker['weight'] ?? 0) - ($defender['weight'] ?? 0);
            if ($weightDiff >= 100) {
                $sizeBonus = 1.10; // +10% damage for much heavier attacker
            } elseif ($weightDiff <= -100) {
                $sizeBonus = 0.90; // -10% damage if much lighter
            }
        }

        // Momentum bonus (up to +25% at max momentum)
        $momentumBonus = 1 + ($attacker['momentum'] / (self::MAX_MOMENTUM * 2));
        $momentumBonus = min(1.25, $momentumBonus);

        $damage = (int) round($baseDamage * $multiplier * $sizeBonus * $momentumBonus);
        return max(1, $damage);
    }

    /**
     * New hit chance formula: base + (offense - defense) / 300
     * Offense/defense depend on move type.
     */
    private function calculateHitChance(array $move, array $attacker, array $defender): float
    {
        $moveType   = $move['type'] ?? 'strike';
        $baseChance = (float) ($move['baseHitChance'] ?? 0.75);

        $offense = 50;
        $defense = 50;

        switch ($moveType) {
            case 'grapple':
                $offense = $attacker['strength'] ?? 50;
                $defense = $defender['strength'] ?? 50;
                break;
            case 'strike':
                $offense = $attacker['brawlingAbility'] ?? 50;
                $defense = $defender['toughness'] ?? 50;
                break;
            case 'highfly':
                $offense = $attacker['aerialAbility'] ?? 50;
                $defense = $defender['aerialAbility'] ?? 50;
                break;
            case 'submission':
                $offense = $attacker['technicalAbility'] ?? 50;
                $defense = $defender['submissionDefense'] ?? 50;
                break;
        }

        $adjustment = ($offense - $defense) / 300;
        $hitChance  = $baseChance + $adjustment;
        return max(0.20, min(0.90, $hitChance));
    }

    private function selectMove(array $wrestler): ?array
    {
        $moveset = ($wrestler['wrestler_id'] === $this->wrestler1['wrestler_id']) ? $this->wrestler1Moves : $this->wrestler2Moves;
        if (empty($moveset)) {
            return null;
        }

        $availableMoves = array_filter($moveset, function ($move) use ($wrestler) {
            return $wrestler['current_stamina'] >= ($move['stamina_cost'] ?? 5);
        });

        if (empty($availableMoves)) {
            return null;
        }

        // Weighted selection based on tendencies
        $weightedPool = [];
        $tendencyMap  = [
            'strike'     => 'tendency_strike',
            'grapple'    => 'tendency_grapple',
            'submission' => 'tendency_submission',
            'highfly'    => 'tendency_highfly',
        ];

        foreach ($availableMoves as $move) {
            $moveType    = $move['type'] ?? 'strike';
            $tendencyKey = $tendencyMap[$moveType] ?? 'tendency_strike';
            $weight      = $wrestler[$tendencyKey] ?? 50;
            for ($i = 0; $i < $weight; $i++) {
                $weightedPool[] = $move;
            }
        }

        if (empty($weightedPool)) {
            return $availableMoves[array_rand($availableMoves)];
        }

        return $weightedPool[array_rand($weightedPool)];
    }

    private function isMatchOngoing(): bool
    {
        return $this->wrestler1['current_hp'] > 0 && $this->wrestler2['current_hp'] > 0 && $this->turn < self::MAX_TURNS;
    }

    private function determineWinner(): array
    {
        $winner = null;
        $method = 'Draw';

        if ($this->wrestler1['current_hp'] <= 0) {
            $winner = $this->wrestler2;
            $method = 'Pinfall';
        } elseif ($this->wrestler2['current_hp'] <= 0) {
            $winner = $this->wrestler1;
            $method = 'Pinfall';
        } elseif ($this->turn >= self::MAX_TURNS) {
            $method = "Judge's Decision";
            $winner = ($this->wrestler1['current_hp'] > $this->wrestler2['current_hp']) ? $this->wrestler1 : $this->wrestler2;
        }

        return [
            'id'     => $winner['wrestler_id'] ?? null,
            'name'   => $winner['name'] ?? 'No one',
            'method' => $method,
        ];
    }

    private function getWrestlerMoves(int | string $id): array
    {
        $key = (string) $id;
        if (! isset(self::$moveCache[$key])) {
            if (is_numeric($id)) {
                $sql  = "SELECT m.* FROM all_moves m JOIN roster_moves rm ON m.move_id = rm.move_id WHERE rm.roster_wrestler_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([(int) $id]);
            } else {
                $sql  = "SELECT m.* FROM all_moves m JOIN prospect_moves pm ON m.move_id = pm.move_id WHERE pm.prospect_pid = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([(string) $id]);
            }
            self::$moveCache[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return self::$moveCache[$key];
    }

    /**
     * Runs multiple simulations between two wrestlers to calculate win probabilities and odds.
     * Handles cases where wrestler data might not be found.
     *
     * @param mixed $wrestler1_id ID of the first wrestler (can be roster ID or prospect PID).
     * @param mixed $wrestler2_id ID of the second wrestler (can be roster ID or prospect PID).
     * @param int $simCount The number of simulations to run.
     * @return array An array containing win counts, probabilities, moneyline odds, or an error message.
     */
    public function runBulkSimulations($wrestler1_id, $wrestler2_id, $simCount)
    {
        $wrestler1_data = $this->api->get_wrestler($wrestler1_id);
        $wrestler2_data = $this->api->get_wrestler($wrestler2_id);

        if (! $wrestler1_data || ! $wrestler2_data) {
            error_log("Could not find wrestler data for bulk simulation: ID1=" . print_r($wrestler1_id, true) . ", ID2=" . print_r($wrestler2_id, true));
            return [
                'error'         => 'Could not find wrestler data for one or both participants.',
                'wins'          => [],
                'probabilities' => [],
                'moneyline'     => [],
            ];
        }

        $winCounts = [
            $wrestler1_data['name'] => 0,
            $wrestler2_data['name'] => 0,
            'draw'                  => 0,
        ];

        for ($i = 0; $i < $simCount; $i++) {
            $result    = $this->run($wrestler1_data['wrestler_id'], $wrestler2_data['wrestler_id']);
            $winner_id = $result['winner_id'] ?? null;

            if ($winner_id == $wrestler1_data['wrestler_id']) {
                $winCounts[$wrestler1_data['name']]++;
            } elseif ($winner_id == $wrestler2_data['wrestler_id']) {
                $winCounts[$wrestler2_data['name']]++;
            } else {
                if ($result['victoryMethod'] === 'Draw' || $winner_id === null) {
                    $winCounts['draw']++;
                }
            }
        }

        $probabilities = [];
        $moneylineOdds = [];
        foreach ($winCounts as $name => $wins) {
            $probability          = ($simCount > 0) ? $wins / $simCount : 0;
            $probabilities[$name] = $probability;
            $moneylineOdds[$name] = $this->calculateMoneyline($probability);
        }

        return [
            'wins'          => $winCounts,
            'probabilities' => $probabilities,
            'moneyline'     => $moneylineOdds,
        ];
    }

    private function calculateMoneyline($probability)
    {
        if ($probability <= 0) {
            return '+9900';
        }
        if ($probability >= 1) {
            return '-99900';
        }
        if ($probability < 0.5) {
            return '+' . round((100 / $probability) - 100);
        }
        return round(-100 / (1 - (1 / $probability)));
    }
}
