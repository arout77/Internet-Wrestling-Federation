<?php

namespace App\Controllers;

use Core\BaseController;
use Core\Response;
use Twig\Environment;

/**
 * Handles rendering of the framework documentation pages.
 */
class FrameworkDocsController extends BaseController
{
    /**
     * @param Environment $twig
     */
    public function __construct( Environment $twig )
    {
        parent::__construct( $twig );
    }

    /**
     * Shows the main documentation index page.
     */
    public function index(): Response
    {
        return $this->view( 'docs-framework/index.twig' );
    }

    /**
     * Shows the installation and setup guide.
     */
    public function installation(): Response
    {
        return $this->view( 'docs-framework/installation.twig' );
    }

    /**
     * Shows the routing documentation.
     */
    public function routing(): Response
    {
        return $this->view( 'docs-framework/routing.twig' );
    }

    /**
     * @return mixed
     */
    public function request(): Response
    {
        return $this->view( 'docs-framework/request.twig' );
    }

    /**
     * @return mixed
     */
    public function response(): Response
    {
        return $this->view( 'docs-framework/response.twig' );
    }

    /**
     * Shows the controllers documentation.
     */
    public function controllers(): Response
    {
        return $this->view( 'docs-framework/controllers.twig' );
    }

    /**
     * Shows the models and database documentation.
     */
    public function models(): Response
    {
        return $this->view( 'docs-framework/models.twig' );
    }

    /**
     * @return mixed
     */
    public function doctrine(): Response
    {
        return $this->view( 'docs-framework/doctrine.twig' );
    }

    /**
     * Shows the views and templating documentation.
     */
    public function views(): Response
    {
        return $this->view( 'docs-framework/views.twig' );
    }

    /**
     * Shows the validation documentation.
     */
    public function validation(): Response
    {
        return $this->view( 'docs-framework/validation.twig' );
    }

    /**
     * Shows the authentication and middleware documentation.
     */
    public function middleware(): Response
    {
        return $this->view( 'docs-framework/middleware.twig' );
    }

    /**
     * @return mixed
     */
    public function cli(): Response
    {
        return $this->view( 'docs-framework/cli.twig' );
    }

    /**
     * @return mixed
     */
    public function mailer(): Response
    {
        return $this->view( 'docs-framework/mailer.twig' );
    }

    /**
     * @return mixed
     */
    public function seo(): Response
    {
        return $this->view( 'docs-framework/seo.twig' );
    }

    /**
     * @return mixed
     */
    public function pagination(): Response
    {
        return $this->view( 'docs-framework/pagination.twig' );
    }

    /**
     * @return mixed
     */
    public function fileUploader(): Response
    {
        return $this->view( 'docs-framework/file-uploader.twig' );
    }

    /**
     * @return mixed
     */
    public function caching(): Response
    {
        return $this->view( 'docs-framework/caching.twig' );
    }

    /**
     * @return mixed
     */
    public function updating(): Response
    {
        return $this->view( 'docs-framework/updating.twig' );
    }

    /**
     * @return mixed
     */
    public function security(): Response
    {
        return $this->view( 'docs-framework/security.twig' );
    }

    /**
     * @return mixed
     */
    public function performance(): Response
    {
        return $this->view( 'docs-framework/performance.twig' );
    }

    /**
     * @return mixed
     */
    public function logging(): Response
    {
        return $this->view( 'docs-framework/logging.twig' );
    }

    /**
     * @return mixed
     */
    public function imageProcessing()
    {
        return $this->view( 'docs-framework/image-processing.twig' );
    }
}
