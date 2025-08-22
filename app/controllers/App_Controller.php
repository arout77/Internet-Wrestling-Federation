<?php

namespace App\Controller;

use RedBeanPHP\R as R;
use \Src\Controller\Base_Controller;

class App_Controller extends Base_Controller
{
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
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            // Redirect to login page if not logged in
            header('Location: ' . $this->config->setting('site_url') . 'user/login');
            exit;
        }

        // Explicitly fetch the prospect data for the current user to ensure it's available
        $userModel = $this->model('User');
        $prospect = $userModel->getProspectByUserId($_SESSION['user_id']);

        // Pass the prospect data directly to the template
        $this->template->render('app/career.html.twig', [
            'isLoggedIn' => true,
            'prospect' => $prospect
        ]);
    }

    public function match()
    {
        $this->template->render(
            'app/match.html.twig',
            [
                'message'   => 'Page Not Found',
                'site_name' => 'Rhapsody Framework',
            ]
        );
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
