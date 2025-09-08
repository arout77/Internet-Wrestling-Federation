<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Career_Controller extends Base_Controller
{
    /**
     * Ensures the user is logged in for all career-related actions.
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }
    }

    /**
     * Displays the main career dashboard. If no prospect exists, it shows the creation modal.
     */
    public function index()
    {
        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        $manager = null;
        if ( $prospect && !empty( $prospect['manager_id'] ) )
        {
            $managerModel = $this->model( 'Manager' );
            $manager      = $managerModel->getManagerById( $prospect['manager_id'] );
        }

        $this->template->render( 'app/career.html.twig', [
            'prospect' => $prospect,
            'manager'  => $manager,
        ] );
    }

    /**
     * Handles the creation of a new wrestler prospect.
     */
    public function create_prospect()
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
        {
            http_response_code( 405 );
            echo json_encode( ['success' => false, 'error' => 'Invalid request method.'] );
            exit;
        }

        $data      = json_decode( file_get_contents( 'php://input' ), true );
        $userModel = $this->model( 'User' );
        $result    = $userModel->createProspectForUser( $_SESSION['user_id'], $data );

        if ( isset( $result['success'] ) && $result['success'] )
        {
            http_response_code( 200 );
            echo json_encode( $result );
        }
        else
        {
            http_response_code( 400 );
            echo json_encode( $result );
        }
        exit;
    }

    /**
     * Renders the match selection screen for career mode.
     */
    public function find_match()
    {
        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            $this->redirect( 'career' );
            exit;
        }

        $careerModel             = $this->model( 'Career' );
        $prospect['traits']      = $careerModel->getProspectTraits( $prospect['id'] );
        $prospect['wrestler_id'] = $prospect['pid'];

        $opponents = $careerModel->findOpponentForProspect( $prospect['lvl'] );

        $this->template->render( 'career/find_match.html.twig', [
            'prospect_json'  => json_encode( $prospect ),
            'opponents_json' => json_encode( $opponents ),
        ] );
    }

    /**
     * API endpoint to run a career match simulation and return the log.
     */
    public function run_simulation_api()
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
        {
            http_response_code( 403 );
            echo json_encode( ['success' => false, 'error' => 'Invalid request method'] );
            exit;
        }

        $opponent_id = $_POST['opponent_id'] ?? null;
        if ( !$opponent_id )
        {
            http_response_code( 400 );
            echo json_encode( ['success' => false, 'error' => 'Opponent ID missing.'] );
            exit;
        }

        $userModel              = $this->model( 'User' );
        $prospectData           = $userModel->getProspectByUserId( $_SESSION['user_id'] );
        $careerModel            = $this->model( 'Career' );
        $prospectData['traits'] = $careerModel->getProspectTraits( $prospectData['id'] );
        $prospect               = (object) $prospectData;
        $prospect->wrestler_id  = $prospect->pid;

        $apiModel = $this->model( 'Api' );
        $opponent = $apiModel->getWrestlerById( $opponent_id );

        if ( !$prospect || !$opponent )
        {
            http_response_code( 404 );
            echo json_encode( ['success' => false, 'error' => 'Wrestler data not found.'] );
            exit;
        }

        $initial_hp_prospect = $prospect->baseHp + ( $prospect->toughness * 10 );
        $initial_hp_opponent = $opponent->baseHp + ( $opponent->toughness * 10 );

        $simulatorModel = $this->model( 'Simulator' );
        $simResult      = $simulatorModel->simulateMatch( $prospect, $opponent );

        $winnerName = is_object( $simResult['winner'] ) ? $simResult['winner']->name : 'Draw';
        $isWin      = ( $winnerName === $prospect->name );
        $rewards    = $careerModel->calculateRewards( $prospect, $opponent, $isWin );

        $updateResult = $careerModel->updateProspectAfterMatch( $_SESSION['user_id'], $rewards['xp'], $rewards['gold'], $isWin );

        $_SESSION['last_match_for_log'] = [
            'winner'           => $winnerName,
            'prospect_name'    => $prospect->name,
            'opponent_name'    => $opponent->name,
            'xp_earned'        => $rewards['xp'],
            'gold_earned'      => $rewards['gold'],
            'log'              => $simResult['log'],
            'leveled_up'       => $updateResult['leveled_up'],
            'bonus_ap_awarded' => $updateResult['bonus_ap'], // **NEW:** Pass bonus info
        ];

        echo json_encode( [
            'success'    => true,
            'log'        => $simResult['log'],
            'initial_hp' => [
                'prospect' => $initial_hp_prospect,
                'opponent' => $initial_hp_opponent,
            ],
        ] );
        exit;
    }

    /**
     * Displays the result of the last match.
     */
    public function match_result()
    {
        if ( !isset( $_SESSION['last_match_for_log'] ) )
        {
            $this->redirect( 'career' );
            exit;
        }

        $result = $_SESSION['last_match_for_log'];
        unset( $_SESSION['last_match_for_log'] );

        $this->template->render( 'career/match_result.html.twig', [
            'result' => $result,
        ] );
    }

    /**
     * Finalizes the match process and returns to the career dashboard.
     */
    public function complete_match()
    {
        $this->redirect( 'career' );
    }

    /**
     * Handles upgrading a prospect's attribute.
     */
    public function upgrade_attribute( $attribute )
    {
        header( 'Content-Type: application/json' );

        $careerModel = $this->model( 'Career' );
        $result      = $careerModel->purchaseAttributePoint( $_SESSION['user_id'], $attribute );

        if ( $result === true )
        {
            $userModel       = $this->model( 'User' );
            $updatedProspect = $userModel->getProspectByUserId( $_SESSION['user_id'] );

            http_response_code( 200 );
            echo json_encode( [
                'success'  => true,
                'message'  => 'Attribute upgraded successfully!',
                'prospect' => $updatedProspect,
            ] );
        }
        else
        {
            http_response_code( 400 );
            echo json_encode( ['success' => false, 'error' => $result] );
        }

        exit;
    }

    /**
     * Displays the moveset for the current prospect.
     */
    public function moveset()
    {
        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            $this->redirect( 'career' );
            exit;
        }

        $careerModel  = $this->model( 'Career' );
        $learnedMoves = $careerModel->getProspectLearnedMoves( $prospect['pid'] );
        $knownMoves   = $careerModel->getMovesByNames( $prospect['moves'] );
        $traits       = $careerModel->getProspectTraits( $prospect['id'] );

        $this->template->render( 'career/moveset.html.twig', [
            'prospect'     => $prospect,
            'learnedMoves' => $learnedMoves,
            'knownMoves'   => $knownMoves,
            'traits'       => $traits,
        ] );
    }

    /**
     * Displays the training center.
     */
    public function train()
    {
        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            $this->redirect( 'career' );
            exit;
        }

        $filterType = $_GET['type'] ?? 'all';
        $sortBy     = $_GET['sort_by'] ?? 'level_requirement';
        $sortOrder  = $_GET['sort_order'] ?? 'ASC';

        $trainModel     = $this->model( 'Train' );
        $availableMoves = $trainModel->getAvailableMoves( $prospect['pid'], $prospect['lvl'], $filterType, $sortBy, $sortOrder );

        $this->template->render( 'career/train.html.twig', [
            'prospect'         => $prospect,
            'availableMoves'   => $availableMoves,
            'currentFilter'    => $filterType,
            'currentSortBy'    => $sortBy,
            'currentSortOrder' => $sortOrder,
        ] );
    }

    /**
     * Handles learning a new move.
     */
    public function learn_move( $moveId )
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
        {
            http_response_code( 403 );
            echo json_encode( ['error' => 'Unauthorized'] );
            exit;
        }

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        $trainModel = $this->model( 'Train' );
        $result     = $trainModel->learnMove( $prospect, $moveId );

        if ( $result === true )
        {
            echo json_encode( ['success' => true, 'message' => 'Move learned successfully!'] );
        }
        else
        {
            http_response_code( 400 );
            echo json_encode( ['error' => $result] );
        }
    }

    /**
     * Displays the manager hiring page.
     */
    public function hire_manager()
    {
        $managerModel = $this->model( 'Manager' );
        $managers     = $managerModel->getAllManagers();

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        $this->template->render( 'managers/hire.html.twig', [
            'managers' => $managers,
            'prospect' => $prospect,
        ] );
    }

    /**
     * Handles purchasing a manager.
     */
    public function purchase_manager( $managerId )
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
        {
            http_response_code( 403 );
            echo json_encode( ['error' => 'Unauthorized'] );
            exit;
        }

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            http_response_code( 404 );
            echo json_encode( ['error' => 'Prospect not found.'] );
            exit;
        }

        $managerModel = $this->model( 'Manager' );
        $result       = $managerModel->hireManagerForProspect( $prospect, $managerId );

        if ( $result === true )
        {
            echo json_encode( ['success' => true, 'message' => 'Manager hired successfully!'] );
        }
        else
        {
            http_response_code( 400 );
            echo json_encode( ['error' => $result] );
        }
    }
}
