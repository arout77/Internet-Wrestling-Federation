<?php
namespace App\Controllers;

use App\Entities\WrestlerTrait;
use Core\BaseController;
use Core\Response;
use Doctrine\ORM\EntityManager;
use Twig\Environment;

/**
 * Handles rendering of the framework documentation pages.
 */
class DocsController extends BaseController
{
    protected EntityManager $em;

    /**
     * @param Environment $twig
     * @param EntityManager $em
     */
    public function __construct(Environment $twig, EntityManager $em)
    {
        parent::__construct($twig);
        $this->em = $em;
    }

    /**
     * Shows the main documentation index page.
     */
    public function index(): Response
    {
        return $this->view('docs/index.twig');
    }

    /**
     * Shows the installation and setup guide.
     */
    public function installation(): Response
    {
        return $this->view('docs/installation.twig');
    }

    /**
     * Shows the routing documentation.
     */
    public function routing(): Response
    {
        return $this->view('docs/routing.twig');
    }

    /**
     * @return mixed
     */
    public function request(): Response
    {
        return $this->view('docs/request.twig');
    }

    /**
     * @return mixed
     */
    public function response(): Response
    {
        return $this->view('docs/response.twig');
    }

    /**
     * Shows the controllers documentation.
     */
    public function controllers(): Response
    {
        return $this->view('docs/controllers.twig');
    }

    /**
     * Shows the models and database documentation.
     */
    public function models(): Response
    {
        return $this->view('docs/models.twig');
    }

    /**
     * @return mixed
     */
    public function doctrine(): Response
    {
        return $this->view('docs/doctrine.twig');
    }

    /**
     * Shows the views and templating documentation.
     */
    public function views(): Response
    {
        return $this->view('docs/views.twig');
    }

    /**
     * Shows the validation documentation.
     */
    public function validation(): Response
    {
        return $this->view('docs/validation.twig');
    }

    /**
     * Shows the authentication and middleware documentation.
     */
    public function middleware(): Response
    {
        return $this->view('docs/middleware.twig');
    }

    /**
     * @return mixed
     */
    public function cli(): Response
    {
        return $this->view('docs/cli.twig');
    }

    /**
     * @return mixed
     */
    public function mailer(): Response
    {
        return $this->view('docs/mailer.twig');
    }

    /**
     * @return mixed
     */
    public function seo(): Response
    {
        return $this->view('docs/seo.twig');
    }

    /**
     * @return mixed
     */
    public function pagination(): Response
    {
        return $this->view('docs/pagination.twig');
    }

    /**
     * @return mixed
     */
    public function fileUploader(): Response
    {
        return $this->view('docs/file-uploader.twig');
    }

    /**
     * @return mixed
     */
    public function caching(): Response
    {
        return $this->view('docs/caching.twig');
    }

    /**
     * @return mixed
     */
    public function updating(): Response
    {
        return $this->view('docs/updating.twig');
    }

    /**
     * @return mixed
     */
    public function security(): Response
    {
        return $this->view('docs/security.twig');
    }

    /**
     * @return mixed
     */
    public function performance(): Response
    {
        return $this->view('docs/performance.twig');
    }

    /**
     * @return mixed
     */
    public function logging(): Response
    {
        return $this->view('docs/logging.twig');
    }

    /**
     * @return mixed
     */
    public function imageProcessing()
    {
        return $this->view('docs/image-processing.twig');
    }

    /**
     * Shows the events documentation.
     */
    public function events(): Response
    {
        return $this->view('docs/events.twig');
    }

    /**************** GAME DOCS *************/
    public function gamemodes(): Response
    {
        return $this->view('docs/game_modes.html.twig');
    }

    public function booking(): Response
    {
        return $this->view('docs/booking.twig');
    }

    public function career(): Response
    {
        return $this->view('docs/career_guide.html.twig');
    }

    public function faq(): Response
    {
        return $this->view('docs/faq.html.twig');
    }

    public function features(): Response
    {
        return $this->view('docs/features_rewards.html.twig');
    }

    public function simengine(): Response
    {
        return $this->view('docs/simulation-engine.twig');
    }

    public function traits(): Response
    {
        $traits = $this->em->getRepository(WrestlerTrait::class)->findAll();

        return $this->view('docs/traits_guide.html.twig', [
            'traits' => $traits,
            'meta'   => ['title' => 'Trait System Guide | IWF'],
        ]);
    }
}
