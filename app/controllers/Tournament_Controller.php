<?php

namespace App\Controller;

use \Exception;
use \PDO;
use \Src\Controller\Base_Controller;

class Tournament_Controller extends Base_Controller
{
    public function index()
    {
        $model         = $this->model( 'Api' );
        $all_wrestlers = $model->get_all_wrestlers();

        shuffle( $all_wrestlers );
        $tournament_wrestlers = array_slice( $all_wrestlers, 0, 32 );

        $matchups = [];
        if ( count( $tournament_wrestlers ) === 32 )
        {
            for ( $i = 0; $i < 16; $i++ )
            {
                $wrestler1 = $tournament_wrestlers[$i * 2];
                $wrestler2 = $tournament_wrestlers[( $i * 2 ) + 1];

                $odds = $this->calculateOdds( $wrestler1, $wrestler2 );

                $odds['wrestler1_moneyline'] = $this->convertToMoneyline( $odds['wrestler1'] );
                $odds['wrestler2_moneyline'] = $this->convertToMoneyline( $odds['wrestler2'] );

                $matchups[] = [
                    'wrestler1' => $wrestler1,
                    'wrestler2' => $wrestler2,
                    'odds'      => $odds,
                ];
            }
        }

        $this->template->render(
            'tournament/index.html.twig',
            [
                'event_name'   => 'Chasing The Gold',
                'event_slogan' => 'Dollar or Less Entry Fees',
                'wrestlers'    => $tournament_wrestlers,
                'matchups'     => $matchups,
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
            if ( isset( $result['winner'] ) && $result['winner']->wrestler_id == $wrestler1->wrestler_id )
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
            $apiModel      = $this->model( 'Api' );
            $all_wrestlers = $apiModel->get_all_wrestlers();
            shuffle( $all_wrestlers );
            $tournament_wrestlers = array_slice( $all_wrestlers, 0, 32 );
            $wrestler_ids         = array_map( fn( $w ) => $w->wrestler_id, $tournament_wrestlers );
            $stmt                 = $this->db->prepare( "INSERT INTO tournaments (user_id, wrestler_ids) VALUES (?, ?)" );
            $stmt->execute( [$userId, json_encode( $wrestler_ids )] );
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

            $stmt = $this->db->prepare( "SELECT * FROM tournaments WHERE id = :id AND user_id = :user_id FOR UPDATE" );
            $stmt->execute( [':id' => $tournamentId, ':user_id' => $_SESSION['user_id']] );
            $tournament = $stmt->fetch( PDO::FETCH_OBJ );

            if ( !$tournament )
            {
                $this->json( ['success' => false, 'message' => 'Tournament not found.'], 404 );
            }

            $currentRound            = $tournament->current_round;
            $wrestlerIdsInTournament = json_decode( $tournament->wrestler_ids ?? '[]' );
            $simulatorModel          = $this->model( 'Simulator' );
            $apiModel                = $this->model( 'Api' );
            $actualWinners           = [];
            $all_correct             = true;
            $matchups                = array_chunk( $wrestlerIdsInTournament, 2 );

            foreach ( $matchups as $index => $match )
            {
                $simulationResult = $simulatorModel->start_simulation( $match[0], $match[1] );
                if ( !isset( $simulationResult['winner'] ) )
                {
                    throw new Exception( "Simulation failed for match index {$index}." );
                }

                $winnerId              = $simulationResult['winner']->wrestler_id;
                $actualWinners[$index] = $winnerId;
                if ( !isset( $userPicks[(string) $index] ) || $userPicks[(string) $index] != $winnerId )
                {
                    $all_correct = false;
                }
            }

            $currentUserPicks                          = json_decode( $tournament->user_picks ?? '[]', true );
            $currentUserPicks['round' . $currentRound] = $userPicks;
            $updateStmt                                = $this->db->prepare( "UPDATE tournaments SET user_picks = :picks WHERE id = :id" );
            $updateStmt->execute( [':picks' => json_encode( $currentUserPicks ), ':id' => $tournamentId] );

            $response = ['success' => true, 'actual_winners' => array_values( $actualWinners )];

            if ( $all_correct )
            {
                if ( $currentRound == 5 )
                {
                    $this->model( 'User' )->updateGold( $_SESSION['user_id'], 25 );
                    $response['tournament_winner'] = true;
                    $response['message']           = 'Congratulations! You won the tournament and earned 25 Gold!';
                }
                else
                {
                    $nextRoundWrestlerIds = array_values( $actualWinners );
                    $stmt                 = $this->db->prepare( "UPDATE tournaments SET current_round = current_round + 1, wrestler_ids = :wrestler_ids WHERE id = :id" );
                    $stmt->execute( [':wrestler_ids' => json_encode( $nextRoundWrestlerIds ), ':id' => $tournamentId] );
                    if ( $stmt->rowCount() === 0 )
                    {
                        throw new Exception( "Failed to advance tournament round: Database record was not updated." );
                    }

                    $response['all_correct'] = true;
                    $response['message']     = 'Perfect round! Advancing...';
                    $nextRoundWrestlers      = [];
                    foreach ( $nextRoundWrestlerIds as $id )
                    {
                        $nextRoundWrestlers[] = $apiModel->getWrestlerById( $id );
                    }
                    $response['next_round_matchups'] = $nextRoundWrestlers;
                }
            }
            else
            {
                $response['all_correct']  = false;
                $response['can_continue'] = ( $currentRound == 1 );
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
}
