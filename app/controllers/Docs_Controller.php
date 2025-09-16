<?php
namespace App\Controller {
    use Src\Controller\Base_Controller;

    class Docs_Controller extends Base_Controller
    {
        public function simulation_engine()
        {
            $this->template->render( 'docs/components/simulation-engine.html.twig', [
                'title' => 'Simulation Engine Documentation',
            ] );
        }

        /**
         * Main documentation landing page.
         */
        public function index()
        {
            $this->template->render( 'docs/index.html.twig' );
        }

        /**
         * Displays the Game Modes documentation page.
         */
        public function game_modes()
        {
            $this->template->render( 'docs/game_modes.html.twig' );
        }

        /**
         * Displays the Career Mode Guide documentation page.
         */
        public function career_guide()
        {
            $this->template->render( 'docs/career_guide.html.twig' );
        }

        /**
         * Displays the Features & Rewards documentation page.
         */
        public function features_rewards()
        {
            $this->template->render( 'docs/features_rewards.html.twig' );
        }

        /**
         * Displays the FAQ documentation page.
         */
        public function faq()
        {
            $this->template->render( 'docs/faq.html.twig' );
        }

    }
}
