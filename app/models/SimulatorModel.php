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
     * Extracts a flat list of move names from a wrestler's data object.
     * @param object $wrestler The wrestler object containing the moves structure.
     * @return array A flat array of move names.
     */
    public function getWrestlerMoveNames( $wrestler )
    {
        $moveNames = [];
        // Ensure moves property exists and is an object or array
        if ( isset( $wrestler->moves ) && ( is_object( $wrestler->moves ) || is_array( $wrestler->moves ) ) )
        {
            foreach ( $wrestler->moves as $moveType => $moves )
            {
                if ( is_array( $moves ) )
                {
                    foreach ( $moves as $move )
                    {
                        // Check if move is an object and has the move_name property
                        if ( is_object( $move ) && isset( $move->move_name ) )
                        {
                            $moveNames[] = $move->move_name;
                        }
                    }
                }
            }
        }
        return $moveNames;
    }

    /**
     * Fetches all submission moves from the database.
     *
     * @return array
     */
    public function getSubmissionMoves()
    {
        $sql   = "SELECT * FROM all_moves WHERE type = 'submission'";
        $query = $this->db->query( $sql );
        return $query->execute();
    }

    /**
     * Calculates performance modifiers based on a wrestler's level.
     * @param int $level The wrestler's level.
     * @return array An array containing damage and hit chance modifiers.
     */
    public function get_level_modifiers( $level )
    {
        $level     = (int) $level;
        $modifiers = [
            'damage_modifier'     => 1.0,
            'hit_chance_modifier' => 0,
        ];

        if ( $level >= 90 )
        {
            $modifiers['damage_modifier']     = 1.25;
            $modifiers['hit_chance_modifier'] = 10;
        }
        elseif ( $level >= 75 )
        {
            $modifiers['damage_modifier']     = 1.15;
            $modifiers['hit_chance_modifier'] = 5;
        }
        elseif ( $level >= 50 )
        {
            $modifiers['damage_modifier']     = 1.10;
            $modifiers['hit_chance_modifier'] = 2;
        }
        elseif ( $level >= 25 )
        {
            $modifiers['damage_modifier']     = 1.05;
            $modifiers['hit_chance_modifier'] = 1;
        }

        return $modifiers;
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

        $this->run_full_simulation();

        return [
            'winner' => $this->winner,
            'loser'  => $this->loser,
            'log'    => $this->matchLog,
        ];
    }

    private function run_full_simulation()
    {
        $result       = $this->simulateMatch( $this->wrestler1_data, $this->wrestler2_data );
        $this->winner = $result['winner'];
        $this->loser  = ( $result['winner'] && is_object( $result['winner'] ) && $result['winner']->wrestler_id == $this->wrestler1_data->wrestler_id ) ? $this->wrestler2_data : $this->wrestler1_data;
    }

    /**
     * @param $attackerState
     */
    private function checkForDisqualification( &$attackerState )
    {
        $dqChance = 0.15;
        $reasons  = [
            "using a foreign object",
            "ignoring the referee's 5-count",
            "performing an illegal maneuver",
            "attacking a non-legal opponent",
        ];

        if ( in_array( 'Dirty Player', $attackerState['data']->traits ) )
        {
            $dqChance += 0.15;
        }
        if ( in_array( 'Brawler', $attackerState['data']->traits ) )
        {
            $dqChance += 0.05;
        }
        if ( in_array( 'Showman', $attackerState['data']->traits ) )
        {
            $dqChance += 0.15;
        }
        if ( $attackerState['data']->brawlingAbility >= 90 )
        {
            $dqChance += 0.05;
        }

        if ( ( rand( 1, 10000 ) / 100 ) <= $dqChance )
        {
            $reason = $reasons[array_rand( $reasons )];
            $this->logMessage( "{$attackerState['data']->name} has been disqualified for {$reason}!" );
            return true;
        }

        return false;
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

            if ( $this->checkForDisqualification( $attackerState ) )
            {
                $winner = $defenderState['data'];
                break;
            }

            if ( $attackerState['stunned'] > 0 )
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} is stunned and cannot make a move!" );
                }

                $attackerState['stunned']--;
                continue;
            }

            if ( !$isBulk )
            {
                $this->logMessage( "{$attackerState['data']->name} is on the offensive!" );
            }

            $attackerMoveNames = $this->getWrestlerMoveNames( $attackerState['data'] );
            $move              = $this->selectMove( $attackerState, $defenderState, $attackerMoveNames );

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

            if ( $move->type === 'grapple' && in_array( 'Dirty Player', $defenderState['data']->traits ) && rand( 1, 100 ) <= 5 )
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "{$attackerState['data']->name} goes for a grapple, but {$defenderState['data']->name} plays dirty with a LOW BLOW!" );
                }

                $attackerState['stunned'] = 1;
                $defenderState['momentum'] += 20;
                continue;
            }

            if ( $roll <= $hitChance )
            {
                $damage = $this->calculateDamage( $attackerState, $defenderState, $move, $isBulk );
                $defenderState['hp'] -= $damage;
                $staminaCost = in_array( 'Workhorse', $attackerState['data']->traits ) ? $move->stamina_cost * 0.85 : $move->stamina_cost;
                $attackerState['stamina'] -= $staminaCost;
                $momentumGain = $move->momentumGain;
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
                if ( $damage > 50 )
                {
                    $defenderState['stunned'] = 1;
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

            $comebackThreshold = $attackerState['data']->baseHp * 0.25;
            if ( $attackerState['hp'] <= $comebackThreshold && !isset( $this->comeback_activated[$attackerId] ) )
            {
                if ( in_array( 'Comeback Kid', $attackerState['data']->traits ) )
                {
                    $attackerState['momentum'] += 50;
                    $attackerState['comeback_damage_modifier'] = 1.20;
                    $attackerState['comeback_turns']           = 3;
                    $this->comeback_activated[$attackerId]     = true;
                    if ( !$isBulk )
                    {
                        $this->logMessage( "{$attackerState['data']->name} is hurt but fires up! The crowd is going wild!" );
                    }

                }
            }

            if ( isset( $attackerState['comeback_turns'] ) && $attackerState['comeback_turns'] > 0 )
            {
                $attackerState['comeback_turns']--;
                if ( $attackerState['comeback_turns'] == 0 )
                {
                    $attackerState['comeback_damage_modifier'] = 1.0;
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

        return ['winner' => $winner, 'log' => $this->matchLog];
    }

    /**
     * @param $attackerState
     * @param $defenderState
     * @param $move
     * @param $isBulk
     */
    private function calculateDamage( $attackerState, $defenderState, $move, $isBulk = false )
    {
        $minDamage         = 0;
        $maxDamage         = 0;
        $staminaPercentage = $attackerState['stamina'] > 0 ? $attackerState['stamina'] / ( $attackerState['data']->stamina * 10 ) : 0;

        $minDamageJson = is_string( $move->min_damage ) ? $move->min_damage : '{}';
        $decodedDamage = json_decode( $minDamageJson, true );

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

        $wrestlerMovesJson = is_string( $attackerState['data']->moves ) ? $attackerState['data']->moves : '{}';
        $wrestlerMoves     = json_decode( $wrestlerMovesJson, true );
        $finisherName      = $wrestlerMoves['finisher'][0] ?? null;

        if ( $finisherName && $move->move_name === $finisherName )
        {
            $minDamage = max( 50, $minDamage );
            $maxDamage = max( 70, $maxDamage );
        }

        $baseDamage        = rand( $minDamage, $maxDamage );
        $attackerStatValue = $attackerState['data']->{$move->stat} ?? 50;
        $statBonus         = $attackerStatValue * 0.10;
        $sizeModifier      = $this->getSizeModifier( $attackerState['data'], $defenderState['data'], $move->type );
        $grossDamage       = ( $baseDamage + $statBonus ) * $sizeModifier;

        $finalDamage = $grossDamage;
        $finalDamage *= $attackerState['level_damage_modifier'];

        if ( $move->type === 'grapple' )
        {
            if ( $attackerState['data']->technicalAbility >= 95 )
            {
                $finalDamage *= 1.15;
            }
            if ( $attackerState['data']->strength >= 95 )
            {
                $finalDamage *= 1.15;
            }
        }

        if ( $staminaPercentage < 0.3 )
        {
            $finalDamage *= 0.5;
        }
        elseif ( $staminaPercentage < 0.5 )
        {
            $finalDamage *= 0.75;
        }

        if ( $move->type === 'highFlying' && $attackerState['data']->aerialAbility >= 95 )
        {
            $finalDamage *= 1.15;
        }

        if ( $attackerState['data']->brawlingAbility >= 95 )
        {
            $finalDamage *= 1.15;
        }

        if ( in_array( 'Brawler', $attackerState['data']->traits ) && $move->type === 'strike' && rand( 1, 100 ) <= 10 )
        {
            $finalDamage *= 1.5;
            if ( !$isBulk )
            {
                $this->logMessage( "CRITICAL HIT!" );
            }
        }
        if ( in_array( 'Powerhouse', $attackerState['data']->traits ) && $move->type === 'grapple' )
        {
            $finalDamage *= 1.1;
        }
        if ( in_array( 'Submission Specialist', $attackerState['data']->traits ) && $move->submissionAttemptChance > 0 )
        {
            $finalDamage *= 1.2;
        }
        if ( in_array( 'Brick Wall', $defenderState['data']->traits ) )
        {
            $finalDamage *= 0.90;
        }

        if ( property_exists( $attackerState['data'], 'archetype' ) && $attackerState['data']->archetype )
        {
            switch ( $attackerState['data']->archetype )
            {
                case 'powerhouse':
                    if ( $move->type === 'grapple' && $defenderState['data']->weight < 250 )
                    {
                        $finalDamage *= 1.10;
                        if ( !$isBulk )
                        {
                            $this->logMessage( "{$attackerState['data']->name}'s Powerhouse Archetype adds extra impact!" );
                        }
                    }
                    break;
                case 'high-flyer':
                    if ( $move->type === 'highFlying' )
                    {
                        $attackerState['momentum'] += 5;
                        if ( !$isBulk )
                        {
                            $this->logMessage( "{$attackerState['data']->name}'s High-Flyer Archetype builds momentum!" );
                        }
                    }
                    break;
            }
        }

        if ( isset( $attackerState['comeback_damage_modifier'] ) )
        {
            $finalDamage *= $attackerState['comeback_damage_modifier'];
        }

        $weightDifference = $defenderState['data']->weight - $attackerState['data']->weight;
        if ( ( $move->type === 'grapple' || $move->type === 'highFlying' ) && $weightDifference >= 100 )
        {
            $finalDamage *= 0.70;
        }

        $toughnessReductionPercent = $defenderState['data']->toughness * 0.1;
        if ( $defenderState['data']->toughness >= 95 )
        {
            $toughnessReductionPercent += 2;
        }
        $finalDamage *= ( 1 - ( $toughnessReductionPercent / 100 ) );

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

        if ( $staminaPercentage < 0.3 )
        {
            $defenderStatValue -= 20;
        }
        elseif ( $staminaPercentage < 0.5 )
        {
            $defenderStatValue -= 10;
        }

        $finalChance += $attackerState['level_hit_chance_modifier'];

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

        if ( property_exists( $defenderState['data'], 'archetype' ) && $defenderState['data']->archetype === 'technician' )
        {
            if ( $move->type === 'grapple' )
            {
                $finalChance -= 5;
            }
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
            $levelModifiers = $this->get_level_modifiers( $wrestler->lvl );

            $state[$wrestler->wrestler_id] = [
                'hp'                        => $initialHp,
                'stamina'                   => $initialStamina,
                'momentum'                  => 50,
                'data'                      => $wrestler,
                'stunned'                   => 0,
                'comeback_damage_modifier'  => 1.0,
                'level_damage_modifier'     => $levelModifiers['damage_modifier'],
                'level_hit_chance_modifier' => $levelModifiers['hit_chance_modifier'],
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
     * @param $defenderState
     * @param $attackerMoveNames
     * @return mixed
     */
    private function selectMove( $attackerState, $defenderState, $attackerMoveNames )
    {
        $wrestlerMoves = (array) ( $attackerState['data']->moves ?? [] );

        $fullMoveDetails = array_filter( $this->allMoves, fn( $move ) => in_array( $move->move_name, $attackerMoveNames ) );

        $staminaCostModifier = in_array( 'Workhorse', $attackerState['data']->traits ) ? 0.85 : 1.0;
        $executableMoves     = array_filter( $fullMoveDetails, fn( $move ) => $attackerState['stamina'] >= ( $move->stamina_cost * $staminaCostModifier ) );

        if ( empty( $executableMoves ) )
        {
            return null;
        }

        $executableMoves = array_filter( $executableMoves, function ( $move ) use ( $attackerState )
        {
            if ( isset( $move->weight_limit ) && $attackerState['data']->weight < $move->weight_limit )
            {
                return false;
            }
            return true;
        } );

        $finishers    = [];
        $finisherName = null;
        // **FIX STARTS HERE**
        if ( isset( $wrestlerMoves['finisher'] ) && !empty( $wrestlerMoves['finisher'] ) )
        {
            // Check if the finisher is an object with a move_name property
            if ( isset( $wrestlerMoves['finisher'][0]->move_name ) )
            {
                $finisherName = $wrestlerMoves['finisher'][0]->move_name;
            }
            // Check if the finisher is a simple string (for prospects)

            elseif ( is_string( $wrestlerMoves['finisher'][0] ) )
            {
                $finisherName = $wrestlerMoves['finisher'][0];
            }

            if ( $finisherName )
            {
                foreach ( $executableMoves as $move )
                {
                    if ( $move->move_name === $finisherName )
                    {
                        $finishers[] = $move;
                        break;
                    }
                }
            }
        }
        // **FIX ENDS HERE**

        $regularMoves = array_filter( $executableMoves, function ( $move ) use ( $finisherName )
        {
            return $move->move_name !== $finisherName;
        } );

        $movePool = [];

        $defenderInitialHp = $defenderState['data']->baseHp + ( $defenderState['data']->toughness * 10 );
        $finisherCondition = $attackerState['momentum'] >= 100 && $defenderState['hp'] <= ( $defenderInitialHp * 0.10 );
        if ( $finisherCondition && !empty( $finishers ) )
        {
            $movePool = array_merge( $movePool, array_fill( 0, 80, $finishers[0] ) );
        }

        if ( !empty( $regularMoves ) )
        {
            if ( property_exists( $attackerState['data'], 'archetype' ) )
            {
                switch ( $attackerState['data']->archetype )
                {
                    case 'technician':
                        if ( $defenderState['stamina'] < ( $defenderState['data']->stamina * 10 ) * 0.5 )
                        {
                            $submissionMoves = array_filter( $regularMoves, fn( $move ) => $move->type === 'submission' );
                            if ( !empty( $submissionMoves ) )
                            {
                                $movePool = array_merge( $movePool, array_fill( 0, 50, $submissionMoves[array_rand( $submissionMoves )] ) );
                            }
                        }
                        break;
                    case 'brawler':
                        if ( $attackerState['momentum'] > 75 )
                        {
                            $strikeMoves = array_filter( $regularMoves, fn( $move ) => $move->type === 'strike' );
                            if ( !empty( $strikeMoves ) )
                            {
                                $movePool = array_merge( $movePool, array_fill( 0, 50, $strikeMoves[array_rand( $strikeMoves )] ) );
                            }
                        }
                        break;
                    case 'high-flyer':
                        if ( $attackerState['hp'] > ( $attackerState['data']->baseHp + ( $attackerState['data']->toughness * 10 ) ) * 0.75 )
                        {
                            $highFlyingMoves = array_filter( $regularMoves, fn( $move ) => $move->type === 'highFlying' );
                            if ( !empty( $highFlyingMoves ) )
                            {
                                $movePool = array_merge( $movePool, array_fill( 0, 50, $highFlyingMoves[array_rand( $highFlyingMoves )] ) );
                            }
                        }
                        break;
                }
            }
            $movePool = array_merge( $movePool, array_fill( 0, 20, $regularMoves[array_rand( $regularMoves )] ) );
        }
        else
        {
            if ( empty( $movePool ) && !empty( $finishers ) )
            {
                $movePool[] = $finishers[0];
            }
        }

        if ( empty( $movePool ) )
        {
            return empty( $executableMoves ) ? null : $executableMoves[array_rand( $executableMoves )];
        }

        return $movePool[array_rand( $movePool )];
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
            if ( $weightDifference > 100 )
            {
                $modifier += 0.45;
            }
            elseif ( $weightDifference > 50 )
            {
                $modifier += 0.25;
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
     * @return mixed
     */
    public function runBulkSimulations( $wrestler1, $wrestler2, $simCount = 100 )
    {
        $isTagMatch = is_array( $wrestler1 ) && is_array( $wrestler2 );

        $winCounts = [];
        if ( $isTagMatch )
        {
            $winCounts['Team 1'] = 0;
            $winCounts['Team 2'] = 0;
        }
        else
        {
            $winCounts[$wrestler1->name] = 0;
            $winCounts[$wrestler2->name] = 0;
        }
        $winCounts['draw'] = 0;

        for ( $i = 0; $i < $simCount; $i++ )
        {
            $result = $isTagMatch
            ? $this->simulateTagMatch( $wrestler1, $wrestler2, true )
            : $this->simulateMatch( $wrestler1, $wrestler2, true );

            if ( $isTagMatch )
            {
                if ( $result['winner'] === 'Team 1' )
                {
                    $winCounts['Team 1']++;
                }
                elseif ( $result['winner'] === 'Team 2' )
                {
                    $winCounts['Team 2']++;
                }
                else
                {
                    $winCounts['draw']++;
                }
            }
            else
            {
                if ( is_object( $result['winner'] ) )
                {
                    $winCounts[$result['winner']->name]++;
                }
                else
                {
                    $winCounts['draw']++;
                }
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

        $response = ['wins' => $winCounts, 'probabilities' => $probabilities, 'moneyline' => $moneylineOdds];

        if ( $isTagMatch )
        {
            $response['team1_names'] = $wrestler1[0]->name . ' & ' . $wrestler1[1]->name;
            $response['team2_names'] = $wrestler2[0]->name . ' & ' . $wrestler2[1]->name;
        }

        return $response;
    }

    /**
     * @param $team1
     * @param $team2
     * @return mixed
     */
    public function start_tag_simulation( $team1, $team2 )
    {
        return $this->simulateTagMatch( $team1, $team2, false );
    }

    /**
     * @param $team1
     * @param $team2
     * @param $isBulk
     */
    private function simulateTagMatch( $team1, $team2, $isBulk = false )
    {
        $this->matchLog = [];
        $winner         = null;
        $turn           = 0;
        $maxTurns       = 250;
        $matchState     = $this->initializeTagMatchState( $team1, $team2 );

        if ( !$isBulk )
        {
            $team1Names = $team1[0]->name . ' & ' . $team1[1]->name;
            $team2Names = $team2[0]->name . ' & ' . $team2[1]->name;
            $this->logMessage( "--- Tag Match Start: {$team1Names} vs. {$team2Names} ---" );
        }

        while ( $turn < $maxTurns && $winner === null )
        {
            $turn++;
            if ( !$isBulk )
            {
                $this->logMessage( "--- Turn {$turn} ---" );
            }

            foreach ( array_merge( $team1, $team2 ) as $wrestler )
            {
                $initialStamina                                = $wrestler->stamina * 10;
                $recoveryRate                                  = $wrestler->staminaRecoveryRate;
                $matchState[$wrestler->wrestler_id]['stamina'] = min( $initialStamina, $matchState[$wrestler->wrestler_id]['stamina'] + $recoveryRate );
            }

            $activeTeam1 = array_filter( $team1, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 );
            $activeTeam2 = array_filter( $team2, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 );

            if ( empty( $activeTeam1 ) )
            {
                $winner = 'Team 2';
                break;
            }
            if ( empty( $activeTeam2 ) )
            {
                $winner = 'Team 1';
                break;
            }

            if ( rand( 0, 1 ) === 0 )
            {
                $attacker = $activeTeam1[array_rand( $activeTeam1 )];
                $defender = $activeTeam2[array_rand( $activeTeam2 )];
                $this->performAttack( $matchState, $attacker, $defender, $isBulk );

                if ( empty( array_filter( $team2, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 ) ) )
                {
                    $winner = 'Team 1';
                    break;
                }

                $activeTeam1 = array_filter( $team1, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 );
                $activeTeam2 = array_filter( $team2, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 );
                if ( !empty( $activeTeam2 ) && !empty( $activeTeam1 ) )
                {
                    $attacker = $activeTeam2[array_rand( $activeTeam2 )];
                    $defender = $activeTeam1[array_rand( $activeTeam1 )];
                    $this->performAttack( $matchState, $attacker, $defender, $isBulk );
                    if ( empty( array_filter( $team1, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 ) ) )
                    {
                        $winner = 'Team 2';
                        break;
                    }
                }
            }
            else
            {
                $attacker = $activeTeam2[array_rand( $activeTeam2 )];
                $defender = $activeTeam1[array_rand( $activeTeam1 )];
                $this->performAttack( $matchState, $attacker, $defender, $isBulk );

                if ( empty( array_filter( $team1, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 ) ) )
                {
                    $winner = 'Team 2';
                    break;
                }

                $activeTeam1 = array_filter( $team1, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 );
                $activeTeam2 = array_filter( $team2, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 );
                if ( !empty( $activeTeam1 ) && !empty( $activeTeam2 ) )
                {
                    $attacker = $activeTeam1[array_rand( $activeTeam1 )];
                    $defender = $activeTeam2[array_rand( $activeTeam2 )];
                    $this->performAttack( $matchState, $attacker, $defender, $isBulk );
                    if ( empty( array_filter( $team2, fn( $w ) => $matchState[$w->wrestler_id]['hp'] > 0 ) ) )
                    {
                        $winner = 'Team 1';
                        break;
                    }
                }
            }
        }

        if ( !$winner )
        {
            $winner = 'draw';
        }

        if ( !$isBulk )
        {
            if ( $winner === 'draw' )
            {
                $this->logMessage( "--- The match is a Draw! ---" );
            }
            else
            {
                $this->logMessage( "--- The winner is {$winner}! ---" );
            }
        }

        return ['winner' => $winner, 'log' => $this->matchLog];
    }

    /**
     * @param $matchState
     * @param $attacker
     * @param $defender
     * @param $isBulk
     */
    private function performAttack( &$matchState, $attacker, $defender, $isBulk )
    {
        $attackerMoves         = $this->getWrestlerMoveNames( $attacker );
        $allMoveNamesForAttack = $attackerMoves;

        $move = $this->selectMove( $matchState[$attacker->wrestler_id], $matchState[$defender->wrestler_id], $allMoveNamesForAttack );

        if ( $move )
        {
            if ( !$isBulk )
            {
                $this->logMessage( "{$attacker->name} attempts a {$move->move_name} on {$defender->name}..." );
            }

            $hitChance = $this->calculateHitChance( $matchState[$attacker->wrestler_id], $matchState[$defender->wrestler_id], $move );
            if ( rand( 1, 100 ) <= $hitChance )
            {
                $damage = $this->calculateDamage( $matchState[$attacker->wrestler_id], $matchState[$defender->wrestler_id], $move, $isBulk );
                $matchState[$defender->wrestler_id]['hp'] -= $damage;
                if ( !$isBulk )
                {
                    $this->logMessage( "It connects! {$damage} damage dealt." );
                    $this->logMessage( "{$defender->name} has " . max( 0, round( $matchState[$defender->wrestler_id]['hp'] ) ) . " HP remaining." );
                }
            }
            else
            {
                if ( !$isBulk )
                {
                    $this->logMessage( "...but {$defender->name} reverses it!" );
                }
            }
        }
        else
        {
            if ( !$isBulk )
            {
                $this->logMessage( "{$attacker->name} is too exhausted to make a move!" );
            }
        }
    }

    /**
     * @param $team1
     * @param $team2
     * @return mixed
     */
    private function initializeTagMatchState( $team1, $team2 )
    {
        $state = [];
        foreach ( array_merge( $team1, $team2 ) as $wrestler )
        {
            $state[$wrestler->wrestler_id] = $this->initializeMatchState( $wrestler, $wrestler )[$wrestler->wrestler_id];
        }
        return $state;
    }

    /**
     * @param $probability
     */
    private function calculateMoneyline( $probability )
    {
        if ( $probability <= 0 )
        {
            return '+9900';
        }
        if ( $probability >= 1 )
        {
            return '-99900';
        }
        if ( $probability < 0.5 )
        {
            $odds = ( 100 / $probability ) - 100;
            return '+' . min( 9900, round( $odds ) );
        }
        else
        {
            $odds = -100 / ( ( 100 / ( 100 * $probability ) ) - 1 );
            return round( $odds );
        }
    }
}
