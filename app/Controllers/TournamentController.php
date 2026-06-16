<?php
namespace App\Controllers;

use App\Models\Api;
use App\Models\Simulator;
use App\Models\Tournament as TournamentModel;
use App\Services\SimulationService; // <-- IMPORTANT: Use the new service for caching
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Twig\Environment;

/**
 * TournamentController
 *
 * Handles the tournament game mode: themed brackets, user predictions,
 * paying to continue, and simulating matches.
 *
 * Performance note: Originally this controller recalculated odds from scratch
 * for every matchup on every page load (16,000+ simulations). This has been
 * optimised by using SimulationService, which caches results.
 */
class TournamentController extends BaseController
{
    /**
     * @param Environment $twig Twig template engine
     * @param Api $apiModel PDO model to fetch wrestler data (may eventually be replaced by Doctrine)
     * @param Simulator $simModel Old simulation model – kept for backwards compatibility, but not used for odds
     * @param TournamentModel $tournamentModel Database operations for tournaments
     * @param SimulationService $simService New simulation service with caching (injected)
     */
    public function __construct(
        Environment $twig,
        private Api $apiModel,
        private Simulator $simModel,
        private TournamentModel $tournamentModel,
        private SimulationService $simService, // <-- Injected to provide cached odds
        private \Doctrine\ORM\EntityManager $em
    ) {
        parent::__construct($twig);
    }

    // ==================== PAGE RENDERING ====================

    /**
     * Main entry point for tournament pages.
     * If no specific tournament type is given, shows the selection screen.
     * Otherwise, delegates to the appropriate themed tournament method.
     *
     * @param string|null $type One of: standard, giants, technicians, brawlers, aerialists, strongmen
     * @return Response
     */
    public function index($type = null)
    {
        // Handle the case where $type is passed as an object from route parameters
        if (is_object($type)) {
            $args = func_get_args();
            $type = $args[1] ?? null;
        }
        $validTypes = ['standard', 'giants', 'technicians', 'brawlers', 'aerialists', 'strongmen'];
        if ($type === null || ! in_array($type, $validTypes)) {
            // Show the tournament selection page
            return $this->view('tournament/index.html.twig', [
                'event_name'   => 'Choose Your Tournament',
                'event_slogan' => 'Select a bracket to begin',
                'matchups'     => [],
            ]);
        }
        // Delegate to the specific method based on tournament type
        return match ($type) {
            'standard'    => $this->standard(),
            'giants'      => $this->giants(),
            'technicians' => $this->technicians(),
            'brawlers'    => $this->brawlers(),
            'aerialists'  => $this->aerialists(),
            'strongmen'   => $this->strongmen(),
            default       => redirect('/tournament'),
        };
    }

    /**
     * Standard tournament: 32 random wrestlers from the entire roster.
     */
    public function standard()
    {
        $wrestlers = $this->apiModel->get_all_wrestlers();
        shuffle($wrestlers);
        $tournament_wrestlers = array_slice($wrestlers, 0, 32);
        return $this->render_tournament_view('Standard Tournament', 'No Restrictions', $tournament_wrestlers);
    }

    /**
     * Giants tournament: wrestlers weighing 300+ pounds.
     */
    public function giants()
    {
        return $this->create_themed_tournament('Battle of the Giants', '300+ Pounders Only', 'get_wrestlers_by_weight', 300);
    }

    /**
     * Technicians tournament: wrestlers with technical ability >= 92.
     */
    public function technicians()
    {
        return $this->create_themed_tournament('Technical Masters', 'Wrestlers with 92+ Technical Ability', 'get_wrestlers_by_technical', 92);
    }

    /**
     * Brawlers tournament: wrestlers with brawling ability >= 93.
     */
    public function brawlers()
    {
        return $this->create_themed_tournament('Bar Room Brawlers', 'Wrestlers with 93+ Brawling Ability', 'get_wrestlers_by_brawling', 93);
    }

    /**
     * Aerialists tournament: wrestlers with aerial ability >= 85.
     */
    public function aerialists()
    {
        return $this->create_themed_tournament('Aerial Assault', 'Wrestlers with 85+ Aerial Ability', 'get_wrestlers_by_aerial', 85);
    }

    /**
     * Strongmen tournament: wrestlers with strength >= 94.
     */
    public function strongmen()
    {
        return $this->create_themed_tournament('Strongman Competition', 'Wrestlers with 94+ Strength', 'get_wrestlers_by_strength', 94);
    }

