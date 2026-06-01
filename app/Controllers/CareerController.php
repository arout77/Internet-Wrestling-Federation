<?php
namespace App\Controllers;

use App\Entities\Move;
use App\Entities\Prospect;
use App\Entities\ProspectNickname;
use App\Entities\Roster;
use App\Entities\User;
use App\Services\CareerService;
use App\Services\SimulationService;
use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Validator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Twig\Environment;

class CareerController extends BaseController
{
    /**
     * @param EntityManager $em
     * @param Validator $validator
     * @param Environment $twig
     */
    public function __construct(
        protected EntityManager $em,
        protected CareerService $careerService,
        protected SimulationService $simulationService,
        protected Validator $validator,
        Environment $twig
    ) {
        parent::__construct($twig);
    }

    /**
     * Main career dashboard.
     * If user has a prospect, show it.
     * If not, redirect to the creation form.
     */
    public function index(): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));

        if (! $user->getProspect()) {
            // User has no prospect, force them to create one.
            return redirect('/career/create');
        }

        // User has a prospect, show the main career dashboard.
        $prospect = $user->getProspect();

        // Example of fetching data for the dashboard:
        $traits = $prospect->getTraits();

        return $this->view('career/career.html.twig', [
            'prospect' => $prospect,
            'traits'   => $traits,
        ]);
    }

    /**
     * Show the form to create a new prospect.
     * If user already has a prospect, redirect to the dashboard.
     */
    public function showCreateForm(Request $request): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));

        if ($user->getProspect()) {
            // User already has a prospect, redirect them away.
            return redirect('/career');
        }

        // Fetch data needed to populate the form
        $nicknameRepo = $this->em->getRepository(ProspectNickname::class);
        $nicknames    = $nicknameRepo->findAll();

        $avatarPath = dirname(__DIR__, 2) . '/public/images/avatars';
        $avatars    = [];
        if (is_dir($avatarPath)) {
            $allFiles = scandir($avatarPath);
            $avatars  = array_filter($allFiles, fn($file) => str_ends_with($file, '.png'));
        }

        return $this->view('career/career.html.twig', [
            'nicknames' => $nicknames,
            'avatars'   => $avatars,
            'old'       => Session::getFlash('old'),    // <-- THE FIX (was $request->getFlash)
            'errors'    => Session::getFlash('errors'), // <-- THE FIX (was $request->getFlash)
        ]);
    }

    /**
     * Handle the submission of the create prospect form.
     */
    public function handleCreateForm(Request $request): Response
    {
        // 1. Get the logged-in user
        $user = $this->em->find(User::class, Session::get('user_id'));

        // 2. Double-check they don't have a prospect
        if ($user->getProspect()) {
            return redirect('/dashboard')->with('error', 'You already have a prospect.');
        }

        // 3. Get form data
        $data = $request->getBody();

        // 4. Define validation rules
        $rules = [
            'name'     => 'required|min:3|max:50',
            'height'   => 'required|regex:/^[4-7]\'\d{1,2}\"$/', // Regex for format <feet>'<inches>"
            'weight'   => 'required|numeric|min:150|max:600',
            'nickname' => 'alpha_num', // Optional, but must be valid if present
            'avatar'   => 'required',  // Must have selected one
        ];

        if ($this->validator->validate($data, $rules)) {
            // 5. Validation Passed
            try {
                // Create the new Prospect
                $prospect = new Prospect();

                // Format the name with nickname if provided
                $nickname     = $data['nickname'] ?? '';
                $name         = $data['name'];
                $prospectName = ! empty($nickname) ? "{$nickname} {$name}" : $name;

                $prospect->setName($prospectName);
                $prospect->setHeight($data['height']);
                $prospect->setWeight($data['weight']);
                $prospect->setImage('/public/images/avatars/' . $data['avatar']);

                $moveRepo = $this->em->getRepository(Move::class);
                // This is the default set of 11 moves from your SQL dump
                $defaultMoveIds = [6, 33, 51, 80, 127, 173, 177, 235, 283, 285, 293];

                $defaultMoves = $moveRepo->findBy(['move_id' => $defaultMoveIds]);

                foreach ($defaultMoves as $move) {
                    $prospect->getMoves()->add($move);
                }
                // Associate it with the user
                $prospect->setUser($user);
                $user->setProspect($prospect);

                // Save entity to the database in a transaction
                $this->em->persist($user);
                $this->em->persist($prospect);
                $this->em->flush();

                // 1. Set the flash message for the next page
                Session::flash('success', 'Your prospect has been created!');

                // 2. Return the JSON response the JavaScript expects
                return $this->json([
                    'success'  => true,
                    'redirect' => '/career',
                ]);

            } catch (\Exception $e) {
                // Handle potential database errors (e.g., duplicate name)
                return $this->json([
                    'success' => false,
                    'error'   => 'An error occurred: ' . $e->getMessage(),
                ], 500); // Send a 500 status code
            }
        }

        // 6. Validation Failed
        // We need to refetch the form data (nicknames, avatars) to re-render the page
        $nicknameRepo = $this->em->getRepository(ProspectNickname::class);
        $nicknames    = $nicknameRepo->findAll();
        $avatarPath   = dirname(__DIR__, 2) . '/public/images/avatars';
        $allFiles     = scandir($avatarPath);
        $avatars      = array_filter($allFiles, function ($file) {
            return str_ends_with($file, '.png');
        });

        return $this->view('/career/career.html.twig', [
            'nicknames' => $nicknames,
            'avatars'   => $avatars,
            'old'       => $data,
            'errors'    => $this->validator->getErrors(),
        ]);
    }

    // CareerController.php

    public function selectArchetype(Request $request): Response
    {
        $user     = $this->em->find(User::class, Session::get('user_id'));
        $prospect = $user->getProspect();

        if (! $prospect || $prospect->getLvl() < 5 || $prospect->getArchetype() !== null) {
            return redirect('/career')->with('error', 'Cannot select archetype at this time.');
        }

        $data      = $request->getBody();
        $archetype = $data['archetype'] ?? '';

        $valid = ['brawler', 'technician', 'high-flyer', 'powerhouse'];
        if (! in_array($archetype, $valid)) {
            return redirect('/career')->with('error', 'Invalid archetype selected.');
        }

        // Apply one‑time stat bonuses
        switch ($archetype) {
            case 'brawler':
                $prospect->setBrawlingAbility($prospect->getBrawlingAbility() + 5);
                $prospect->setToughness($prospect->getToughness() + 5);
                break;
            case 'technician':
                $prospect->setTechnicalAbility($prospect->getTechnicalAbility() + 5);
                // Submission defense is not a direct attribute in your schema;
                // you may store it separately or ignore for now.
                break;
            case 'high-flyer':
                $prospect->setAerialAbility($prospect->getAerialAbility() + 5);
                $prospect->setStamina($prospect->getStamina() + 5);
                break;
            case 'powerhouse':
                $prospect->setStrength($prospect->getStrength() + 5);
                $prospect->setBaseHp($prospect->getBaseHp() + 50);
                break;
        }

        $prospect->setArchetype($archetype);
        $this->em->flush();

        return redirect('/career')->with('success', "Archetype '$archetype' selected! Bonus stats applied.");
    }

    /**
     * NEW: Show the "Find Match" page with the Roster list.
     */
    public function findMatch(): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user) {
            return redirect('/login');
        }

        $prospect = $user->getProspect();
        if (! $prospect) {
            // User has no prospect, force them to create one.
            return redirect('/career/create');
        }

        // Get all Roster wrestlers
        $rosterRepo = $this->em->getRepository(Roster::class);
        $opponents  = $rosterRepo->findAll();

        // --- NEW: Sort opponents ---
        $prospectLvl = $prospect->getLvl();
        usort($opponents, function ($a, $b) use ($prospectLvl) {
            $isA_Locked = $a->getLvl() > $prospectLvl;
            $isB_Locked = $b->getLvl() > $prospectLvl;

            if ($isA_Locked && ! $isB_Locked) {
                return 1; // A (locked) comes after B (unlocked)
            } elseif (! $isA_Locked && $isB_Locked) {
                return -1; // A (unlocked) comes before B (locked)
            } else {
                // Both are locked or both are unlocked, sort by level ascending
                return $a->getLvl() <=> $b->getLvl();
            }
        });
        // --- END OF SORT ---

        return $this->view('career/find_match.html.twig', [
            'prospect'  => $prospect,
            'opponents' => $opponents,
            'meta'      => ['title' => 'Find Match | IWF Career'],
        ]);
    }

    /**
     * NEW: Run the match simulation and show results.
     */
    public function runMatch(Request $request): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user || ! $user->getProspect()) {
            return redirect('/login');
        }

        $prospect = $user->getProspect();
        $data     = $request->getBody();

        $opponent_id = $data['opponent_id'] ?? null;
        if (! $opponent_id) {
            return redirect('/career/find-match')->with('error', 'No opponent selected.');
        }

        $opponent = $this->em->getRepository(Roster::class)->find($opponent_id);
        if (! $opponent) {
            return redirect('/career/find-match')->with('error', 'Opponent not found.');
        }

        // --- Run Simulation ---
        $log      = $this->simulationService->runVisualMatch($prospect, $opponent, true);
        $endEvent = end($log);
        $isWin    = ($endEvent['data']['winner_id'] === 'w1'); // 'w1' is always the prospect here

        // --- Process Results ---
        $results = $this->careerService->processMatchResult($prospect, $isWin, $opponent->getLvl());

        // Store results in session to show on the next page
        $resultData = [
            'log'      => $log,
            // We must pass plain data to the session, not the full Doctrine entity
            'prospect' => [
                'name'  => $prospect->getName(),
                'image' => $prospect->getImage(),
            ],
            'opponent' => [
                'name'          => $opponent->getName(),
                'image'         => $opponent->getImage(),
                'lvl'           => $opponent->getLvl(),
                'simulation_id' => $opponent->getSimulationId(),
            ],
            'rewards'  => $results,
            'is_win'   => $isWin,
        ];

        // --- FIX: Convert the array to a JSON string before flashing ---
        Session::flash('match_result', json_encode($resultData));

        return redirect('/career/match-result');
    }

    /**
     * NEW: Display the results of the match.
     */
    public function showMatchResult(): Response
    {
        // --- FIX: Decode the JSON string back into an array ---
        $resultJson = Session::getFlash('match_result');
        $result     = $resultJson ? json_decode($resultJson, true) : null;
        // --- END FIX ---

        if (! $result) {
            return redirect('/career/find-match')->with('error', 'No match result found.');
        }

        return $this->view('career/match_result.html.twig', [
            'result' => $result,
            'meta'   => ['title' => 'Match Result | IWF Career'],
        ]);
    }
}
