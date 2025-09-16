<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Store_Controller extends Base_Controller
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
        $userModel = $this->model( 'User' );
        $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );

        $this->template->render( 'store/index.html.twig', [
            'prospect' => $prospect,
        ] );
    }

    /**
     * API endpoint to process a successful purchase.
     */
    public function process_purchase()
    {
        // Start output buffering to catch any stray output (like warnings)
        ob_start();

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_SESSION['user_id'] ) )
        {
            ob_end_clean(); // Discard buffer before sending response
            http_response_code( 403 );
            header( 'Content-Type: application/json' );
            echo json_encode( ['success' => false, 'error' => 'Unauthorized'] );
            exit;
        }

        $data   = json_decode( file_get_contents( 'php://input' ), true );
        $itemId = $data['itemId'] ?? null;

        if ( !$itemId )
        {
            ob_end_clean();
            http_response_code( 400 );
            header( 'Content-Type: application/json' );
            echo json_encode( ['success' => false, 'error' => 'Invalid item specified.'] );
            exit;
        }

        $userModel = $this->model( 'User' );
        $result    = $userModel->creditPurchase( $_SESSION['user_id'], $itemId );

        // Clean (discard) the buffer just before sending the final JSON response
        ob_end_clean();
        header( 'Content-Type: application/json' );

        if ( isset( $result['success'] ) && $result['success'] )
        {
            http_response_code( 200 );
            echo json_encode( [
                'success' => true,
                'message' => 'Purchase successful! Your account has been credited.',
                'newGold' => $result['newGold'],
                'newAP'   => $result['newAP'],
            ] );
        }
        else
        {
            http_response_code( 400 );
            echo json_encode( ['success' => false, 'error' => $result['error'] ?? 'An unknown error occurred.'] );
        }
        exit;
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
