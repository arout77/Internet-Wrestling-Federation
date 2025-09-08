<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Train_Controller extends Base_Controller
{
    /**
     * Displays the training center with moves available to learn.
     */
    public function index()
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
            $this->redirect( 'app/career' );
            exit;
        }

        // Get filter and sort parameters from the URL
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
     * @return null
     */
    public function upgrade()
    {
        $this->auth->requireLogin();
        $this->load->model( 'train' );
        $user_id = $this->session->get( 'user_id' );

        // Load the prospect associated with the logged-in user
        $prospect = R::findOne( 'prospects', 'user_id = ?', [$user_id] );

        if ( !$prospect )
        {
            // Handle the case where the prospect is not found
            // You might want to redirect or show an error message
            return;
        }

        $attribute = $_POST['attribute'];
        $cost      = $_POST['cost'];

        if ( $this->train->can_upgrade( $prospect, $cost ) )
        {
            $this->train->upgrade_attribute( $prospect, $attribute, $cost );
            $this->session->setFlash( 'success', 'Attribute upgraded successfully!' );
        }
        else
        {
            $this->session->setFlash( 'error', 'You do not have enough gold to upgrade.' );
        }

        header( 'Location: /train' ); // Redirect back to the training page
        exit;
    }

    /**
     * Handles the purchase of a new move.
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
}
