<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Notification_Controller extends Base_Controller
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
        if ( session_status() == PHP_SESSION_NONE )
        {
            session_start();
        }
    }

    /**
     * API endpoint to check for new notifications (unread challenges).
     */
    public function check()
    {
        header( 'Content-Type: application/json' );

        if ( !isset( $_SESSION['user_id'] ) )
        {
            echo json_encode( ['count' => 0] );
            exit;
        }

        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        if ( !$prospect )
        {
            echo json_encode( ['count' => 0] );
            exit;
        }

        $challengeModel = $this->model( 'Challenge' );
        $count          = $challengeModel->getUnreadChallengeCount( $prospect['pid'] );

        echo json_encode( ['count' => $count] );
        exit;
    }
}
