<?php
namespace App\Controllers;

use Rhapsody\Core\BaseController;
use Rhapsody\Core\Response;
use Twig\Environment;

class StoreController extends BaseController
{
    public function __construct(Environment $twig)
    {
        parent::__construct($twig);
    }

    /**
     * Example method.
     */
    public function index(): Response
    {
        return $this->view('store/index.html.twig');
    }
}
