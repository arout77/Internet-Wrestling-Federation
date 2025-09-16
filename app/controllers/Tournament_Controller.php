<?php

namespace App\Controller;

use \Exception;
use \PDO;
use \Src\Controller\Base_Controller;

class Tournament_Controller extends Base_Controller
{
    public function index()
    {
        // This method now only displays the tournament selection page.
        // It no longer pre-builds a default tournament bracket.
        $this->template->render(
            'tournament/index.html.twig',
            [
                'event_name'   => 'Choose Your Tournament',
                'event_slogan' => 'Select a bracket to begin',
                'matchups'     => [], // Pass an empty array to hide the bracket
            ]
        );
    }

    /**
     * Calculates win probability by running a batch of simulations.
     * This provides a more accurate prediction than simple stat comparison.
     *
     * @param object $wrestler1
     * @param object $wrestler2
     * @return array
     */
    private function calculateOdds( $wrestler1, $wrestler2 )
    {
        $simulatorModel   = $this->model( 'Simulator' );
        $simulation_count = 100; // Number of simulations to run for an accurate percentage
        $wrestler1_wins   = 0;

        for ( $i = 0; $i < $simulation_count; $i++ )
        {
            $result = $simulatorModel->start_simulation( $wrestler1->wrestler_id, $wrestler2->wrestler_id );
            if ( isset( $result['winner'] ) && is_object( $result['winner'] ) && $result['winner']->wrestler_id == $wrestler1->wrestler_id )
            {
                $wrestler1_wins++;
            }
        }

        $odds1 = round( ( $wrestler1_wins / $simulation_count ) * 100 );
        $odds2 = 100 - $odds1;

        return ['wrestler1' => $odds1, 'wrestler2' => $odds2];
    }

    /**
     * API endpoint for purchasing and retrieving match odds.
     */
    public function get_odds()
    {
        $this->db->beginTransaction();
        try {
            if ( !isset( $_SESSION['user_id'] ) )
            {
                $this->json( ['success' => false, 'message' => 'You must be logged in.'], 403 );
            }

            $postData     = json_decode( file_get_contents( 'php://input' ), true );
            $tournamentId = (int) ( $postData['tournament_id'] ?? 0 );
            $userId       = $_SESSION['user_id'];

            $userModel = $this->model( 'User' );
            if ( $userModel->getGold( $userId ) < 1 )
            {
                $this->json( ['success' => false, 'message' => 'Not enough gold! (Cost: 1 Gold)'] );
            }

            $userModel->updateGold( $userId, -1 );

            $stmt = $this->db->prepare( "SELECT * FROM tournaments WHERE id = :id AND user_id = :user_id" );
            $stmt->execute( [':id' => $tournamentId, ':user_id' => $userId] );
            $tournament = $stmt->fetch( PDO::FETCH_OBJ );

            if ( !$tournament )
            {
                $this->json( ['success' => false, 'message' => 'Tournament not found.'], 404 );
            }

            $wrestlerIdsInTournament = json_decode( $tournament->wrestler_ids ?? '[]' );
            $matchups                = array_chunk( $wrestlerIdsInTournament, 2 );
            $apiModel                = $this->model( 'Api' );
            $odds_data               = [];

            foreach ( $matchups as $match )
            {
                $wrestler1 = $apiModel->getWrestlerById( $match[0] );
                $wrestler2 = $apiModel->getWrestlerById( $match[1] );
                if ( $wrestler1 && $wrestler2 )
                {
                    $odds        = $this->calculateOdds( $wrestler1, $wrestler2 );
                    $odds_data[] = [
                        'wrestler1' => ['name' => $wrestler1->name],
                        'wrestler2' => ['name' => $wrestler2->name],
                        'odds'      => $odds,
                    ];
                }
            }

            $this->db->commit();
            $this->json( ['success' => true, 'odds_data' => $odds_data] );

        }
        catch ( Exception $e )
        {
            $this->db->rollBack();
            $this->json( ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500 );
        }
    }

