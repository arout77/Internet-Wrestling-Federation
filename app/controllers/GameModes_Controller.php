<?php

namespace App\Controller;

use Src\Controller\Base_Controller;

/**
 * Handles the rendering of dedicated, SEO-optimized landing pages for each game mode.
 */
class GameModes_Controller extends Base_Controller
{
    /**
     * Renders the Career Mode landing page.
     */
    public function career()
    {
        $this->template->render( 'game-modes/career.html.twig' );
    }

    /**
     * Renders the Match Simulator landing page.
     */
    public function simulator()
    {
        $this->template->render( 'game-modes/simulator.html.twig' );
    }

    /**
     * Renders the Tournament Mode landing page.
     */
    public function tournament()
    {
        $this->template->render( 'game-modes/tournament.html.twig' );
    }

    /**
     * Renders the Booker Mode landing page.
     */
    public function booking()
    {
        $this->template->render( 'game-modes/booking.html.twig' );
    }
}
