<?php

namespace App\Controller;

use Src\Controller\Base_Controller;

class Career_Controller extends Base_Controller
{
    /**
     * @param $app
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

    public function index()
    {
        $user = $this->model( 'User' )->getProspectByUserId( $_SESSION['user_id'] );

        $manager = null;
        if ( $user && !empty( $user['manager_id'] ) )
        {
            $managerModel = $this->model( 'Manager' );
            $manager      = $managerModel->getManagerById( $user['manager_id'] );
        }

        $record = ['wins' => 0, 'losses' => 0];
        if ( $user )
        {
            $userModel = $this->model( 'User' );
            $record    = $userModel->getWrestlerRecord( $user['pid'] );
        }

        // Check for a flash message from a previous action (like retirement)
        $flash_message = $_SESSION['flash_message'] ?? null;
        if ( $flash_message )
        {
            // Unset the message so it doesn't show on subsequent visits
            unset( $_SESSION['flash_message'] );
        }

        $this->template->render( 'app/career.html.twig', [
            'isLoggedIn'    => true,
            'user'          => $user,
            'prospect'      => $user,
            'manager'       => $manager,
            'record'        => $record,
            'flash_message' => $flash_message, // Pass the message to the template
        ] );
    }

    public function select_archetype()
    {
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'career' );
            exit;
        }

        $archetype   = $_POST['archetype'] ?? null;
        $careerModel = $this->model( 'Career' );
        $result      = $careerModel->setProspectArchetype( $_SESSION['user_id'], $archetype );

        if ( $result === true )
        {
            // Success, redirect back to the career page
            $this->redirect( 'career' );
        }
        else
        {
            // Handle error, maybe set a flash message
            // For now, just redirect back
            $this->redirect( 'career' );
        }
        exit;
    }

    /**
     * Renders the match selection screen for career mode.
     */
    public function find_match()
    {
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            $this->redirect( 'career' );
            exit;
        }

