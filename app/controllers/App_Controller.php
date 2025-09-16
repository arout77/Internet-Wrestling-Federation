<?php

namespace App\Controller;

use RedBeanPHP\R as R;
use \Src\Controller\Base_Controller;

class App_Controller extends Base_Controller
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );

        if ( !isset( $_SESSION['user_id'] ) && $this->route->action != 'wrestlers' )
        {
            // A more reliable way to detect an API request is to check the 'Accept' header.
            $is_api_request = isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'application/json' ) !== false;

            if ( $is_api_request )
            {
                // It's an API call, send a JSON error
                http_response_code( 401 ); // Unauthorized
                header( 'Content-Type: application/json' );
                echo json_encode( ['success' => false, 'message' => 'Authentication required. Please log in.'] );
                exit();
            }
            else
            {
                // It's a regular page navigation, so redirect to the login page
                header( 'Location: ' . $this->app['config']->setting( 'site_url' ) . 'user/login' );
                exit();
            }
        }
    }

    public function booking()
    {
        $this->template->render(
            'app/booking.html.twig',
            [
                'message'   => 'Page Not Found',
                'site_name' => 'Rhapsody Framework',
            ]
        );
    }

    public function match()
    {
        $apiModel  = $this->model( 'Api' );
        $wrestlers = $apiModel->get_all_wrestlers();

        $this->template->render( 'app/match.html.twig', [
            'title'     => 'Match Simulator',
            'wrestlers' => $wrestlers,
        ] );
    }

    public function index()
    {
        // Default action for App_Controller can redirect to career or a dashboard
        $this->redirect( 'career' );
    }

    public function wrestlers()
    {
        $this->template->render( 'app/wrestlers.html.twig' );
    }

}
