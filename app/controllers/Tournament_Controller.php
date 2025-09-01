<?php

namespace App\Controller;

use \Exception;
use \PDO;
use \Src\Controller\Base_Controller;

class Tournament_Controller extends Base_Controller
{
    // ... [index, calculateOdds, convertToMoneyline, simulateMatch, json, and start methods remain the same as the last PDO version] ...

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
     * @param $wrestler1
     * @param $wrestler2
     */
    private function calculateOdds( $wrestler1, $wrestler2 )
    {
        $score1     = ( $wrestler1->strength ?? 60 ) + ( $wrestler1->technicalAbility ?? 60 ) + ( $wrestler1->brawlingAbility ?? 60 ) + ( $wrestler1->stamina ?? 60 ) + ( $wrestler1->toughness ?? 60 );
        $score2     = ( $wrestler2->strength ?? 60 ) + ( $wrestler2->technicalAbility ?? 60 ) + ( $wrestler2->brawlingAbility ?? 60 ) + ( $wrestler2->stamina ?? 60 ) + ( $wrestler2->toughness ?? 60 );
        $totalScore = $score1 + $score2;

        if ( $totalScore == 0 )
        {
            return ['wrestler1' => 50, 'wrestler2' => 50];
        }

        $odds1 = round( ( $score1 / $totalScore ) * 100 );
        $odds2 = 100 - $odds1;

        return ['wrestler1' => $odds1, 'wrestler2' => $odds2];
    }

