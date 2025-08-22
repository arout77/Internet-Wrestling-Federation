<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Manager_Controller extends Base_Controller
{
    /**
     * Manager_Controller constructor.
     * Fetches prospect data for logged-in users and makes it globally available to Twig templates.
     */
    public function __construct($app)
    {
        parent::__construct($app);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $prospect = null;
        if (isset($_SESSION['user_id'])) {
            $userModel = $this->model('User');
            $prospect = $userModel->getProspectByUserId($_SESSION['user_id']);
        }

        // Makes 'prospect' variable available in all templates rendered by this controller.
        if (method_exists($this->template, 'getTwig')) {
            $this->template->getTwig()->addGlobal('prospect', $prospect);
        }
    }

    /**
     * Displays the list of available managers for hire.
     */
    public function hire()
    {
        // Ensure user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->config->setting('site_url') . 'user/login');
            exit;
        }

        $managerModel = $this->model('Manager');
        $managers = $managerModel->getAllManagers();

        $this->template->render('managers/hire.html.twig', [
            'managers' => $managers
        ]);
    }

    /**
     * Handles the hiring of a manager by a user's prospect.
     */
    public function purchase($managerId)
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $userModel = $this->model('User');
        $prospect = $userModel->getProspectByUserId($_SESSION['user_id']);

        if (!$prospect) {
            http_response_code(404);
            echo json_encode(['error' => 'Prospect not found.']);
            exit;
        }

        $managerModel = $this->model('Manager');
        $result = $managerModel->hireManagerForProspect($prospect['pid'], $managerId);

        if ($result === true) {
            echo json_encode(['success' => true, 'message' => 'Manager hired successfully!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result]);
        }
    }
}
