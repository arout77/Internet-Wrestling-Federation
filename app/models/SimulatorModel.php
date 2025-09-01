<?php

namespace App\Model;

use Src\Model\System_Model;

class SimulatorModel extends System_Model
{
    /**
     * @var array
     */
    private $matchLog = [];
    /**
     * @var int
     */
    private $maxTurns = 200;
    /**
     * @var array
     */
    private $allMoves = [];

    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
        $this->loadAllMoves();
    }

    /**
     * Main function to simulate a match between two wrestlers.
     */
    public function simulateMatch( $wrestler1, $wrestler2, $isBulk = false )
    {
        $this->matchLog = [];
        $turn           = 0;
        $winner         = null;

        $matchState = $this->initializeMatchState( $wrestler1, $wrestler2 );

        if ( !$isBulk )
        {
            $this->logMessage( "--- Match Start: {$wrestler1->name} vs. {$wrestler2->name} ---" );
        }

        // **THE FIX:** Use wrestler_id to access the match state array
        if ( !$isBulk )
        {
            $this->logMessage( "{$wrestler1->name} starts with {$matchState[$wrestler1->wrestler_id]['hp']} HP and {$matchState[$wrestler1->wrestler_id]['stamina']} Stamina." );
        }

        if ( !$isBulk )
        {
            $this->logMessage( "{$wrestler2->name} starts with {$matchState[$wrestler2->wrestler_id]['hp']} HP and {$matchState[$wrestler2->wrestler_id]['stamina']} Stamina." );
        }

        while ( $turn < $this->maxTurns && $winner === null )
        {
            $turn++;
            if ( !$isBulk )
            {
                $this->logMessage( "--- Turn {$turn} ---" );
            }

            // **THE FIX:** Use wrestler_id to determine the attacker/defender
            $attackerId = $this->determineAttacker( $matchState, $wrestler1->wrestler_id, $wrestler2->wrestler_id );
            $defenderId = ( $attackerId === $wrestler1->wrestler_id ) ? $wrestler2->wrestler_id : $wrestler1->wrestler_id;

            // Use references to easily modify the state
            $attackerState = &$matchState[$attackerId];
            $defenderState = &$matchState[$defenderId];

            if ( !$isBulk )
            {
                $this->logMessage( "{$attackerState['data']->name} is on the offensive!" );
            }

            $move = $this->selectMove( $attackerState );
            if ( !$move )
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} is too tired to perform a move and catches their breath." );
                }

                $attackerState['stamina'] += 100; // Small stamina recovery
                continue;
            }

            $hitChance = $this->calculateHitChance( $attackerState, $defenderState, $move );
            $roll      = rand( 1, 100 );

            if ( $roll <= $hitChance )
            {
                $damage = $this->calculateDamage( $attackerState, $defenderState, $move );
                $defenderState['hp'] -= $damage;

                $attackerState['stamina'] -= $move->stamina_cost;
                $attackerState['momentum'] += $move->momentumGain;

                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} hits a {$move->move_name} for {$damage} damage!" );
                }

                if ( !$isBulk )
                {
                    $this->logMessage( "{$defenderState['data']->name} has {$defenderState['hp']} HP remaining." );
                }

            }
            else
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} goes for a {$move->move_name}... but {$defenderState['data']->name} reverses it!" );
                }

                $defenderState['momentum'] += 15;
                $attackerState['stamina'] -= ceil( $move->stamina_cost / 2 ); // Penalize stamina for a missed move
            }

            $attackerState['momentum'] = max( 0, $attackerState['momentum'] - 5 );
            $defenderState['momentum'] = max( 0, $defenderState['momentum'] - 5 );

            if ( $defenderState['hp'] <= 0 )
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$defenderState['data']->name} has been pinned!" );
                }

                $winner = $attackerState['data'];
            }
        }

        if ( $winner )
        {
            if ( !$isBulk )
            {
                $this->logMessage( "--- Match End: The winner is {$winner->name}! ---" );
            }

        }
        else
        {
            if ( !$isBulk )
            {
                $this->logMessage( "--- Match End: The time limit has expired. It's a draw! ---" );
            }

            $winner = 'draw';
        }

        return [
            'winner' => $winner,
            'log'    => $this->matchLog,
        ];
    }

    /**
     * Runs a specified number of simulations and returns the aggregated results,
     * including win counts, probabilities, and moneyline odds.
     */
    public function runBulkSimulations( $wrestler1, $wrestler2, $simCount = 100 )
    {
        $winCounts = [
            $wrestler1->name => 0,
            $wrestler2->name => 0,
            'draw'           => 0,
        ];

        for ( $i = 0; $i < $simCount; $i++ )
        {
            // Pass 'true' to indicate a bulk simulation, which is faster.
            $result = $this->simulateMatch( $wrestler1, $wrestler2, true );

            if ( is_object( $result['winner'] ) )
            {
                $winCounts[$result['winner']->name]++;
            }
            else
            { // It's a draw
                $winCounts['draw']++;
            }
        }

        // **THE FIX:** Calculate probabilities and moneyline odds before returning.
        $probabilities = [];
        $moneylineOdds = [];
        foreach ( $winCounts as $name => $wins )
        {
            if ( $simCount > 0 )
            {
                $probability          = $wins / $simCount;
                $probabilities[$name] = $probability;
                // The calculateMoneyline helper function is required for this to work.
                $moneylineOdds[$name] = $this->calculateMoneyline( $probability );
            }
        }

        return [
            'wins'          => $winCounts,
            'probabilities' => $probabilities,
            'moneyline'     => $moneylineOdds,
        ];
    }

    /**
     * Helper function to calculate American (moneyline) odds from a probability.
     * @param float $probability The probability of an outcome (0 to 1).
     * @return string The moneyline odds string (e.g., "+250", "-150").
     */
    private function calculateMoneyline( $probability )
    {
        if ( $probability <= 0 )
        {
            return '+99900';
        }

        if ( $probability >= 1 )
        {
            return '-99900';
        }

        if ( $probability < 0.5 )
        {
            $odds = ( 100 / $probability ) - 100;
            return '+' . round( $odds );
        }
        else
        {
            $odds = -100 / ( ( 100 / ( 100 * $probability ) ) - 1 );
            return round( $odds );
        }
    }

    /**
     * Calculates the damage a move inflicts.
     */
    private function calculateDamage( $attackerState, $defenderState, $move )
    {
        // **THE FIX:** This logic now correctly handles both plain numbers and JSON strings.
        $minDamage     = 0;
        $maxDamage     = 0;
        $decodedDamage = json_decode( $move->min_damage, true );

        if ( json_last_error() === JSON_ERROR_NONE && isset( $decodedDamage['min'] ) && isset( $decodedDamage['max'] ) )
        {
            // Handle JSON format: e.g., {"min": 18, "max": 28}
            $minDamage = (int) $decodedDamage['min'];
            $maxDamage = (int) $decodedDamage['max'];
        }
        else
        {
            // Handle plain number format
            $minDamage = (int) $move->min_damage;
            $maxDamage = (int) $move->max_damage;
        }

        // 1. Get base damage from the move
        $baseDamage = rand( $minDamage, $maxDamage );

        // 2. Add bonus based on attacker's relevant stat
        $attackerStatValue = $attackerState['data']->{$move->stat} ?? 50;
        $statBonus         = $attackerStatValue * 0.5;

        // 3. Apply modifier for size difference
        $sizeModifier = $this->getSizeModifier( $attackerState['data'], $defenderState['data'], $move->type );

        // 4. Calculate total damage before defender's reduction
        $grossDamage = ( $baseDamage + $statBonus ) * $sizeModifier;

        // 5. Reduce damage based on defender's toughness
        $toughnessReduction = $defenderState['data']->toughness * 0.25;

        $finalDamage = ceil( $grossDamage - $toughnessReduction );

        return max( 1, $finalDamage ); // Ensure a move always does at least 1 damage
    }

    /**
     * Calculates a damage modifier based on the size difference between wrestlers.
     */
    private function getSizeModifier( $attacker, $defender, $moveType )
    {
        $weightDifference = $attacker->weight - $defender->weight;
        $modifier         = 1.0; // Start with no modification

        // Bonus for heavier wrestlers using power/strength moves
        if ( $moveType === 'grapple' || $moveType === 'strike' )
        {
            if ( $weightDifference > 50 )
            {
                $modifier += 0.15; // 15% bonus for a significant weight advantage
            }
            elseif ( $weightDifference > 20 )
            {
                $modifier += 0.07; // 7% bonus for a minor weight advantage
            }
        }

        // Bonus for lighter wrestlers using high-flying moves
        if ( $moveType === 'highFlying' )
        {
            if ( $weightDifference < -50 )
            {
                $modifier += 0.15; // 15% bonus for a significant weight disadvantage (agility)
            }
            elseif ( $weightDifference < -20 )
            {
                $modifier += 0.07; // 7% bonus for a minor weight disadvantage
            }
        }

        return $modifier;
    }

    /**
     * @param $attackerState
     * @return mixed
     */
    private function selectMove( $attackerState )
    {
        $wrestlerMoves = json_decode( $attackerState['data']->moves, true );

        // **THE FIX:** Check if the wrestler has any moves before proceeding.
        if ( empty( $wrestlerMoves ) || !is_array( $wrestlerMoves ) || count( $wrestlerMoves ) === 0 )
        {
            // This wrestler has no moves, return a default 'struggle' move
            return (object) [
                'move_name'     => 'Struggle',
                'baseHitChance' => 1.0,
                'stat'          => 'strength',
                'stamina_cost'  => 10,
                'momentumGain'  => 2,
                'min_damage'    => '{"min": 5, "max": 10}',
                'max_damage'    => 0,
                'type'          => 'strike',
            ];
        }

        $allWrestlerMoveNames = array_merge( ...array_values( $wrestlerMoves ) );

        // This can happen if the moves JSON is structured but the move type arrays are empty
        if ( empty( $allWrestlerMoveNames ) )
        {
            return (object) [
                'move_name'     => 'Struggle',
                'baseHitChance' => 1.0,
                'stat'          => 'strength',
                'stamina_cost'  => 10,
                'momentumGain'  => 2,
                'min_damage'    => '{"min": 5, "max": 10}',
                'max_damage'    => 0,
                'type'          => 'strike',
            ];
        }

        $fullMoveDetails = array_filter( $this->allMoves, function ( $move ) use ( $allWrestlerMoveNames )
        {
            return in_array( $move->move_name, $allWrestlerMoveNames );
        } );

        $executableMoves = array_filter( $fullMoveDetails, function ( $move ) use ( $attackerState )
        {
            return $attackerState['stamina'] >= $move->stamina_cost;
        } );

        if ( empty( $executableMoves ) )
        {
            return null; // Wrestler is too tired
        }

        $weightedMoves = [];
        foreach ( $executableMoves as $move )
        {
            $weight = 10;
            if ( $move->type === 'finisher' && $attackerState['momentum'] >= 100 )
            {
                $weight += 50;
            }
            for ( $i = 0; $i < $weight; $i++ )
            {
                $weightedMoves[] = $move;
            }
        }

        return $weightedMoves[array_rand( $weightedMoves )];
    }

    /**
     * @param $attackerState
     * @param $defenderState
     * @param $move
     */
    private function calculateHitChance( $attackerState, $defenderState, $move )
    {
        $baseChance        = (float) $move->baseHitChance * 100;
        $attackerStatValue = $attackerState['data']->{$move->stat} ?? 50;
        $defenderStatValue = $defenderState['data']->reversalAbility ?? 50;
        $statDifference    = $attackerStatValue - $defenderStatValue;
        $statBonus         = $statDifference * 0.5;

        $staminaPercentage = $attackerState['stamina'] / ( $attackerState['data']->stamina * 10 );
        $staminaPenalty    = 0;
        if ( $staminaPercentage < 0.3 )
        {
            $staminaPenalty = 20;
        }
        else if ( $staminaPercentage < 0.5 )
        {
            $staminaPenalty = 10;
        }

        $finalChance = $baseChance + $statBonus - $staminaPenalty;
        return max( 5, min( 95, $finalChance ) );
    }

    private function loadAllMoves()
    {
        // **THE FIX:** Manually create an instance of the ApiModel.
        // The $this->app variable is available because we are extending System_Model.
        $apiModel       = new \App\Model\ApiModel( $this->app );
        $this->allMoves = $apiModel->getAllMoves();
    }

    /**
     * @param $matchState
     * @param $id1
     * @param $id2
     */
    private function determineAttacker( $matchState, $id1, $id2 )
    {
        $momentum1        = $matchState[$id1]['momentum'];
        $momentum2        = $matchState[$id2]['momentum'];
        $wrestler1_chance = 50 + ( ( $momentum1 - $momentum2 ) / 2 );
        $wrestler1_chance = max( 10, min( 90, $wrestler1_chance ) );
        return ( rand( 1, 100 ) <= $wrestler1_chance ) ? $id1 : $id2;
    }

    /**
     * @param $wrestler1
     * @param $wrestler2
     * @return mixed
     */
    private function initializeMatchState( $wrestler1, $wrestler2 )
    {
        $state = [];
        foreach ( [$wrestler1, $wrestler2] as $wrestler )
        {
            $initialHp      = $wrestler->baseHp + ( $wrestler->toughness * 10 );
            $initialStamina = $wrestler->stamina * 10;

            // **THE FIX:** Use the correct property 'wrestler_id' instead of 'id'.
            $state[$wrestler->wrestler_id] = [
                'hp'       => $initialHp,
                'stamina'  => $initialStamina,
                'momentum' => 50,
                'data'     => $wrestler,
            ];
        }
        return $state;
    }

    /**
     * @param $message
     */
    private function logMessage( $message )
    {
        $this->matchLog[] = $message;
    }
}
