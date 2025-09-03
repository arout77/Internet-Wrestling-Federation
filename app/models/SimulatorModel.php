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
     * @var mixed
     */
    private $wrestler1_data;
    /**
     * @var mixed
     */
    private $wrestler2_data;
    /**
     * @var array
     */
    protected $simulation_log = [];
    /**
     * @var mixed
     */
    private $winner;
    /**
     * @var mixed
     */
    private $loser;
    /**
     * @var array
     */
    private $comeback_activated = []; // NEW: Track comeback trait usage

    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
        $this->loadAllMoves();
    }

    /**
     * @param $wrestler1_id
     * @param $wrestler2_id
     */
    public function start_simulation( $wrestler1_id, $wrestler2_id )
    {
        $api_model            = new ApiModel( $this->app );
        $this->wrestler1_data = $api_model->getWrestlerById( $wrestler1_id );
        $this->wrestler2_data = $api_model->getWrestlerById( $wrestler2_id );

        if ( !$this->wrestler1_data || !$this->wrestler2_data )
        {
            return ['error' => 'Could not retrieve wrestler data.'];
        }

        // This is a legacy method and can be removed if you only use simulateMatch
        $this->run_full_simulation();

        return [
            'winner' => $this->winner,
            'loser'  => $this->loser,
            'log'    => $this->matchLog, // Return the correct log
        ];
    }

    // This is a legacy method now, you may want to refactor or remove it
    private function run_full_simulation()
    {
        $result       = $this->simulateMatch( $this->wrestler1_data, $this->wrestler2_data );
        $this->winner = $result['winner'];
        $this->loser  = ( $result['winner']->wrestler_id == $this->wrestler1_data->wrestler_id ) ? $this->wrestler2_data : $this->wrestler1_data;
    }

    /**
     * @param $wrestler1
     * @param $wrestler2
     * @param $isBulk
     */
    public function simulateMatch( $wrestler1, $wrestler2, $isBulk = false )
    {
        $this->matchLog           = [];
        $turn                     = 0;
        $winner                   = null;
        $this->comeback_activated = [];

        $matchState = $this->initializeMatchState( $wrestler1, $wrestler2 );

        if ( !$isBulk )
        {
            $this->logMessage( "--- Match Start: {$wrestler1->name} vs. {$wrestler2->name} ---" );
            $this->logMessage( "{$wrestler1->name} starts with {$matchState[$wrestler1->wrestler_id]['hp']} HP and {$matchState[$wrestler1->wrestler_id]['stamina']} Stamina." );
            $this->logMessage( "{$wrestler2->name} starts with {$matchState[$wrestler2->wrestler_id]['hp']} HP and {$matchState[$wrestler2->wrestler_id]['stamina']} Stamina." );
        }

        // NEW: Intimidator Trait Check
        if ( in_array( 'Intimidator', $wrestler1->traits ) && rand( 1, 100 ) <= 10 )
        {
            $matchState[$wrestler2->wrestler_id]['stunned'] = 1;
            if ( !$isBulk )
            {
                $this->logMessage( "{$wrestler2->name} is stunned by {$wrestler1->name}'s intimidating presence and misses the first turn!" );
            }

        }
        if ( in_array( 'Intimidator', $wrestler2->traits ) && rand( 1, 100 ) <= 10 )
        {
            $matchState[$wrestler1->wrestler_id]['stunned'] = 1;
            if ( !$isBulk )
            {
                $this->logMessage( "{$wrestler1->name} is stunned by {$wrestler2->name}'s intimidating presence and misses the first turn!" );
            }

        }

        while ( $turn < $this->maxTurns && $winner === null )
        {
            $turn++;
            if ( !$isBulk )
            {
                $this->logMessage( "--- Turn {$turn} ---" );
            }

            $attackerId = $this->determineAttacker( $matchState, $wrestler1->wrestler_id, $wrestler2->wrestler_id );
            $defenderId = ( $attackerId === $wrestler1->wrestler_id ) ? $wrestler2->wrestler_id : $wrestler1->wrestler_id;

            $attackerState = &$matchState[$attackerId];
            $defenderState = &$matchState[$defenderId];

            // Check if stunned
            if ( $attackerState['stunned'] > 0 )
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} is stunned and cannot make a move!" );
                }

                $attackerState['stunned']--;
                // In a singles match, we just skip to the next full turn where attacker is re-determined.
                // This effectively makes them lose their turn.
                continue;
            }

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

                $attackerState['stamina'] += 100;
                continue;
            }

            $hitChance = $this->calculateHitChance( $attackerState, $defenderState, $move );
            $roll      = rand( 1, 100 );

            // NEW: Dirty Player Trait Check
            if ( $move->type === 'grapple' && in_array( 'Dirty Player', $defenderState['data']->traits ) && rand( 1, 100 ) <= 5 )
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} goes for a grapple, but {$defenderState['data']->name} plays dirty with a LOW BLOW!" );
                }

                $attackerState['stunned'] = 1; // Stun attacker for 1 turn
                $defenderState['momentum'] += 20;
                continue; // Skip the rest of the turn
            }

            if ( $roll <= $hitChance )
            {
                $damage = $this->calculateDamage( $attackerState, $defenderState, $move, $isBulk );
                $defenderState['hp'] -= $damage;

                // NEW: Workhorse Trait stamina reduction
                $staminaCost = in_array( 'Workhorse', $attackerState['data']->traits ) ? $move->stamina_cost * 0.85 : $move->stamina_cost;
                $attackerState['stamina'] -= $staminaCost;

                $momentumGain = $move->momentumGain;
                // NEW: Showman Trait momentum gain
                if ( in_array( 'Showman', $attackerState['data']->traits ) && ( $move->type === 'highFlying' || $move->type === 'finisher' ) )
                {
                    $momentumGain *= 1.5;
                }
                $attackerState['momentum'] += $momentumGain;

                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} hits a {$move->move_name} for {$damage} damage!" );
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
                $attackerState['stamina'] -= ceil( $move->stamina_cost / 2 );
            }

            $attackerState['momentum'] = max( 0, $attackerState['momentum'] - 5 );
            $defenderState['momentum'] = max( 0, $defenderState['momentum'] - 5 );

            // NEW: Comeback Kid Trait Check
            $comebackThreshold = $attackerState['data']->baseHp * 0.25;
            if ( $attackerState['hp'] <= $comebackThreshold && !isset( $this->comeback_activated[$attackerId] ) )
            {
                if ( in_array( 'Comeback Kid', $attackerState['data']->traits ) )
                {
                    $attackerState['momentum'] += 50; // Huge momentum boost
                    $attackerState['damage_modifier']      = 1.20; // 20% damage boost for 3 turns
                    $attackerState['comeback_turns']       = 3;
                    $this->comeback_activated[$attackerId] = true;
                    if ( !$isBulk )
                    {
                        $this->logMessage( "{$attackerState['data']->name} is hurt but fires up! The crowd is going wild!" );
                    }

                }
            }

            // Manage comeback turns
            if ( isset( $attackerState['comeback_turns'] ) && $attackerState['comeback_turns'] > 0 )
            {
                $attackerState['comeback_turns']--;
                if ( $attackerState['comeback_turns'] == 0 )
                {
                    $attackerState['damage_modifier'] = 1.0;
                    if ( !$isBulk )
                    {
                        $this->logMessage( "{$attackerState['data']->name}'s comeback surge wears off." );
                    }

                }
            }

            if ( $defenderState['hp'] <= 0 )
            {
                $isResilient    = in_array( 'Resilient', $defenderState['data']->traits );
                $resilienceRoll = rand( 1, 100 );

                if ( $isResilient && $resilienceRoll <= 5 )
                {
                    $defenderState['hp'] = 1;
                    if ( !$isBulk )
                    {
                        $this->logMessage( "{$defenderState['data']->name} was about to be pinned, but they're still in it! Unbelievable resilience!" );
                    }

                }
                else
                {
                    if ( !$isBulk )
                    {
                        $this->logMessage( "{$defenderState['data']->name} has been pinned!" );
                    }

                    $winner = $attackerState['data'];
                }
            }
        }

        if ( !$winner )
        {
            if ( !$isBulk )
            {
                $this->logMessage( "--- Match End: The time limit has expired. ---" );
            }

            if ( $matchState[$wrestler1->wrestler_id]['hp'] == $matchState[$wrestler2->wrestler_id]['hp'] )
            {
                $winner = 'draw';
            }
            else
            {
                $winner = ( $matchState[$wrestler1->wrestler_id]['hp'] > $matchState[$wrestler2->wrestler_id]['hp'] ) ? $wrestler1 : $wrestler2;
            }
        }

        if ( is_object( $winner ) && !$isBulk )
        {
            $this->logMessage( "--- The winner is {$winner->name}! ---" );
        }

        return [
            'winner' => $winner,
            'log'    => $this->matchLog,
        ];
    }

    /**
     * @param $attackerState
     * @param $defenderState
     * @param $move
     * @param $isBulk
     */
    private function calculateDamage( $attackerState, $defenderState, $move, $isBulk = false )
    {
        $minDamage     = 0;
        $maxDamage     = 0;
        $decodedDamage = json_decode( $move->min_damage, true );

        if ( json_last_error() === JSON_ERROR_NONE && isset( $decodedDamage['min'] ) && isset( $decodedDamage['max'] ) )
        {
            $minDamage = (int) $decodedDamage['min'];
            $maxDamage = (int) $decodedDamage['max'];
        }
        else
        {
            $minDamage = (int) $move->min_damage;
            $maxDamage = (int) $move->max_damage;
        }

        $baseDamage         = rand( $minDamage, $maxDamage );
        $attackerStatValue  = $attackerState['data']->{$move->stat} ?? 50;
        $statBonus          = $attackerStatValue * 0.5;
        $sizeModifier       = $this->getSizeModifier( $attackerState['data'], $defenderState['data'], $move->type );
        $grossDamage        = ( $baseDamage + $statBonus ) * $sizeModifier;
        $toughnessReduction = $defenderState['data']->toughness * 0.25;
        $finalDamage        = ceil( $grossDamage - $toughnessReduction );

        // Apply Traits
        if ( in_array( 'Brawler', $attackerState['data']->traits ) && $move->type === 'strike' && rand( 1, 100 ) <= 10 )
        {
            $finalDamage *= 1.5; // 50% critical hit bonus
            if ( !$isBulk )
            {
                $this->logMessage( "CRITICAL HIT!" );
            }

        }
        if ( in_array( 'Powerhouse', $attackerState['data']->traits ) && $move->type === 'grapple' )
        {
            $finalDamage *= 1.1; // 10% powerhouse bonus
        }
        if ( in_array( 'Submission Specialist', $attackerState['data']->traits ) && $move->submissionAttemptChance > 0 )
        {
            $finalDamage *= 1.2; // 20% submission bonus
        }
        if ( in_array( 'Brick Wall', $defenderState['data']->traits ) )
        {
            $finalDamage *= 0.90; // 10% damage reduction
        }

        // Apply comeback damage modifier if active
        if ( isset( $attackerState['damage_modifier'] ) )
        {
            $finalDamage *= $attackerState['damage_modifier'];
        }

        return max( 1, (int) round( $finalDamage ) );
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

        $staminaPercentage = $attackerState['stamina'] > 0 ? $attackerState['stamina'] / ( $attackerState['data']->stamina * 10 ) : 0;
        $staminaPenalty    = ( $staminaPercentage < 0.3 ) ? 20 : ( ( $staminaPercentage < 0.5 ) ? 10 : 0 );

        $finalChance = $baseChance + $statBonus - $staminaPenalty;

        if ( $move->type === 'highFlying' && in_array( 'High-Flyer', $attackerState['data']->traits ) )
        {
            $finalChance += 10;
        }
        if ( $move->type === 'grapple' && in_array( 'Giant', $defenderState['data']->traits ) )
        {
            $finalChance -= 15;
        }
        if ( $move->submissionAttemptChance > 0 && in_array( 'Submission Specialist', $attackerState['data']->traits ) )
        {
            $finalChance += 10;
        }

        return max( 5, min( 95, $finalChance ) );
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

            $state[$wrestler->wrestler_id] = [
                'hp'              => $initialHp,
                'stamina'         => $initialStamina,
                'momentum'        => 50,
                'data'            => $wrestler,
                'stunned'         => 0,
                'damage_modifier' => 1.0,
            ];
        }
        return $state;
    }

    private function loadAllMoves()
    {
        $apiModel       = new \App\Model\ApiModel( $this->app );
        $this->allMoves = $apiModel->getAllMoves();
    }

    /**
     * @param $message
     */
    private function logMessage( $message )
    {
        $this->matchLog[] = $message;
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
     * @param $attackerState
     * @return mixed
     */
    private function selectMove( $attackerState )
    {
        $wrestlerMoves = json_decode( $attackerState['data']->moves, true );

        if ( empty( $wrestlerMoves ) || !is_array( $wrestlerMoves ) || count( $wrestlerMoves ) === 0 )
        {
            return (object) [
                'move_name'    => 'Struggle', 'baseHitChance' => 1.0, 'stat'                         => 'strength',
                'stamina_cost' => 10, 'momentumGain'          => 2, 'min_damage'                     => '{"min": 5, "max": 10}',
                'max_damage'   => 0, 'type'                   => 'strike', 'submissionAttemptChance' => 0,
            ];
        }

        $allWrestlerMoveNames = array_merge( ...array_values( $wrestlerMoves ) );

        if ( empty( $allWrestlerMoveNames ) )
        {
            return (object) [
                'move_name'    => 'Struggle', 'baseHitChance' => 1.0, 'stat'                         => 'strength',
                'stamina_cost' => 10, 'momentumGain'          => 2, 'min_damage'                     => '{"min": 5, "max": 10}',
                'max_damage'   => 0, 'type'                   => 'strike', 'submissionAttemptChance' => 0,
            ];
        }

        $fullMoveDetails = array_filter( $this->allMoves, function ( $move ) use ( $allWrestlerMoveNames )
        {
            return in_array( $move->move_name, $allWrestlerMoveNames );
        } );

        $staminaCostModifier = in_array( 'Workhorse', $attackerState['data']->traits ) ? 0.85 : 1.0;
        $executableMoves     = array_filter( $fullMoveDetails, function ( $move ) use ( $attackerState, $staminaCostModifier )
        {
            return $attackerState['stamina'] >= ( $move->stamina_cost * $staminaCostModifier );
        } );

        if ( empty( $executableMoves ) )
        {
            return null;
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
     * @param $attacker
     * @param $defender
     * @param $moveType
     * @return mixed
     */
    private function getSizeModifier( $attacker, $defender, $moveType )
    {
        $weightDifference = $attacker->weight - $defender->weight;
        $modifier         = 1.0;

        if ( $moveType === 'grapple' || $moveType === 'strike' )
        {
            if ( $weightDifference > 50 )
            {
                $modifier += 0.15;
            }
            elseif ( $weightDifference > 20 )
            {
                $modifier += 0.07;
            }
        }

        if ( $moveType === 'highFlying' )
        {
            if ( $weightDifference < -50 )
            {
                $modifier += 0.15;
            }
            elseif ( $weightDifference < -20 )
            {
                $modifier += 0.07;
            }
        }

        return $modifier;
    }

    /**
     * @param $wrestler1
     * @param $wrestler2
     * @param $simCount
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
            $result = $this->simulateMatch( $wrestler1, $wrestler2, true );

            if ( is_object( $result['winner'] ) )
            {
                $winCounts[$result['winner']->name]++;
            }
            else
            {
                $winCounts['draw']++;
            }
        }

        $probabilities = [];
        $moneylineOdds = [];
        foreach ( $winCounts as $name => $wins )
        {
            if ( $simCount > 0 )
            {
                $probability          = $wins / $simCount;
                $probabilities[$name] = $probability;
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
     * @param $probability
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
}
