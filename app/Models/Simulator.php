<?php

namespace App\Models;

use Core\BaseModel;
use PDO;

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

    /**
     * @param Api $api
     */
    public function __construct( Api $api )
    {
        parent::__construct();
        $this->api = $api;
    }

    /**
     * Runs a complete, turn-by-turn wrestling match simulation.
     * This is now the single, authoritative method for all simulations.
     */
    public function run( $wrestler1_id, $wrestler2_id ): array
    {
        $this->log  = [];
        $this->turn = 0;

        $this->initializeWrestlers( $wrestler1_id, $wrestler2_id );

        while ( $this->isMatchOngoing() ) {
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

    /**
     * @param int $wrestler1Id
     * @param int $wrestler2Id
     */
    private function initializeWrestlers( int | string $wrestler1Id, int | string $wrestler2Id ): void
    {
        $this->wrestler1 = $this->api->get_wrestler( $wrestler1Id );
        $this->wrestler2 = $this->api->get_wrestler( $wrestler2Id );

        if ( !$this->wrestler1 || !$this->wrestler2 ) {
            throw new \Exception( "Could not find one or both wrestlers." );
        }

        $this->wrestler1Moves = $this->getWrestlerMoves( (int) $wrestler1Id );
        $this->wrestler2Moves = $this->getWrestlerMoves( (int) $wrestler2Id );

        foreach ( [ &$this->wrestler1, &$this->wrestler2] as &$w ) {
            $w['max_hp']          = $w['baseHp'];
            $w['current_hp']      = $w['baseHp'];
            $w['max_stamina']     = $w['stamina'];
            $w['current_stamina'] = $w['stamina'];
            $w['momentum']        = 50; // Start with even momentum
        }
    }

    private function executeTurn(): void
    {
        $momentumTotal = ( $this->wrestler1['momentum'] ?? 50 ) + ( $this->wrestler2['momentum'] ?? 50 );
        $roll          = rand( 1, $momentumTotal );

        if ( $roll <= $this->wrestler1['momentum'] ) {
            $this->performAction( $this->wrestler1, $this->wrestler2 );
        } else {
            $this->performAction( $this->wrestler2, $this->wrestler1 );
        }
    }

    /**
     * @param $attacker
     * @param $defender
     * @return null
     */
    private function performAction( array &$attacker, array &$defender ): void
    {
        $move = $this->selectMove( $attacker );

        if ( !$move ) {
            $attacker['current_stamina'] = min( $attacker['max_stamina'], $attacker['current_stamina'] + 10 );
            return;
        }

        if ( ( mt_rand() / mt_getrandmax() ) <= $this->calculateHitChance( $move, $attacker, $defender ) ) {
            $damage                      = $this->calculateDamage( $move, $attacker );
            $defender['current_hp']      = max( 0, $defender['current_hp'] - $damage );
            $attacker['current_stamina'] = max( 0, $attacker['current_stamina'] - ( $move['stamina_cost'] ?? 5 ) );

            // --- NEW: Cap the momentum gain ---
            $attacker['momentum'] = min( self::MAX_MOMENTUM, $attacker['momentum'] + ( $move['momentumGain'] ?? 10 ) );

            $this->log[] = [
                'type'                      => 'damage',
                'attacker_id'               => $attacker['wrestler_id'],
                'defender_id'               => $defender['wrestler_id'],
                'defender_hp_percent'       => ( $defender['current_hp'] / $defender['max_hp'] ) * 100,
                'attacker_stamina_percent'  => ( $attacker['current_stamina'] / $attacker['max_stamina'] ) * 100,
                // --- NEW: Add momentum percentage to the log ---
                'attacker_momentum_percent' => ( $attacker['momentum'] / self::MAX_MOMENTUM ) * 100,
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
     * Selects a move for a wrestler based on their tendencies and stamina.
     * @param array $wrestler
     * @return mixed
     */
    private function selectMove( array $wrestler ): ?array
    {
        $moveset = ( $wrestler['wrestler_id'] === $this->wrestler1['wrestler_id'] ) ? $this->wrestler1Moves : $this->wrestler2Moves;
        if ( empty( $moveset ) ) {
            return null;
        }

        // 1. Filter moves by available stamina
        $availableMoves = array_filter( $moveset, function ( $move ) use ( $wrestler ) {
            return $wrestler['current_stamina'] >= ( $move['stamina_cost'] ?? 5 );
        } );

        if ( empty( $availableMoves ) ) {
            return null;
        }

        // 2. Build a weighted pool based on tendencies
        $weightedPool = [];
        $tendencyMap  = [
            'strike'     => 'tendency_strike',
            'grapple'    => 'tendency_grapple',
            'submission' => 'tendency_submission',
            'highfly'    => 'tendency_highfly',
        ];

        foreach ( $availableMoves as $move ) {
            $moveType    = $move['type'] ?? 'strike';
            $tendencyKey = $tendencyMap[$moveType] ?? 'tendency_strike';
            $weight      = $wrestler[$tendencyKey] ?? 50; // Default to 50 if tendency is not set

            // Add the move to the pool 'weight' number of times
            for ( $i = 0; $i < $weight; $i++ ) {
                $weightedPool[] = $move;
            }
        }

        if ( empty( $weightedPool ) ) {
            // Fallback to random if something went wrong (e.g., all tendencies are 0)
            return $availableMoves[array_rand( $availableMoves )];
        }

        // 3. Select a random move from the weighted pool
        return $weightedPool[array_rand( $weightedPool )];
    }

    /**
     * @param array $move
     * @param array $attacker
     */
    private function calculateDamage( array $move, array $attacker ): int
    {
        $statName   = trim( $move['stat'] ?? 'strength' );
        $statValue  = $attacker[$statName] ?? 60;
        $baseDamage = rand( (int) ( $move['min_damage'] ?? 5 ), (int) ( $move['max_damage'] ?? 15 ) );
        return (int) max( 1, $baseDamage + ( $statValue / 10 ) );
    }

    /**
     * @param array $move
     * @param array $attacker
     * @param array $defender
     */
    private function calculateHitChance( array $move, array $attacker, array $defender ): float
    {
        $baseChance   = (float) ( $move['baseHitChance'] ?? 0.75 );
        $reversalDiff = ( $attacker['reversalAbility'] ?? 60 ) - ( $defender['reversalAbility'] ?? 60 );
        return max( 0.10, min( 0.95, $baseChance + ( $reversalDiff / 200 ) ) );
    }

    /**
     * @return mixed
     */
    private function isMatchOngoing(): bool
    {
        return $this->wrestler1['current_hp'] > 0 && $this->wrestler2['current_hp'] > 0 && $this->turn < self::MAX_TURNS;
    }

    private function determineWinner(): array
    {
        $winner = null;
        $method = 'Draw';

        if ( $this->wrestler1['current_hp'] <= 0 ) {
            $winner = $this->wrestler2;
            $method = 'Pinfall';
        } elseif ( $this->wrestler2['current_hp'] <= 0 ) {
            $winner = $this->wrestler1;
            $method = 'Pinfall';
        } elseif ( $this->turn >= self::MAX_TURNS ) {
            // Time limit reached, decide by remaining HP
            $method = "Judge's Decision";
            $winner = ( $this->wrestler1['current_hp'] > $this->wrestler2['current_hp'] ) ? $this->wrestler1 : $this->wrestler2;
        }

        return [
            'id'     => $winner['wrestler_id'] ?? null,
            'name'   => $winner['name'] ?? 'No one',
            'method' => $method,
        ];
    }

    /**
     * Gets the moveset for a wrestler, checking both roster and prospect moves.
     * @param int|string $id The wrestler's ID (roster ID or prospect PID).
     * @return array An array of move objects.
     */
    private function getWrestlerMoves( int | string $id ): array// Accept int OR string
    {
        // Determine which table to query based on the ID type
        if ( is_numeric( $id ) ) {
            // Roster wrestler - query roster_moves
            $sql  = "SELECT m.* FROM all_moves m JOIN roster_moves rm ON m.move_id = rm.move_id WHERE rm.roster_wrestler_id = ?";
            $stmt = $this->db->prepare( $sql );
            $stmt->execute( [(int) $id] ); // Execute with the integer ID
        } else {
            // Prospect wrestler - query prospect_moves
            $sql  = "SELECT m.* FROM all_moves m JOIN prospect_moves pm ON m.move_id = pm.move_id WHERE pm.prospect_pid = ?";
            $stmt = $this->db->prepare( $sql );
            $stmt->execute( [(string) $id] ); // Execute with the string PID
        }

        return $stmt->fetchAll( PDO::FETCH_ASSOC );
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
    public function runBulkSimulations( $wrestler1_id, $wrestler2_id, $simCount )
    {
        $wrestler1_data = $this->api->get_wrestler( $wrestler1_id );
        $wrestler2_data = $this->api->get_wrestler( $wrestler2_id );

        // --- FIX: Check if wrestler data was successfully fetched ---
        if ( !$wrestler1_data || !$wrestler2_data ) {
            // Log the error for debugging
            error_log( "Could not find wrestler data for bulk simulation: ID1=" . print_r( $wrestler1_id, true ) . ", ID2=" . print_r( $wrestler2_id, true ) );
            // Return an error structure to the controller
            return [
                'error'         => 'Could not find wrestler data for one or both participants.',
                'wins'          => [],
                'probabilities' => [],
                'moneyline'     => [],
            ];
        }
        // --- END FIX ---

        $winCounts = [
            $wrestler1_data['name'] => 0, // Now safe to access 'name'
            $wrestler2_data['name'] => 0, // Now safe to access 'name'
            'draw' => 0,
        ];

        for ( $i = 0; $i < $simCount; $i++ ) {
            // Ensure the IDs passed to run() are the correct ones
            $result    = $this->run( $wrestler1_data['wrestler_id'], $wrestler2_data['wrestler_id'] );
            $winner_id = $result['winner_id'] ?? null;

            if ( $winner_id == $wrestler1_data['wrestler_id'] ) {
                $winCounts[$wrestler1_data['name']]++;
            } elseif ( $winner_id == $wrestler2_data['wrestler_id'] ) {
                $winCounts[$wrestler2_data['name']]++;
            } else {
                // Could be a draw or an error in the single simulation
                if ( $result['victoryMethod'] === 'Draw' || $winner_id === null ) {
                    $winCounts['draw']++;
                }
                // Optionally log if it's neither win/loss/draw, indicating a potential issue in run()
            }
        }

        $probabilities = [];
        $moneylineOdds = [];
        foreach ( $winCounts as $name => $wins ) {
            $probability          = ( $simCount > 0 ) ? $wins / $simCount : 0;
            $probabilities[$name] = $probability;
            $moneylineOdds[$name] = $this->calculateMoneyline( $probability );
        }

        return [
            'wins'          => $winCounts,
            'probabilities' => $probabilities,
            'moneyline'     => $moneylineOdds,
        ];
    }

    /**
     * @param $probability
     */
    private function calculateMoneyline( $probability )
    {
        if ( $probability <= 0 ) {
            return '+9900';
        }

        if ( $probability >= 1 ) {
            return '-99900';
        }

        if ( $probability < 0.5 ) {
            return '+' . round( ( 100 / $probability ) - 100 );
        }
        return round( -100 / ( 1 - ( 1 / $probability ) ) );
    }
}
