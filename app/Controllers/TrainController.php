<?php
namespace App\Controllers;

use App\Models\Career;
use App\Models\Train;
use App\Services\CareerService;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Twig\Environment;

class TrainController extends BaseController
{
    private Career $careerModel;
    private Train $trainModel;
    private CareerService $careerService;
    private EntityManager $em;

    public function __construct(
        Environment $twig,
        Career $careerModel,
        Train $trainModel,
        CareerService $careerService,
        EntityManager $em
    ) {
        parent::__construct($twig);
        $this->careerModel   = $careerModel;
        $this->trainModel    = $trainModel;
        $this->careerService = $careerService;
        $this->em            = $em;
    }

    public function index(): Response
    {
        $userId = Session::get('user_id');
        if (! $userId) {
            return redirect('/login');
        }

        $user = $this->em->find(\App\Entities\User::class, $userId);
        if (! $user || ! $user->getProspect()) {
            return redirect('/career')->with('error', 'You need a prospect to access training.');
        }

        $prospect = $user->getProspect();
        // Use PID (string) for database lookups
        $prospectData = [
            'pid' => $prospect->getPid(),
            'lvl' => $prospect->getLvl(),
        ];

        $filterType = $_GET['type'] ?? 'all';
        $sortBy     = $_GET['sort_by'] ?? 'level_requirement';
        $sortOrder  = $_GET['sort_order'] ?? 'ASC';

        // Fetch all available moves (filtered, sorted)
        $allMoves = $this->trainModel->getAvailableMoves($prospectData['lvl'], $filterType, $sortBy, $sortOrder);

        // Get known move IDs using PID
        $knownMoveIds = $this->trainModel->getKnownMoveIds($prospectData['pid']);

        $knownMoves = array_filter($allMoves, function ($move) use ($knownMoveIds) {
            return in_array($move['move_id'], $knownMoveIds);
        });

        $purchasableMoves = array_filter($allMoves, function ($move) use ($knownMoveIds) {
            return ! in_array($move['move_id'], $knownMoveIds);
        });

        return $this->view('career/train.html.twig', [
            'prospect'         => $prospectData,
            'purchasableMoves' => $purchasableMoves,
            'knownMoves'       => $knownMoves,
            'opportunities'    => $this->trainModel->getTrainingOpportunities($prospectData['pid']),
            'traits'           => $this->careerModel->getProspectTraits(),
            'currentFilter'    => $filterType,
            'currentSortBy'    => $sortBy,
            'currentSortOrder' => $sortOrder,
        ]);
    }

    public function learnMove(Request $request, $vars)
    {
        $moveId = $vars['moveId'] ?? null;
        $userId = Session::get('user_id');
        if (! $userId) {
            return $this->json(['success' => false, 'error' => 'Not logged in.'], 401);
        }
        $user = $this->em->find(\App\Entities\User::class, $userId);
        if (! $user || ! $user->getProspect()) {
            return $this->json(['success' => false, 'error' => 'No prospect found.'], 400);
        }
        $prospect    = $user->getProspect();
        $prospectPid = $prospect->getPid();

        if (! $moveId || ! $prospectPid) {
            return $this->json(['success' => false, 'error' => 'Invalid request.'], 400);
        }

        $result = $this->trainModel->learnMove($prospectPid, $moveId);

        if ($result === true) {
            return $this->json(['success' => true, 'message' => 'Move learned successfully!']);
        }

        return $this->json(['success' => false, 'error' => $result], 400);
    }
}