    // ==================== API ENDPOINTS ====================

    /**
     * Starts a new tournament (creates a database record and deducts entry fee).
     * Called via AJAX from the tournament page.
     *
     * @param Request $request
     * @return Response JSON
     */
    public function start(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['success' => false, 'message' => 'You must be logged in to play.'], 403);
        }
        $userId = Session::get('user_id');
        // Deduct 1 gold as entry fee
        if (! $this->tournamentModel->deductEntryFee($userId, 1)) {
            return $this->json(['success' => false, 'message' => 'Not enough gold to enter! (Cost: 1 Gold)']);
        }
        $postData    = $request->getBody();
        $wrestlerIds = $postData['wrestler_ids'] ?? null;
        if (empty($wrestlerIds)) {
            // If no wrestler IDs provided, pick 32 random wrestlers
            $allWrestlers = $this->apiModel->get_all_wrestlers();
            shuffle($allWrestlers);
            $tournamentWrestlers = array_slice($allWrestlers, 0, 32);
            $wrestlerIds         = array_map(fn($w) => $w['wrestler_id'], $tournamentWrestlers);
        }
        $initialSize  = count($wrestlerIds);
        $tournamentId = $this->tournamentModel->createTournament($userId, $wrestlerIds, $initialSize);
        if (! $tournamentId) {
            return $this->json(['success' => false, 'message' => 'Failed to create tournament.'], 500);
        }
        return $this->json(['success' => true, 'message' => 'Tournament started!', 'tournament_id' => $tournamentId]);
    }

    /**
     * Retrieves odds for all first‑round matchups of a tournament.
     * This is where the performance improvement is critical:
     *   - It uses SimulationService (cached) instead of the old Simulator.
     *   - It reduces the number of simulations per matchup from 1000 to 100 for preview.
     *
     * @param Request $request
     * @return Response JSON
     */
    public function get_odds(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['success' => false, 'message' => 'You must be logged in.'], 403);
        }
        $userId       = Session::get('user_id');
        $postData     = $request->getBody();
        $tournamentId = (int) ($postData['tournament_id'] ?? 0);

        // Deduct 1 gold to view odds
        if (! $this->tournamentModel->deductEntryFee($userId, 1)) {
            return $this->json(['success' => false, 'message' => 'Not enough gold! (Cost: 1 Gold)']);
        }

        $tournament = $this->tournamentModel->getTournament($tournamentId, $userId);
        if (! $tournament) {
            $this->tournamentModel->awardGold($userId, 1);
            return $this->json(['success' => false, 'message' => 'Tournament not found. Gold refunded.'], 404);
        }

        $wrestlerIds = json_decode($tournament->wrestler_ids, true);
        $matchups    = array_chunk($wrestlerIds, 2);

        // Use 100 simulations for preview (fast enough, still reasonable)
        $simulationCount = 100;
        $oddsData        = [];

        // Get the Doctrine repository for Roster entities
        $rosterRepo = $this->em->getRepository(\App\Entities\Roster::class);

        foreach ($matchups as $match) {
            $id1 = $match[0];
            $id2 = $match[1];

            // Load full entity objects – these implement WrestlerInterface
            $wrestler1 = $rosterRepo->find($id1);
            $wrestler2 = $rosterRepo->find($id2);

            if (! $wrestler1 || ! $wrestler2) {
                $this->tournamentModel->awardGold($userId, 1);
                return $this->json(['success' => false, 'message' => 'Invalid wrestler data. Gold refunded.'], 500);
            }

            try {
                $oddsResult = $this->simService->generateOdds($wrestler1, $wrestler2, $simulationCount);
                $odds       = [
                    'wrestler1'           => $oddsResult['w1_win_percent'],
                    'wrestler2'           => $oddsResult['w2_win_percent'],
                    'wrestler1_moneyline' => $this->convertToMoneyline($oddsResult['w1_win_percent']),
                    'wrestler2_moneyline' => $this->convertToMoneyline($oddsResult['w2_win_percent']),
                ];
            } catch (\RuntimeException $e) {
                $this->tournamentModel->awardGold($userId, 1);
                return $this->json(['success' => false, 'message' => 'Odds calculation failed. Gold refunded. Please try again later.'], 500);
            }

            $oddsData[] = [
                'wrestler1' => ['name' => $wrestler1->getName()],
                'wrestler2' => ['name' => $wrestler2->getName()],
                'odds'      => $odds,
            ];
        }

        return $this->json(['success' => true, 'odds_data' => $oddsData]);
    }

    /**
     * Simulates the tournament round based on user picks.
     * This uses the old Simulator model (non-cached) because it needs fresh results
     * and only runs once per round (not a performance bottleneck).
     *
     * @param Request $request
     * @return Response JSON
     */
    public function simulate(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['success' => false, 'message' => 'User not logged in.'], 403);
        }
        $userId       = Session::get('user_id');
        $postData     = $request->getBody();
        $tournamentId = (int) ($postData['tournament_id'] ?? 0);
        $userPicks    = $postData['picks'] ?? [];
        $tournament   = $this->tournamentModel->getTournament($tournamentId, $userId);
        if (! $tournament) {
            return $this->json(['success' => false, 'message' => 'Tournament not found.'], 404);
        }
        $wrestlerIds        = json_decode($tournament->wrestler_ids, true);
        $currentRound       = $tournament->current_round;
        $matchups           = array_chunk($wrestlerIds, 2);
        $actualWinners      = [];
        $allCorrect         = true;
        $incorrectPicksData = [];

        // Simulate each match using the old Simulator model (runs 1 simulation per match, not 1000)
        foreach ($matchups as $index => $match) {
            $result                = $this->simModel->run($match[0], $match[1]);
            $winnerId              = $result['winner_id'] ?? null;
            $actualWinners[$index] = $winnerId;
            $userPickId            = $userPicks[(string) $index] ?? null;
            if ($userPickId != $winnerId) {
                $allCorrect = false;
                if ($userPickId) {
                    $incorrectPicksData[] = [
                        'user_pick'     => $this->apiModel->getWrestlerById($userPickId),
                        'actual_winner' => $winnerId ? $this->apiModel->getWrestlerById($winnerId) : null,
                    ];
                }
            }
        }

        // Save user's picks for this round
        $currentUserPicks                          = json_decode($tournament->user_picks ?? '[]', true);
        $currentUserPicks['round' . $currentRound] = $userPicks;
        $this->tournamentModel->updateTournamentRound($tournamentId, $currentUserPicks, $currentRound);

        $response = [
            'success'              => true,
            'actual_winners'       => array_values($actualWinners),
            'winners_data'         => [],
            'incorrect_picks_data' => $incorrectPicksData,
        ];

        // Build winners data for display
        foreach ($actualWinners as $winnerId) {
            if ($winnerId) {
                $response['winners_data'][] = $this->apiModel->getWrestlerById($winnerId);
            } else {
                $response['winners_data'][] = null;
            }
        }

        if ($allCorrect) {
            $response['all_correct'] = true;
            $nextRoundWrestlers      = array_values(array_filter($actualWinners));
            if (count($nextRoundWrestlers) === 1) {
                // Tournament complete: award gold prize
                $reward = ($tournament->initial_size == 32) ? 100 : 50;
                $this->tournamentModel->awardGold($userId, $reward);
                $response['tournament_winner'] = $this->apiModel->getWrestlerById($nextRoundWrestlers[0]);
                $response['message']           = "Congratulations! You correctly picked all winners and won {$reward} Gold!";
            } else {
                // Advance to next round
                if (count($nextRoundWrestlers) != count($matchups)) {
                    // This should not happen if all matches have winners, but handle gracefully
                    $response['all_correct'] = false;
                    $response['message']     = 'Some matches ended in a draw. Tournament cannot continue.';
                    return $this->json($response);
                }
                $this->tournamentModel->updateTournamentRound($tournamentId, $currentUserPicks, $currentRound, $nextRoundWrestlers);
                $response['message']             = 'Congratulations! You picked all winners correctly!';
                $response['next_round_matchups'] = array_map(fn($id) => $this->apiModel->getWrestlerById($id), $nextRoundWrestlers);
            }
        } else {
            $response['all_correct']  = false;
            $response['can_continue'] = ($currentRound == 1); // Only first round can be continued with gold
            $response['message']      = 'You had one or more incorrect picks.';
        }
        return $this->json($response);
    }

    /**
     * Allows a user to pay gold to continue after a failed first round.
     *
     * @param Request $request
     * @return Response JSON
     */
    public function payToContinue(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['success' => false, 'message' => 'User not logged in.'], 403);
        }
        $userId       = Session::get('user_id');
        $postData     = $request->getBody();
        $tournamentId = (int) ($postData['tournament_id'] ?? 0);
        $tournament   = $this->tournamentModel->getTournament($tournamentId, $userId);
        if (! $tournament || $tournament->current_round != 1) {
            return $this->json(['success' => false, 'message' => 'Not eligible to continue.'], 400);
        }
        // Cost 3 gold to continue
        if (! $this->tournamentModel->payToAdvance($userId, 3)) {
            return $this->json(['success' => false, 'message' => 'Not enough gold to continue! (Cost: 3 Gold)']);
        }
        $wrestlerIds   = json_decode($tournament->wrestler_ids, true);
        $matchups      = array_chunk($wrestlerIds, 2);
        $actualWinners = [];
        foreach ($matchups as $match) {
            $result   = $this->simModel->run($match[0], $match[1]);
            $winnerId = $result['winner_id'] ?? null;
            if ($winnerId === null) {
                // Draw occurred – refund gold and abort
                $this->tournamentModel->awardGold($userId, 3);
                return $this->json(['success' => false, 'message' => 'A match ended in a draw. Cannot continue. Gold refunded.'], 400);
            }
            $actualWinners[] = $winnerId;
        }
        // Update tournament to the next round with the actual winners
        $this->tournamentModel->updateTournamentRound($tournamentId, json_decode($tournament->user_picks ?? '[]', true), 1, $actualWinners);
        $nextRoundWrestlers = array_map(fn($id) => $this->apiModel->getWrestlerById($id), $actualWinners);
        return $this->json([
            'success'             => true,
            'message'             => 'Payment successful! Advancing to Round 2!',
            'next_round_matchups' => $nextRoundWrestlers,
        ]);
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * Calculates odds using the old Simulator model (kept for backwards compatibility,
     * but no longer used in the main flow because SimulationService is preferred).
     * This method is kept only for potential legacy references; it is not used
     * in the current tournament logic because we call SimulationService directly.
     *
     * @param array $wrestler1
     * @param array $wrestler2
     * @return array ['wrestler1' => int, 'wrestler2' => int]
     * @throws \RuntimeException
     */
    private function calculateOdds(array $wrestler1, array $wrestler2): array
    {
        $simulation_count = 1000;
        $wrestler1_wins   = 0;
        $valid_sims       = 0;

        for ($i = 0; $i < $simulation_count; $i++) {
            $result = $this->simModel->run($wrestler1['wrestler_id'], $wrestler2['wrestler_id']);
            if (! isset($result['winner_id'])) {
                continue;
            }
            $winnerId = $result['winner_id'];
            if ($winnerId === null) {
                continue;
            }
            $valid_sims++;
            if ($winnerId == $wrestler1['wrestler_id']) {
                $wrestler1_wins++;
            }
        }

        if ($valid_sims === 0) {
            throw new \RuntimeException('Simulation engine failed to produce any valid results.');
        }

        $odds1 = round(($wrestler1_wins / $valid_sims) * 100);
        $odds2 = 100 - $odds1;
        return ['wrestler1' => $odds1, 'wrestler2' => $odds2];
    }

    /**
     * Converts a win percentage to a moneyline odd (e.g., -110, +150).
     * Used to display betting-style odds in the tournament interface.
     *
     * @param int $percentage Win percentage (0-100)
     * @return string Moneyline string
     */
    private function convertToMoneyline($percentage): string
    {
        if ($percentage <= 0) {
            return '+9900';
        }
        if ($percentage >= 100) {
            return '-9999';
        }
        if ($percentage == 50) {
            return '-110';
        }

        $vigFactor = 1.10;
        if ($percentage > 50) {
            $moneyline = -($percentage / (100 - $percentage)) * 100;
            return (string) round($moneyline * $vigFactor);
        } else {
            $moneyline = ((100 - $percentage) / $percentage) * 100;
            return '+' . min(9900, round($moneyline / $vigFactor));
        }
    }

    /**
     * Helper to create a themed tournament: fetches wrestlers using a dynamic method,
     * shuffles them, then renders the view.
     *
     * @param string $title
     * @param string $slogan
     * @param string $fetch_method Method name on Api model (e.g., 'get_wrestlers_by_weight')
     * @param mixed $value Parameter for the fetch method
     * @return Response
     */
    private function create_themed_tournament($title, $slogan, $fetch_method, $value)
    {
        $wrestlers = $this->apiModel->$fetch_method($value);
        shuffle($wrestlers);
        return $this->render_tournament_view($title, $slogan, $wrestlers);
    }

    /**
     * Renders the tournament bracket view.
     *
     * PERFORMANCE IMPROVEMENT:
     * - Uses SimulationService (cached) instead of calculateOdds()
     * - Reduces simulation count from 1000 to 100 for preview
     * - Fetches all needed wrestler entities in a single Doctrine query
     *
     * @param string $title
     * @param string $slogan
     * @param array $wrestlers List of wrestler arrays (from Api model)
     * @return Response
     */
    private function render_tournament_view($title, $slogan, $wrestlers)
    {
        // Determine tournament size (32 or 16, or fewer if not enough wrestlers)
        $participant_count = (count($wrestlers) >= 32) ? 32 : 16;
        if (count($wrestlers) < 16) {
            $participant_count = count($wrestlers);
            if ($participant_count % 2 != 0) {
                $participant_count--;
            }
        }
        $tournament_wrestlers = array_slice($wrestlers, 0, $participant_count);

        // --- NEW: Extract all wrestler IDs from the tournament participants ---
        $wrestlerIds = array_column($tournament_wrestlers, 'wrestler_id');

        // --- Fetch full Roster entities in one query (to avoid N+1) ---
        $rosterRepo = $this->em->getRepository(\App\Entities\Roster::class);
        $entities   = $rosterRepo->findBy(['wrestler_id' => $wrestlerIds]);

        // Map ID -> entity for quick lookup
        $entityMap = [];
        foreach ($entities as $entity) {
            $entityMap[$entity->getWrestlerId()] = $entity;
        }

        $matchups        = [];
        $simulationCount = 100; // Preview uses only 100 simulations (fast enough, still gives reasonable odds)

        for ($i = 0; $i < count($tournament_wrestlers) / 2; $i++) {
            $wrestler1_data = $tournament_wrestlers[$i * 2];
            $wrestler2_data = $tournament_wrestlers[($i * 2) + 1];

            // Retrieve the actual entity objects (required by SimulationService)
            $wrestler1_entity = $entityMap[$wrestler1_data['wrestler_id']] ?? null;
            $wrestler2_entity = $entityMap[$wrestler2_data['wrestler_id']] ?? null;

            if (! $wrestler1_entity || ! $wrestler2_entity) {
                // Fallback: skip this matchup or calculate odds the old way?
                // For simplicity, we'll skip and leave empty, but we could call calculateOdds as fallback.
                $matchups[] = [
                    'wrestler1' => $wrestler1_data,
                    'wrestler2' => $wrestler2_data,
                    'odds'      => ['wrestler1' => 50, 'wrestler2' => 50, 'wrestler1_moneyline' => '-110', 'wrestler2_moneyline' => '-110'],
                ];
                continue;
            }

            // Generate odds using cached SimulationService (fast)
            try {
                $oddsResult = $this->simService->generateOdds($wrestler1_entity, $wrestler2_entity, $simulationCount);
                $odds       = [
                    'wrestler1'           => $oddsResult['w1_win_percent'],
                    'wrestler2'           => $oddsResult['w2_win_percent'],
                    'wrestler1_moneyline' => $this->convertToMoneyline($oddsResult['w1_win_percent']),
                    'wrestler2_moneyline' => $this->convertToMoneyline($oddsResult['w2_win_percent']),
                ];
            } catch (\Exception $e) {
                // Fallback to 50/50 odds if simulation fails
                $odds = [
                    'wrestler1'           => 50,
                    'wrestler2'           => 50,
                    'wrestler1_moneyline' => '-110',
                    'wrestler2_moneyline' => '-110',
                ];
            }

            $matchups[] = [
                'wrestler1' => $wrestler1_data,
                'wrestler2' => $wrestler2_data,
                'odds'      => $odds,
            ];
        }

        $bracket_class = (count($matchups) <= 8) ? 'bracket-16' : 'bracket-32';
        return $this->view('tournament/index.html.twig', [
            'event_name'    => $title,
            'event_slogan'  => $slogan,
            'wrestlers'     => $tournament_wrestlers,
            'matchups'      => $matchups,
            'bracket_class' => $bracket_class,
        ]);
    }
}
