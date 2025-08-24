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
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('user/login');
            exit;
        }

        $user = $this->model('User')->getProspectByUserId($_SESSION['user_id']);

        // The 'prospect' and 'record' variables are now globally available from the Base_Controller.
        // We just need to render the view.
        $this->template->render('app/career.html.twig', [
            'isLoggedIn' => true,
            'user'   => $user,
            'prospect'   => $user,
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