        $careerModel        = $this->model( 'Career' );
        $prospect['traits'] = $careerModel->getProspectTraits( $prospect['id'] );

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
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            http_response_code( 403 );
            echo json_encode( ['success' => false, 'error' => 'Unauthorized'] );
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
            'winner'             => $winnerName,
            'prospect_name'      => $prospect->name,
            'opponent_name'      => $opponent->name,
            'xp_earned'          => $rewards['xp'],
            'gold_earned'        => $rewards['gold'],
            'log'                => $simResult['log'],
            'leveled_up'         => $updateResult['leveled_up'],
            'bonus_ap'           => $updateResult['bonus_ap'],
            'leveled_up_rewards' => $updateResult['leveled_up_rewards'],
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
     * Simulates a match between the user's prospect and a chosen opponent.
     */
    public function simulate_career_match()
    {
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'career' );
            exit;
        }

        $opponent_id = $_POST['opponent_id'] ?? null;
        if ( !$opponent_id )
        {
            $this->redirect( 'career/find_match' );
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
            $this->redirect( 'career' );
            exit;
        }

        $simulatorModel = $this->model( 'Simulator' );
        $simResult      = $simulatorModel->simulateMatch( $prospect, $opponent );

        $winnerName = is_object( $simResult['winner'] ) ? $simResult['winner']->name : 'Draw';

        $isWin   = ( $winnerName === $prospect->name );
        $rewards = $careerModel->calculateRewards( $prospect, $opponent, $isWin );
        $careerModel->updateProspectAfterMatch( $_SESSION['user_id'], $rewards['xp'], $rewards['gold'], $isWin );

        $_SESSION['match_result'] = [
            'winner'        => $winnerName,
            'prospect_name' => $prospect->name,
            'opponent_name' => $opponent->name,
            'xp_earned'     => $rewards['xp'],
            'gold_earned'   => $rewards['gold'],
            'log'           => $simResult['log'],
        ];

        $this->redirect( 'career/match_result' );
    }

    /**
     * Completes the match, updating the prospect's stats.
     */
    public function complete_match()
    {
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }

        $this->redirect( 'career' );
    }

    public function train()
    {
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }

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
     * @param $moveId
     */
    public function learn_move( $moveId )
    {
        header( 'Content-Type: application/json' );
        if ( !isset( $_SESSION['user_id'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' )
        {
            http_response_code( 403 );
            echo json_encode( ['error' => 'Unauthorized'] );
            exit;
        }

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        $trainModel = $this->model( 'Train' );
        $result     = $trainModel->learnMove( $prospect['pid'], $moveId );

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

    public function hire_manager()
    {
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }

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
     * @param $managerId
     */
    public function purchase_manager( $managerId )
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
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

    /**
     * Handles upgrading a prospect's attribute.
     */
    public function upgrade_attribute( $attribute )
    {
        header( 'Content-Type: application/json' );

        if ( !isset( $_SESSION['user_id'] ) )
        {
            http_response_code( 401 );
            echo json_encode( ['success' => false, 'error' => 'You must be logged in.'] );
            exit;
        }

        $careerModel = $this->model( 'Career' );

        $result = $careerModel->purchaseAttributePoint( $_SESSION['user_id'], $attribute );

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
        if ( !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'user/login' );
            exit;
        }

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            $this->redirect( 'career' );
            exit;
        }

        $careerModel = $this->model( 'Career' );

        // Get the two lists of moves
        $allLearnedMoves = $careerModel->getProspectLearnedMoves( $prospect['pid'] );
        $knownMoves      = $careerModel->getMovesByNames();
        $traits          = $careerModel->getProspectTraits( $prospect['id'] );

        // Create a list of just the names from the known moves list
        $knownMoveNames = array_column( $knownMoves, 'move_name' );

        // Filter the learned moves list to exclude any moves that are also in the known moves list
        $onlyPurchasedMoves = array_filter( $allLearnedMoves, function ( $move ) use ( $knownMoveNames )
        {
            return !in_array( $move['move_name'], $knownMoveNames );
        } );
        // **FIX ENDS HERE**

        $this->template->render( 'career/moveset.html.twig', [
            'prospect'     => $prospect,
            'learnedMoves' => $onlyPurchasedMoves, // Pass the correctly filtered list
            'knownMoves' => $knownMoves,
            'traits'       => $traits,
        ] );
    }

    /**
     * Handles the creation of a new prospect for the logged-in user.
     */
    public function create_prospect()
    {
        header( 'Content-Type: application/json' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            http_response_code( 403 );
            echo json_encode( ['error' => 'Unauthorized access.'] );
            exit;
        }

        $data         = json_decode( file_get_contents( 'php://input' ), true );
        $prospectData = [
            'name'   => $data['wrestlerName'] ?? null,
            'height' => $data['wrestlerHeight'] ?? null,
            'weight' => $data['wrestlerWeight'] ?? null,
            'image'  => $data['wrestlerAvatar'] ?? null,
        ];

        if ( empty( $prospectData['name'] ) || empty( $prospectData['height'] ) || empty( $prospectData['weight'] ) || empty( $prospectData['image'] ) )
        {
            http_response_code( 400 );
            echo json_encode( ['error' => 'All fields are required.'] );
            exit;
        }

        $userModel = $this->model( 'User' );
        $result    = $userModel->createProspectForUser( $_SESSION['user_id'], $prospectData );

        if ( is_array( $result ) )
        {
            // After creating the prospect, immediately check for and assign any qualifying traits.
            $careerModel = $this->model( 'Career' );
            $careerModel->syncProspectTraits( $result['id'] );

            echo json_encode( ['success' => true, 'prospect' => $result] );
        }
        else
        {
            http_response_code( 500 );
            echo json_encode( ['error' => $result] );
        }
    }

    /**
     * Handles the retirement of a max-level prospect.
     */
    public function retire()
    {
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            $this->redirect( 'career' );
            exit;
        }

        $careerModel = $this->model( 'Career' );
        $result      = $careerModel->retireProspectToRoster( $_SESSION['user_id'] );

        if ( $result === true )
        {
            // Optionally set a success flash message
            $_SESSION['flash_message'] = 'Your prospect has been immortalized in the Hall of Fame!';
        }
        else
        {
            // Optionally set an error flash message
            $_SESSION['flash_message'] = 'Error: ' . $result;
        }

        $this->redirect( 'career' );
    }
}