    /**
     * @param $percentage
     */
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
     * @param $wrestler1
     * @param $wrestler2
     */
    private function simulateMatch( $wrestler1, $wrestler2 )
    {
        $score1 = ( $wrestler1->strength ?? 60 ) + ( $wrestler1->technicalAbility ?? 60 ) + ( $wrestler1->brawlingAbility ?? 60 );
        $score2 = ( $wrestler2->strength ?? 60 ) + ( $wrestler2->technicalAbility ?? 60 ) + ( $wrestler2->brawlingAbility ?? 60 );
        return ( $score1 >= $score2 ) ? $wrestler1 : $wrestler2;
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

            $wrestler_ids = array_map( fn( $w ) => $w->wrestler_id, $tournament_wrestlers );

            $stmt = $this->db->prepare( "INSERT INTO tournaments (user_id, wrestler_ids) VALUES (?, ?)" );
            $stmt->execute( [$userId, json_encode( $wrestler_ids )] );

            $this->json( ['success' => true, 'message' => 'Tournament started!', 'tournament_id' => $this->db->lastInsertId()] );
        }
        catch ( Exception $e )
        {
            $this->json( ['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500 );
        }
    }

    public function simulate()
    {
        try {
            if ( !isset( $_SESSION['user_id'] ) )
            {
                $this->json( ['success' => false, 'message' => 'User not logged in.'], 403 );
            }

            $postData     = json_decode( file_get_contents( 'php://input' ), true );
            $tournamentId = $postData['tournament_id'];
            $userPicks    = $postData['picks'];

            $stmt = $this->db->prepare( "SELECT * FROM tournaments WHERE id = ? AND user_id = ?" );
            $stmt->execute( [$tournamentId, $_SESSION['user_id']] );
            $tournament = $stmt->fetch( PDO::FETCH_OBJ );

            if ( !$tournament )
            {
                $this->json( ['success' => false, 'message' => 'Tournament not found.'], 404 );
            }

            $currentRound            = $tournament->current_round;
            $wrestlerIdsInTournament = json_decode( $tournament->wrestler_ids ?? '[]' );
            $apiModel                = $this->model( 'Api' );
            $actualWinners           = [];
            $all_correct             = true;
            $matchups                = array_chunk( $wrestlerIdsInTournament, 2 );

            foreach ( $matchups as $index => $match )
            {
                $wrestler1 = $apiModel->getWrestlerById( $match[0] );
                $wrestler2 = $apiModel->getWrestlerById( $match[1] );

                if ( !$wrestler1 || !$wrestler2 )
                {
                    throw new Exception( "Could not find wrestler data for match index {$index}." );
                }

                $winner = $this->simulateMatch( $wrestler1, $wrestler2 );
                if ( !isset( $winner->wrestler_id ) )
                {
                    throw new Exception( "Simulation returned an invalid winner object." );
                }

                $actualWinners[$index] = $winner->wrestler_id;

                if ( !isset( $userPicks[(string) $index] ) || $userPicks[(string) $index] != $winner->wrestler_id )
                {
                    $all_correct = false;
                }
            }

            $currentUserPicks                          = json_decode( $tournament->user_picks ?? '[]', true );
            $currentUserPicks['round' . $currentRound] = $userPicks;
            $stmt                                      = $this->db->prepare( "UPDATE tournaments SET user_picks = ? WHERE id = ?" );
            $stmt->execute( [json_encode( $currentUserPicks ), $tournamentId] );

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
                    $stmt                 = $this->db->prepare( "UPDATE tournaments SET current_round = current_round + 1, wrestler_ids = ? WHERE id = ?" );
                    $stmt->execute( [json_encode( $nextRoundWrestlerIds ), $tournamentId] );

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

            $this->json( $response );
        }
        catch ( Exception $e )
        {
            $this->json( ['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()], 500 );
        }
    }

    public function payToContinue()
    {
        // Define a log file in the root of your project directory
        $logFile = 'tournament_debug.log';
        // Clear the log file for each new request for easier reading
        file_put_contents( $logFile, "--- PayToContinue Request at " . date( 'Y-m-d H:i:s' ) . " ---\n" );

        try {
            if ( !isset( $_SESSION['user_id'] ) )
            {
                $this->json( ['success' => false, 'message' => 'User not logged in.'], 403 );
            }

            $userId       = $_SESSION['user_id'];
            $postData     = json_decode( file_get_contents( 'php://input' ), true );
            $tournamentId = $postData['tournament_id'];
            file_put_contents( $logFile, "1. Received Tournament ID: {$tournamentId}\n", FILE_APPEND );

            $stmt = $this->db->prepare( "SELECT * FROM tournaments WHERE id = ? AND user_id = ?" );
            $stmt->execute( [$tournamentId, $userId] );
            $tournament = $stmt->fetch( PDO::FETCH_OBJ );

            if ( !$tournament || $tournament->current_round != 1 )
            {
                file_put_contents( $logFile, "ERROR: Eligibility check failed. Tournament not found or not in round 1.\n", FILE_APPEND );
                $this->json( ['success' => false, 'message' => 'Not eligible to continue.'], 400 );
            }

            $userModel = $this->model( 'User' );
            if ( $userModel->getGold( $userId ) < 3 )
            {
                $this->json( ['success' => false, 'message' => 'Not enough gold to continue! (Cost: 3 Gold)'] );
            }

            $userModel->updateGold( $userId, -3 );

            $wrestlerIdsInTournament = json_decode( $tournament->wrestler_ids ?? '[]' );
            file_put_contents( $logFile, "2. Wrestler IDs loaded from DB for Round 1: \n" . print_r( $wrestlerIdsInTournament, true ) . "\n", FILE_APPEND );

            $apiModel      = $this->model( 'Api' );
            $actualWinners = [];
            $matchups      = array_chunk( $wrestlerIdsInTournament, 2 );

            foreach ( $matchups as $match )
            {
                $wrestler1 = $apiModel->getWrestlerById( $match[0] );
                $wrestler2 = $apiModel->getWrestlerById( $match[1] );
                if ( $wrestler1 && $wrestler2 )
                {
                    $winner          = $this->simulateMatch( $wrestler1, $wrestler2 );
                    $actualWinners[] = $winner->wrestler_id;
                }
            }
            file_put_contents( $logFile, "3. Calculated winners for Round 2: \n" . print_r( $actualWinners, true ) . "\n", FILE_APPEND );

            $updateQuery  = "UPDATE tournaments SET current_round = 2, wrestler_ids = ? WHERE id = ?";
            $updateStmt   = $this->db->prepare( $updateQuery );
            $updateResult = $updateStmt->execute( [json_encode( $actualWinners ), $tournamentId] );

            if ( $updateResult )
            {
                file_put_contents( $logFile, "4. SUCCESS: Database updated with new wrestler IDs.\n", FILE_APPEND );
            }
            else
            {
                $errorInfo = $updateStmt->errorInfo();
                file_put_contents( $logFile, "4. FAILED: Database update failed. Error: \n" . print_r( $errorInfo, true ) . "\n", FILE_APPEND );
                throw new Exception( "Database update failed." );
            }

            $nextRoundWrestlers = [];
            foreach ( $actualWinners as $id )
            {
                $nextRoundWrestlers[] = $apiModel->getWrestlerById( $id );
            }

            $this->json( [
                'success'             => true,
                'message'             => 'Payment successful! Advancing to Round 2!',
                'next_round_matchups' => $nextRoundWrestlers,
            ] );
        }
        catch ( Exception $e )
        {
            file_put_contents( $logFile, "EXCEPTION caught: " . $e->getMessage() . "\n", FILE_APPEND );
            $this->json( ['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500 );
        }
    }
}