    /**
     * @param $percentage
     */
    // This method is no longer used by the index() action but is kept for potential future use.
    private function convertToMoneyline( $percentage )
    {
        if ( $percentage <= 0 )
        {
            return '+9999';
        }

        if ( $percentage >= 100 )
        {
            return '-9999';
        }

        if ( $percentage == 50 )
        {
            return '-110';
        }

        $vigFactor = 1.10;
        if ( $percentage > 50 )
        {
            $moneyline = -( $percentage / ( 100 - $percentage ) ) * 100;
            return (string) round( $moneyline * $vigFactor );
        }
        else
        {
            $moneyline = ( ( 100 - $percentage ) / $percentage ) * 100;
            return '+' . round( $moneyline / $vigFactor );
        }
    }

    /**
     * @param $data
     * @param $statusCode
     */
    private function json( $data, $statusCode = 200 )
    {
        http_response_code( $statusCode );
        header( 'Content-Type: application/json' );
        echo json_encode( $data );
        exit;
    }

    public function start()
    {
        $this->db->beginTransaction();
        try {
            if ( !isset( $_SESSION['user_id'] ) )
            {
                $this->json( ['success' => false, 'message' => 'You must be logged in to play.'], 403 );
            }

            $userId    = $_SESSION['user_id'];
            $userModel = $this->model( 'User' );
            if ( $userModel->getGold( $userId ) < 1 )
            {
                $this->json( ['success' => false, 'message' => 'Not enough gold to enter! (Cost: 1 Gold)'] );
            }

            $userModel->updateGold( $userId, -1 );

            $postData     = json_decode( file_get_contents( 'php://input' ), true );
            $wrestler_ids = $postData['wrestler_ids'] ?? null;

            if ( empty( $wrestler_ids ) )
            {
                // Fallback for standard tournament if no specific IDs are sent
                $apiModel      = $this->model( 'Api' );
                $all_wrestlers = $apiModel->get_all_wrestlers();
                shuffle( $all_wrestlers );
                $tournament_wrestlers = array_slice( $all_wrestlers, 0, 32 );
                $wrestler_ids         = array_map( fn( $w ) => $w->wrestler_id, $tournament_wrestlers );
            }

            $initial_size = count( $wrestler_ids );

            $stmt = $this->db->prepare( "INSERT INTO tournaments (user_id, wrestler_ids, initial_size) VALUES (?, ?, ?)" );
            $stmt->execute( [$userId, json_encode( $wrestler_ids ), $initial_size] );

            $tournamentId = $this->db->lastInsertId();
            $this->db->commit();
            $this->json( ['success' => true, 'message' => 'Tournament started!', 'tournament_id' => $tournamentId] );
        }
        catch ( Exception $e )
        {
            $this->db->rollBack();
            $this->json( ['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500 );
        }
    }

    public function simulate()
    {
        $this->db->beginTransaction();
        try {
            if ( !isset( $_SESSION['user_id'] ) )
            {
                $this->json( ['success' => false, 'message' => 'User not logged in.'], 403 );
            }

            $postData     = json_decode( file_get_contents( 'php://input' ), true );
            $tournamentId = (int) ( $postData['tournament_id'] ?? 0 );
            $userPicks    = $postData['picks'];

            $stmt = $this->db->prepare( "SELECT id, user_id, wrestler_ids, initial_size, current_round, user_picks FROM tournaments WHERE id = :id AND user_id = :user_id FOR UPDATE" );
            $stmt->execute( [':id' => $tournamentId, ':user_id' => $_SESSION['user_id']] );
            $tournament = $stmt->fetch( PDO::FETCH_OBJ );

            if ( !$tournament )
            {
                $this->json( ['success' => false, 'message' => 'Tournament not found.'], 404 );
            }

            $wrestlerIdsInTournament = json_decode( $tournament->wrestler_ids ?? '[]' );
            $simulatorModel          = $this->model( 'Simulator' );
            $apiModel                = $this->model( 'Api' );
            $actualWinners           = [];
            $all_correct             = true;

            $incorrect_picks_data = [];

            $matchups = array_chunk( $wrestlerIdsInTournament, 2 );

            foreach ( $matchups as $index => $match )
            {
                $simulationResult = $simulatorModel->start_simulation( $match[0], $match[1] );
                $winnerId         = is_object( $simulationResult['winner'] ) ? $simulationResult['winner']->wrestler_id : $match[rand( 0, 1 )];

                $actualWinners[$index] = $winnerId;
                $userPickId            = $userPicks[(string) $index] ?? null;

                if ( $userPickId != $winnerId )
                {
                    $all_correct = false;
                    if ( $userPickId )
                    {
                        $incorrect_picks_data[] = [
                            'user_pick'     => $apiModel->getWrestlerById( $userPickId ),
                            'actual_winner' => $apiModel->getWrestlerById( $winnerId ),
                        ];
                    }
                }
            }

            $currentUserPicks                                       = json_decode( $tournament->user_picks ?? '[]', true );
            $currentUserPicks['round' . $tournament->current_round] = $userPicks;
            $updateStmt                                             = $this->db->prepare( "UPDATE tournaments SET user_picks = :picks WHERE id = :id" );
            $updateStmt->execute( [':picks' => json_encode( $currentUserPicks ), ':id' => $tournamentId] );

            $response = ['success' => true, 'actual_winners' => array_values( $actualWinners )];

            $winners_data = [];
            foreach ( array_values( $actualWinners ) as $winnerId )
            {
                $winners_data[] = $apiModel->getWrestlerById( $winnerId );
            }
            $response['winners_data'] = $winners_data;

            $response['incorrect_picks_data'] = $incorrect_picks_data;

            if ( $all_correct )
            {
                $response['all_correct']     = true; // ** THE FIX IS HERE **
                $wrestler_ids_for_next_round = array_values( $actualWinners );

                if ( count( $wrestler_ids_for_next_round ) === 1 )
                {
                    $userModel = $this->model( 'User' );
                    $reward    = ( $tournament->initial_size == 32 ) ? 100 : 50;
                    $userModel->updateGold( $_SESSION['user_id'], $reward );
                    $response['tournament_winner'] = $apiModel->getWrestlerById( $wrestler_ids_for_next_round[0] );
                    $response['message']           = "Congratulations! You correctly picked all winners and won {$reward} Gold!";
                }
                else
                {
                    $stmt = $this->db->prepare( "UPDATE tournaments SET current_round = current_round + 1, wrestler_ids = :wrestler_ids WHERE id = :id" );
                    $stmt->execute( [':wrestler_ids' => json_encode( $wrestler_ids_for_next_round ), ':id' => $tournamentId] );
                    $response['message'] = 'Congratulations! You picked all winners correctly!';

                    $nextRoundWrestlers = [];
                    foreach ( $wrestler_ids_for_next_round as $id )
                    {
                        $nextRoundWrestlers[] = $apiModel->getWrestlerById( $id );
                    }
                    $response['next_round_matchups'] = $nextRoundWrestlers;
                }
            }
            else
            {
                $response['all_correct']  = false;
                $response['can_continue'] = ( $tournament->current_round == 1 );
                $response['message']      = 'You had one or more incorrect picks.';
            }

            $this->db->commit();
            $this->json( $response );
        }
        catch ( Exception $e )
        {
            $this->db->rollBack();
            $this->json( ['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()], 500 );
        }
    }

    public function payToContinue()
    {
        $this->db->beginTransaction();
        try {
            if ( !isset( $_SESSION['user_id'] ) )
            {
                $this->json( ['success' => false, 'message' => 'User not logged in.'], 403 );
            }

            $userId       = $_SESSION['user_id'];
            $postData     = json_decode( file_get_contents( 'php://input' ), true );
            $tournamentId = (int) ( $postData['tournament_id'] ?? 0 );

            $stmt = $this->db->prepare( "SELECT * FROM tournaments WHERE id = :id AND user_id = :user_id FOR UPDATE" );
            $stmt->execute( [':id' => $tournamentId, ':user_id' => $userId] );
            $tournament = $stmt->fetch( PDO::FETCH_OBJ );

            if ( !$tournament || $tournament->current_round != 1 )
            {
                $this->json( ['success' => false, 'message' => 'Not eligible to continue.'], 400 );
            }

            $userModel = $this->model( 'User' );
            if ( $userModel->getGold( $userId ) < 3 )
            {
                $this->json( ['success' => false, 'message' => 'Not enough gold to continue! (Cost: 3 Gold)'] );
            }

            $userModel->updateGold( $userId, -3 );

            $wrestlerIdsInTournament = json_decode( $tournament->wrestler_ids ?? '[]' );
            $simulatorModel          = $this->model( 'Simulator' );
            $apiModel                = $this->model( 'Api' );
            $actualWinners           = [];
            $matchups                = array_chunk( $wrestlerIdsInTournament, 2 );

            foreach ( $matchups as $match )
            {
                $simulationResult = $simulatorModel->start_simulation( $match[0], $match[1] );
                if ( isset( $simulationResult['winner'] ) )
                {
                    $actualWinners[] = $simulationResult['winner']->wrestler_id;
                }
            }

            $stmt = $this->db->prepare( "UPDATE tournaments SET current_round = 2, wrestler_ids = :wrestler_ids WHERE id = :id" );
            $stmt->execute( [':wrestler_ids' => json_encode( $actualWinners ), ':id' => $tournamentId] );

            if ( $stmt->rowCount() === 0 )
            {
                throw new Exception( "Failed to save progress: The tournament record was not updated in the database." );
            }

            $nextRoundWrestlers = [];
            foreach ( $actualWinners as $id )
            {
                $nextRoundWrestlers[] = $apiModel->getWrestlerById( $id );
            }

            $this->db->commit();

            $this->json( [
                'success'             => true,
                'message'             => 'Payment successful! Advancing to Round 2!',
                'next_round_matchups' => $nextRoundWrestlers,
            ] );
        }
        catch ( Exception $e )
        {
            $this->db->rollBack();
            $this->json( ['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500 );
        }
    }

    public function giants()
    {
        $this->create_themed_tournament( 'Battle of the Giants', '300+ Pounders Only', 'get_wrestlers_by_weight', 300 );
    }

    public function technicians()
    {
        $this->create_themed_tournament( 'Technical Masters', 'Wrestlers with 92+ Technical Ability', 'get_wrestlers_by_technical', 92 );
    }

    public function brawlers()
    {
        $this->create_themed_tournament( 'Bar Room Brawlers', 'Wrestlers with 93+ Brawling Ability', 'get_wrestlers_by_brawling', 93 );
    }

    public function aerialists()
    {
        $this->create_themed_tournament( 'Aerial Assault', 'Wrestlers with 85+ Aerial Ability', 'get_wrestlers_by_aerial', 85 );
    }

    public function strongmen()
    {
        $this->create_themed_tournament( 'Strongman Competition', 'Wrestlers with 94+ Strength', 'get_wrestlers_by_strength', 94 );
    }

    /**
     * A generic helper method to create and render a themed tournament.
     */
    private function create_themed_tournament( $title, $slogan, $fetch_method, $value )
    {
        $model     = $this->model( 'Api' );
        $wrestlers = $model->$fetch_method( $value );

        shuffle( $wrestlers );

        $participant_count = ( count( $wrestlers ) >= 32 ) ? 32 : 16;

        if ( count( $wrestlers ) < 16 )
        {
            $participant_count = count( $wrestlers );
            if ( $participant_count % 2 != 0 )
            {
                $participant_count--;
            }
        }

        $tournament_wrestlers = array_slice( $wrestlers, 0, $participant_count );

        $matchups = [];
        for ( $i = 0; $i < count( $tournament_wrestlers ) / 2; $i++ )
        {
            $wrestler1                   = $tournament_wrestlers[$i * 2];
            $wrestler2                   = $tournament_wrestlers[( $i * 2 ) + 1];
            $odds                        = $this->calculateOdds( $wrestler1, $wrestler2 );
            $odds['wrestler1_moneyline'] = $this->convertToMoneyline( $odds['wrestler1'] );
            $odds['wrestler2_moneyline'] = $this->convertToMoneyline( $odds['wrestler2'] );
            $matchups[]                  = [
                'wrestler1' => $wrestler1,
                'wrestler2' => $wrestler2,
                'odds'      => $odds,
            ];
        }

        $bracket_class = ( count( $matchups ) <= 8 ) ? 'bracket-16' : 'bracket-32';

        $this->template->render(
            'tournament/index.html.twig',
            [
                'event_name'    => $title,
                'event_slogan'  => $slogan,
                'wrestlers'     => $tournament_wrestlers,
                'matchups'      => $matchups,
                'bracket_class' => $bracket_class,
            ]
        );
    }
}
