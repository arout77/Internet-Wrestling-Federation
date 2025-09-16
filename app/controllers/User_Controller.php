<?php
namespace App\Controller;

use App\Model\UserModel;
use Src\Controller\Base_Controller;

class User_Controller extends Base_Controller
{
    /**
     * @var mixed
     */
    private $userModel;

    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
        session_start();
        $this->userModel = new UserModel( $this->app );
    }

    public function login()
    {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
        {
            $credential = $_POST['credential'] ?? '';
            $password   = $_POST['password'] ?? '';

            $user = $this->userModel->verifyUser( $credential, $password );

            if ( $user )
            {
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['name'];
                // **FIX:** Changed redirect from 'app/career' to the correct 'career' route.
                $this->redirect( 'career' );
                exit;
            }
            else
            {
                $this->template->render( 'forms/login.html.twig', ['error' => 'Invalid credentials or password.'] );
            }
        }
        else
        {
            $this->template->render( 'forms/login.html.twig' );
        }
    }

    public function register()
    {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
        {
            $username         = $_POST['username'] ?? '';
            $password         = $_POST['password'] ?? '';
            $email            = $_POST['email'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            $errors = [];
            if ( $password !== $confirm_password )
            {
                $errors[] = 'Passwords do not match.';
            }

            if ( empty( $errors ) )
            {
                $result = $this->userModel->createNewUser( $username, $email, $password );

                if ( $result === true )
                {
                    $this->redirect( 'user/login' );
                    exit;
                }
                else
                {
                    $errors[] = $result;
                }
            }

            $this->template->render( 'forms/register.html.twig', ['errors' => $errors] );
        }
        else
        {
            $this->template->render( 'forms/register.html.twig' );
        }
    }

    public function logout()
    {
        session_start();
        session_unset();
        session_destroy();
        $this->redirect( '' );
        exit;
    }
}
