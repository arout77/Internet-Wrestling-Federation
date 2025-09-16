<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Challenge_Controller extends Base_Controller
{
    /**
     * @var mixed
     */
    private $challengeModel;
    /**
     * @var mixed
     */
    private $userModel;

    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
        $this->challengeModel = $this->model( 'Challenge' );
        $this->userModel      = $this->model( 'User' );
    }

    public function index()
    {
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }

        $myProspect = $this->userModel->getProspectByUserId( $_SESSION['user_id'] );
        if ( !$myProspect )
        {
            $this->redirect( 'career' ); // Redirect if they don't have a prospect
            exit;
        }

        $prospects = $this->challengeModel->getAllOtherProspects( $myProspect['pid'] );
        $statuses  = $this->challengeModel->getChallengeStatusesForProspect( $myProspect['pid'], array_column( $prospects, 'pid' ) );

        $this->template->render( 'challenge/index.html.twig', [
            'prospects'          => $prospects,
            'myProspect'         => $myProspect,
            'challenge_statuses' => $statuses,
        ] );
    }

    public function manage()
    {
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }
        $myProspect = $this->userModel->getProspectByUserId( $_SESSION['user_id'] );
        if ( !$myProspect )
        {
            $this->redirect( 'career' );
            exit;
        }

        $incoming = $this->challengeModel->getIncomingChallenges( $myProspect['pid'] );
        $outgoing = $this->challengeModel->getOutgoingChallenges( $myProspect['pid'] );

        $this->template->render( 'challenge/manage.html.twig', [
            'incoming_challenges' => $incoming,
            'outgoing_challenges' => $outgoing,
            'myProspect'          => $myProspect,
        ] );
    }

    public function send()
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            $this->json( ['success' => false, 'error' => 'Unauthorized'], 403 );
        }

        $data        = json_decode( file_get_contents( 'php://input' ), true );
        $defenderPid = $data['defender_pid'] ?? null;
        $wager       = isset( $data['wager'] ) ? (int) $data['wager'] : 0;

        $challenger = $this->userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$challenger || !$defenderPid || $wager <= 0 )
        {
            $this->json( ['success' => false, 'error' => 'Invalid data provided.'], 400 );
        }

        if ( $challenger['gold'] < $wager )
        {
            $this->json( ['success' => false, 'error' => 'You do not have enough gold for this wager.'], 400 );
        }

        $result = $this->challengeModel->createChallenge( $challenger['pid'], $defenderPid, $wager );

        if ( $result === true )
        {
            $this->json( ['success' => true, 'message' => 'Challenge sent successfully!'] );
        }
        else
        {
            $this->json( ['success' => false, 'error' => $result], 400 );
        }
    }

    /**
     * @param $prospect1_pid
     * @param $prospect2_pid
     * @return mixed
     */
    private function calculate_odds_for_challenge( $prospect1_pid, $prospect2_pid )
    {
        $p1_full = $this->challengeModel->getProspectDetailsByPid( $prospect1_pid );
        $p2_full = $this->challengeModel->getProspectDetailsByPid( $prospect2_pid );

        if ( !$p1_full || !$p2_full )
        {
            return null;
        }

        // Convert to objects for simulator compatibility
        $p1_obj = (object) $p1_full;
        $p2_obj = (object) $p2_full;

        // Add wrestler_id for compatibility with the simulator model
        $p1_obj->wrestler_id = $p1_full['pid'];
        $p2_obj->wrestler_id = $p2_full['pid'];

        $p1_obj->traits = array_column( $p1_full['traits'], 'name' );
        $p2_obj->traits = array_column( $p2_full['traits'], 'name' );

        $simulatorModel = $this->model( 'Simulator' );
        $simResult      = $simulatorModel->runBulkSimulations( $p1_obj, $p2_obj, 100 );

        return $simResult;
    }

    /**
     * @param $opponentPid
     * @return null
     */
    public function ajax_get_challenge_details( $opponentPid )
    {
        header( 'Content-Type: application/json' );
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->json( ['success' => false, 'error' => 'Unauthorized'], 403 );
            return;
        }

        $myProspect = $this->userModel->getProspectByUserId( $_SESSION['user_id'] );
        if ( !$myProspect )
        {
            $this->json( ['success' => false, 'error' => 'Current user prospect not found.'], 404 );
            return;
        }

        $opponentProspect = $this->challengeModel->getProspectDetailsByPid( $opponentPid );
        if ( !$opponentProspect )
        {
            $this->json( ['success' => false, 'error' => 'Opponent prospect not found.'], 404 );
            return;
        }

        $oddsData = $this->calculate_odds_for_challenge( $myProspect['pid'], $opponentPid );

        $this->json( [
            'success'           => true,
            'my_prospect'       => $myProspect,
            'opponent_prospect' => $opponentProspect,
            'odds'              => $oddsData,
        ] );
    }

    public function accept()
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            $this->json( ['success' => false, 'error' => 'Unauthorized'], 403 );
        }

        $data        = json_decode( file_get_contents( 'php://input' ), true );
        $challengeId = $data['challenge_id'] ?? null;

        $defender  = $this->userModel->getProspectByUserId( $_SESSION['user_id'] );
        $challenge = $this->challengeModel->getChallengeById( $challengeId );

        if ( !$defender || !$challenge || $challenge['defender_pid'] !== $defender['pid'] )
        {
            $this->json( ['success' => false, 'error' => 'Invalid challenge or not authorized.'], 400 );
        }

        if ( $defender['gold'] < $challenge['wager_amount'] )
        {
            $this->json( ['success' => false, 'error' => 'You do not have enough gold to accept this wager.'], 400 );
        }

        // --- Run Simulation ---
        $challenger = $this->challengeModel->getProspectDetailsByPid( $challenge['challenger_pid'] );

        $challengerObj              = (object) $challenger;
        $challengerObj->wrestler_id = $challenger['pid']; // Add wrestler_id
        $challengerObj->traits      = array_column( $challenger['traits'], 'name' );

        $defenderObj              = (object) $defender;
        $defenderObj->wrestler_id = $defender['pid']; // Add wrestler_id
        $defenderTraits           = $this->challengeModel->getProspectTraits( $defender['id'] );
        $defenderObj->traits      = array_column( $defenderTraits, 'name' );

        $simulator = $this->model( 'Simulator' );
        $simResult = $simulator->simulateMatch( $challengerObj, $defenderObj );

        $winnerPid = is_object( $simResult['winner'] ) ? $simResult['winner']->pid : null;

        // --- Resolve Challenge ---
        $this->challengeModel->resolveChallenge( $challengeId, $winnerPid );

        $this->json( ['success' => true, 'message' => 'Challenge accepted and simulated!', 'result' => $simResult] );
    }

    public function decline()
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            $this->json( ['success' => false, 'error' => 'Unauthorized'], 403 );
        }

        $data        = json_decode( file_get_contents( 'php://input' ), true );
        $challengeId = $data['challenge_id'] ?? null;

        $prospect  = $this->userModel->getProspectByUserId( $_SESSION['user_id'] );
        $challenge = $this->challengeModel->getChallengeById( $challengeId );

        if ( !$prospect || !$challenge || $challenge['defender_pid'] !== $prospect['pid'] )
        {
            $this->json( ['success' => false, 'error' => 'Invalid challenge or not authorized.'], 400 );
        }

        $this->challengeModel->updateChallengeStatus( $challengeId, 'declined' );
        $this->json( ['success' => true, 'message' => 'Challenge declined.'] );
    }

    /**
     * @param $data
     * @param $statusCode
     */
    private function json( $data, $statusCode = 200 )
    {
        http_response_code( $statusCode );
        echo json_encode( $data );
        exit;
    }
}
