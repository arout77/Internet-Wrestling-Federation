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

        if ( !$_SESSION['user_id'] )
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

    public function career()
    {
        $user = $this->model( 'User' )->getProspectByUserId( $_SESSION['user_id'] );

        // The 'prospect' and 'record' variables are now globally available from the Base_Controller.
        // We just need to render the view.
        $this->template->render( 'app/career.html.twig', [
            'isLoggedIn' => true,
            'user'       => $user,
            'prospect'   => $user,
        ] );
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
        $this->template->render(
            'home/index.html.twig',
            [
                'message'   => 'Page Not Found',
                'site_name' => 'Rhapsody Framework',
            ]
        );
    }

    public function wrestlers()
    {
        $this->template->render( 'app/wrestlers.html.twig' );
    }

}
