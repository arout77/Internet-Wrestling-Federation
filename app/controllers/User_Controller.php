<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class User_Controller extends Base_Controller
{
    /**
     * Handles user login. Displays the login form and processes form submission.
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $userModel = $this->model('User');
            $user = $userModel->verifyUser($email, $password);

            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['name'];
                $this->redirect('app/career');
                exit;
            } else {
                $this->template->render('forms/login.html.twig', ['error' => 'Invalid email or password.']);
            }
        } else {
            $this->template->render('forms/login.html.twig');
        }
    }

    /**
     * Handles user registration. Displays the registration form and processes form submission.
     */
    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            $errors = [];
            if ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $userModel = $this->model('User');
                $result = $userModel->createNewUser($username, $email, $password);

                if ($result === true) {
                    $this->redirect('user/login');
                    exit;
                } else {
                    $errors[] = $result;
                }
            }
            
            $this->template->render('forms/register.html.twig', ['errors' => $errors]);
        } else {
            $this->template->render('forms/register.html.twig');
        }
    }

    /**
     * Handles the creation of a new prospect for the logged-in user.
     */
    public function create_prospect()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $prospectData = [
            'name' => $data['wrestlerName'] ?? null,
            'height' => $data['wrestlerHeight'] ?? null,
            'weight' => $data['wrestlerWeight'] ?? null,
            'avatar' => $data['wrestlerAvatar'] ?? null,
        ];

        if (empty($prospectData['name']) || empty($prospectData['height']) || empty($prospectData['weight']) || empty($prospectData['avatar'])) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required.']);
            exit;
        }

        $userModel = $this->model('User');
        $result = $userModel->createProspectForUser($_SESSION['user_id'], $prospectData);

        if (is_array($result)) {
            echo json_encode(['success' => true, 'prospect' => $result]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result]);
        }
    }

    /**
     * Handles user logout.
     */
    public function logout()
    {
        session_start();
        session_unset();
        session_destroy();
        $this->redirect('');
        exit;
    }
}
