<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Manager_Controller extends Base_Controller
{
    /**
     * Displays the list of available managers for hire.
     */
    public function hire()
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
     * Handles the hiring of a manager by a user's prospect.
     * UPDATED to return the new gold amount on success.
     */
    public function purchase( $managerId )
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
            // After a successful purchase, fetch the updated gold balance
            $updatedProspect = $userModel->getProspectByUserId( $_SESSION['user_id'] );
            $newGold         = $updatedProspect['gold'];

            echo json_encode( [
                'success' => true,
                'message' => 'Manager hired successfully!',
                'newGold' => $newGold, // Send the new gold amount back to the client
            ] );
        }
        else
        {
            http_response_code( 400 );
            echo json_encode( ['error' => $result] );
        }
    }
}
