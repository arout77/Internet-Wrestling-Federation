<?php
namespace App\Controllers;

use Rhapsody\Core\BaseController;
use Rhapsody\Core\Database;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;

class TriviaController extends BaseController
{
    /**
     * GET /trivia
     * Renders the HTML template containing the React DOM root mount point
     */
    public function index(): Response
    {
        return $this->view('trivia/trivia.twig');
    }
}
