<?php
namespace App\Controllers;

use App\Models\Api;
use App\Models\Simulator;
use Rhapsody\Core\BaseController;
use Twig\Environment;

class AppController extends BaseController
{
    /**
     * @var mixed
     */
    private $apiModel;
    /**
     * @var mixed
     */
    private $simModel;

    /**
     * @param $twig
     */
    public function __construct(Environment $twig, Api $apiModel, Simulator $simModel)
    {
        parent::__construct($twig);

        if (! isset($_SESSION['user_id']) && $this->route->action != 'wrestlers') {
            // A more reliable way to detect an API request is to check the 'Accept' header.
            $is_api_request = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

            if ($is_api_request) {
                                         // It's an API call, send a JSON error
                http_response_code(401); // Unauthorized
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
                exit();
            } else {
                // It's a regular page navigation, so redirect to the login page
                header('Location: ' . $this->app['config']->setting('site_url') . 'user/login');
                exit();
            }
        }

        $this->apiModel = $apiModel;
        $this->simModel = $simModel;
    }

    /**
     * @return mixed
     */
    public function booking()
    {
        return $this->view(
            'app/booking.html.twig',
            [
                'message'   => 'Page Not Found',
                'site_name' => 'Rhapsody Framework',
            ]
        );
    }

    public function match()
    {
        $apiModel  = $this->apiModel;
        $wrestlers = $apiModel->get_all_wrestlers();
        $tagTeams  = $this->apiModel->getAllTagTeams();

        return $this->view('app/match.html.twig', [
            'title'     => 'Match Simulator',
            'wrestlers' => $wrestlers,
            'tag_teams' => $tagTeams,
        ]);
    }

    /**
     * @return mixed
     */
    public function index()
    {
        // Default action for App_Controller can redirect to career or a dashboard
        return $this->redirect('career');
    }

    /**
     * @return mixed
     */
    public function wrestlers()
    {
        return $this->view('app/wrestlers.html.twig');
    }

}
