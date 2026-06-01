<?php

namespace App\Controllers;

use App\Models\Career;
use App\Models\Train;
use App\Services\CareerService;
use Core\BaseController;
use Core\Request;
use Twig\Environment;

class TrainController extends BaseController
{
    private Career $careerModel;
    private Train $trainModel;
    private CareerService $careerService;

    /**
     * @param Environment $twig
     * @param Career $careerModel
     * @param Train $trainModel
     * @param CareerService $careerService
     */
    public function __construct( Environment $twig, Career $careerModel, Train $trainModel, CareerService $careerService )
    {
        parent::__construct( $twig );
        $this->careerModel   = $careerModel;
        $this->trainModel    = $trainModel;
        $this->careerService = $careerService;
    }

    /**
     * @return mixed
     */
    public function index(): Response
    {
        $prospect = $this->careerService->getCurrentProspect();
        if ( !$prospect ) {
            return redirect( '/career' );
        }

        $filterType = $_GET['type'] ?? 'all';
        $sortBy     = $_GET['sort_by'] ?? 'level_requirement';
        $sortOrder  = $_GET['sort_order'] ?? 'ASC';

        // 1. Fetch moves based on level and type using the correct prospect PID
        $allMoves = $this->trainModel->getAvailableMoves( $prospect['pid'], $prospect['lvl'], $filterType, $sortBy, $sortOrder );

        // 2. Get IDs of moves the prospect already knows
        $knownMoveIds = $this->trainModel->getKnownMoveIds();

        // 3. Get the full details for the moves the prospect already knows
        $knownMoves = array_filter( $allMoves, function ( $move ) use ( $knownMoveIds ) {
            return in_array( $move['move_id'], $knownMoveIds );
        } );

        // 4. Filter out the known moves from the available moves to get purchasable moves
        $purchasableMoves = array_filter( $allMoves, function ( $move ) use ( $knownMoveIds ) {
            return !in_array( $move['move_id'], $knownMoveIds );
        } );

        return $this->view( 'career/train.html.twig', [
            'prospect'         => $prospect,
            'purchasableMoves' => $purchasableMoves,
            'knownMoves'       => $knownMoves, // Pass the full known moves data
            'opportunities' => $this->trainModel->getTrainingOpportunities(),
            'traits'           => $this->careerModel->getProspectTraits(),
            'currentFilter'    => $filterType,
            'currentSortBy'    => $sortBy,
            'currentSortOrder' => $sortOrder,
        ] );
    }

    /**
     * Handles the API request to learn a new move.
     */
    public function learnMove( Request $request, $vars )
    {
        $moveId   = $vars['moveId'] ?? null;
        $prospect = $this->careerService->getCurrentProspect();

        if ( !$moveId || !$prospect ) {
            return $this->json( ['success' => false, 'error' => 'Invalid request.'], 400 );
        }

        $result = $this->trainModel->learnMove( $prospect['pid'], $moveId );

        if ( $result === true ) {
            return $this->json( ['success' => true, 'message' => 'Move learned successfully!'] );
        }

        return $this->json( ['success' => false, 'error' => $result], 400 );
    }
}
