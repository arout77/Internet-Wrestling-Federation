<?php
namespace App\Controllers;

use App\Entities\Prospect; // <-- 1. IMPORT PROSPECT
use App\Entities\Roster;
use App\Entities\User;
use App\Entities\WrestlerInterface; // <-- 2. IMPORT INTERFACE
use App\Services\SimulationService;
use Core\BaseController;
use Core\Request;
use Core\Session;
use Doctrine\ORM\EntityManager;
use Twig\Environment;

class SimulatorController extends BaseController
{
    private SimulationService $simulationService;
    private EntityManager $em;

    /**
     * @param Environment $twig
     * @param EntityManager $em
     * @param SimulationService $simulationService
     */
    public function __construct(Environment $twig, EntityManager $em, SimulationService $simulationService)
    {
        parent::__construct($twig);
        $this->em                = $em;
        $this->simulationService = $simulationService;
    }

    /**
     * NEW: Helper function to find any wrestler (Prospect or Roster) by their ID.
     *
     * @param string $id
     * @return WrestlerInterface|null
     */
    private function findWrestlerById(string $id): ?WrestlerInterface
    {
        // Try finding a Prospect first by its PID (which is a string)
        $wrestler = $this->em->getRepository(Prospect::class)->find($id);
        if ($wrestler) {
            return $wrestler;
        }

        // If not a Prospect, try finding a Roster (ID is an int)
        if (is_numeric($id)) {
            $wrestler = $this->em->getRepository(Roster::class)->find((int) $id);
            if ($wrestler) {
                return $wrestler;
            }
        }

        // Not found in either table
        return null;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        $rosterRepo = $this->em->getRepository(Roster::class);
        $wrestlers  = $rosterRepo->findAll();

        return $this->view('app/match-simulator.twig', [
            'wrestlers' => $wrestlers,
        ]);
    }

    /**
     * API Endpoint: Runs a bulk simulation for odds.
     */
    public function runMatch(Request $request)
    {
        // --- FIX: Read JSON body from the Request object ---
        $data = $request->getBody();

        $wrestler1_id = $data['wrestler1_id'] ?? null;
        $wrestler2_id = $data['wrestler2_id'] ?? null;
        $num_sims     = (int) ($data['num_sims'] ?? 1000);

        if (! $wrestler1_id || ! $wrestler2_id) {
            return $this->json(['error' => 'Missing wrestler IDs.']);
        }

        try {
            // --- 3. USE THE NEW HELPER ---
            $w1 = $this->findWrestlerById($wrestler1_id);
            $w2 = $this->findWrestlerById($wrestler2_id);
            // --- END OF CHANGE ---

            if (! $w1 || ! $w2) {
                return $this->json(['error' => 'Wrestler not found.']);
            }

            // *** FIX: Build the full, correct image URL based on user notes ***
            // Use .env variables to construct the absolute path for the API response
            $baseUrl = $_ENV['APP_URL'] . $_ENV['APP_BASE_URL'];
            $data    = $this->simulationService->generateOdds($w1, $w2, $num_sims);
                                                                                          // *** FIX: Use getImage() and add ".webp" (with the dot) ***
            $data['w1_image'] = $baseUrl . '/public/images/' . $w1->getImage() . '.webp'; // Add full URL for Team 1
            $data['w2_image'] = $baseUrl . '/public/images/' . $w2->getImage() . '.webp'; // Add full URL for Team 2

            return $this->json($data);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * API Endpoint: Runs a single visual match.
     */
    /**
     * API Endpoint: Runs a single visual match with an optional bet.
     */
    public function runSimulation(Request $request)
    {
        // --- FIX: Read JSON body from the Request object ---
        $data = $request->getBody();

        $wrestler1_id = $data['wrestler1_id'] ?? null;
        $wrestler2_id = $data['wrestler2_id'] ?? null;
        $bet_amount   = (int) ($data['bet_amount'] ?? 0);
        $bet_on_team  = $data['bet_on_team'] ?? null; // 'team1' or 'team2'
                                                      // --- END FIX ---

        if (! $wrestler1_id || ! $wrestler2_id) {
            return $this->json(['success' => false, 'error' => 'Missing wrestler IDs.']);
        }

        try {
            // Find wrestlers (Prospect or Roster) by ID
            $w1 = $this->findWrestlerById($wrestler1_id);
            $w2 = $this->findWrestlerById($wrestler2_id);

            if (! $w1 || ! $w2) {
                return $this->json(['success' => false, 'error' => 'Wrestler not found.']);
            }

            // Run the simulation with visual log
            $log = $this->simulationService->runVisualMatch($w1, $w2, true);

            $bet_won     = false;
            $gold_change = 0;

            // Get user and prospect for betting
            $user        = null;
            $prospect    = null;
            $new_balance = 0;

            if (Session::has('user_id')) {
                $user = $this->em->find(User::class, Session::get('user_id'));
                if ($user) {
                    $prospect = $user->getProspect();
                    if ($prospect) {
                        $new_balance = $prospect->getGold();
                    }
                }
            }

            // Process bet if placed
            if ($bet_amount > 0 && $bet_on_team) {
                if (! $prospect) {
                    return $this->json(['success' => false, 'error' => 'You must be logged in to place a bet.']);
                }
                if ($prospect->getGold() < $bet_amount) {
                    return $this->json(['success' => false, 'error' => 'Not enough gold to place this bet.']);
                }

                // Get odds (1000 simulations) to calculate payout
                $oddsData = $this->simulationService->generateOdds($w1, $w2, 1000);

                $endEvent  = end($log);
                $winner_id = $endEvent['data']['winner_id'] ?? null;

                if (($bet_on_team === 'team1' && $winner_id === 'w1') || ($bet_on_team === 'team2' && $winner_id === 'w2')) {
                    $bet_won = true;
                    $odds    = ($bet_on_team === 'team1') ? $oddsData['w1_odds'] : $oddsData['w2_odds'];

                    if ($odds > 0) {
                        // Underdog payout
                        $winnings = $bet_amount * ($odds / 100);
                    } else {
                        // Favorite payout
                        $winnings = $bet_amount * (100 / abs($odds));
                    }

                    $gold_change = (int) round($winnings);

                    // --- NEW: Minimum payout logic ---
                    // --- 1 gold or 1% of wager, whichever is higher
                    // --- Only applies to scenarios where one wrestler is
                    // --- extreme favorite and would otherwise have won 0 gold
                    $minimumWin = max(1, (int) round($bet_amount * 0.01));
                    if ($gold_change > 0 && $gold_change < $minimumWin) {
                        $gold_change = $minimumWin;
                    }
                    // --- END NEW ---

                    $new_balance = $prospect->getGold() + $gold_change;
                } else {
                    $bet_won     = false;
                    $gold_change = -$bet_amount;
                    $new_balance = $prospect->getGold() + $gold_change;
                }

                // Persist the new balance
                $prospect->setGold($new_balance);
                $this->em->persist($prospect);
                $this->em->flush();
            }

            return $this->json([
                'success'     => true,
                'log'         => $log,
                'bet_won'     => $bet_won,
                'gold_change' => $gold_change,
                'new_balance' => $new_balance,
                'bet_amount'  => $bet_amount,
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
