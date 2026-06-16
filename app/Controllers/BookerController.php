<?php
namespace App\Controllers;

use App\Entities\BookerEvent;
use App\Entities\BookerUserState;
use App\Entities\Roster;
use App\Entities\User;
use App\Entities\Venue;
use App\Services\BookerTournamentService;
use App\Services\SimulationService;
use App\Services\StipulationRules;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Twig\Environment;

/**
 * BookerController – handles the "Booker Mode" game mode.
 * Players act as promoters, booking venues, advertising, hiring wrestlers,
 * creating matches, and simulating events to earn profit.
 */
class BookerController extends BaseController
{
    protected EntityManager $em;
    protected SimulationService $simulationService;

    public function __construct(Environment $twig, EntityManager $em, SimulationService $simulationService)
    {
        parent::__construct($twig);
        $this->em                = $em;
        $this->simulationService = $simulationService;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Fetch the booker state for a user from the database.
     * Returns null if the user has no promoter name set (i.e., not yet started).
     *
     * @param User $user
     * @return array|null
     */
    private function getBookerState(User $user): ?array
    {
        $conn  = $this->em->getConnection();
        $state = $conn->fetchAssociative('SELECT * FROM booker_user_state WHERE user_id = ?', [$user->getUserId()]);
        if (! $state || empty($state['promoter_name'])) {
            return null;
        }
        return $state;
    }

    /**
     * Calculate the hiring fee for a wrestler based on level, overall rating,
     * champion status, and promotion prestige.
     *
     * @param Roster $wrestler
     * @param string|null $championType 'world', 'us', or null
     * @param float $prestigeMultiplier
     * @return int
     */
    private function calculateWrestlerFee(Roster $wrestler, ?string $championType = null, float $prestigeMultiplier = 1.0): int
    {
        $level   = $wrestler->getLvl();
        $overall = $wrestler->getOverallRating();

        if ($level >= 9) {
            $base       = 800;
            $multiplier = 5;
        } elseif ($level >= 7) {
            $base       = 400;
            $multiplier = 3;
        } elseif ($level >= 4) {
            $base       = 150;
            $multiplier = 2;
        } else {
            $base       = 50;
            $multiplier = 1;
        }

        $fee = $base + ($multiplier * $overall);

        // Champion multiplier
        if ($championType === 'world') {
            $fee *= 5;
        } elseif ($championType === 'us') {
            $fee *= 2.5;
        }

        // Prestige multiplier (scales over time)
        $fee *= $prestigeMultiplier;

        return (int) round($fee);
    }

    /**
     * Calculate projected attendance based on venue capacity, advertising spend,
     * and the star power of hired wrestlers.
     *
     * @param array $venue
     * @param int $advertisingSpend
     * @param array $hiredWrestlers (Roster entities)
     * @param bool $isPpv
     * @param array $titleMatches
     * @return int
     */
    private function calculateProjectedAttendance(array $venue, int $advertisingSpend, array $hiredWrestlers, bool $isPpv, array $titleMatches = []): int
    {
        $capacity = $venue['capacity'];
        $base     = $capacity * 0.3;
        $adBoost  = $capacity * 0.4 * (1 - exp(-$advertisingSpend / 2000));

        // Star power (already includes main event because all wrestlers are in sum)
        $starPower = 0;
        foreach ($hiredWrestlers as $w) {
            $starPower += ($w->getLvl() * 100) + ($w->getOverallRating() * 10);
        }
        // Cap star power to 60% of remaining capacity
        $remaining = $capacity - ($base + $adBoost);
        $starPower = min($remaining * 0.6, $starPower);

        $attendance = $base + $adBoost + $starPower;

        // Title match bonuses
        $titleBonus = 1.0;
        if (in_array('world', $titleMatches)) {
            $titleBonus += 0.2;
        }

        if (in_array('us', $titleMatches)) {
            $titleBonus += 0.1;
        }

        $attendance *= $titleBonus;

        // PPV multiplier (people more likely to buy tickets for big shows)
        if ($isPpv) {
            $attendance *= 1.5;
        }

        return min($capacity, (int) round($attendance));
    }

    /**
     * Calculate revenue from ticket sales.
     * Ticket price scales modestly with venue capacity.
     *
     * @param int $attendance
     * @param array $venue
     * @return int
     */
    private function calculateRevenue(int $attendance, array $venue): int
    {
        $ticketPrice = 5 * (1 + $venue['capacity'] / 10000);
        $ticketPrice = (int) round($ticketPrice);
        return $attendance * $ticketPrice;
    }

    /**
     * Calculate PPV revenue based on star power.
     *
     * @param array $hiredWrestlers
     * @return int
     */
    private function calculatePpvRevenue(array $hiredWrestlers): int
    {
        $basePrice = 50; // gold per buy
        $starPower = 0;
        foreach ($hiredWrestlers as $w) {
            $starPower += ($w->getLvl() * 100) + ($w->getOverallRating() * 10);
        }
        $maxBuys = 500000;
        $buys    = min($maxBuys, $starPower * 100); // arbitrary scaling
        return (int) round($buys * $basePrice);
    }

    /**
     * Calculate a star rating (0–5) from a match log.
     * Considers reversals, finishers, momentum swings, and turn count.
     *
     * @param array $log
     * @return float
     */
    private function calculateStarRating(array $log): float
    {
        $reversals       = 0;
        $finishersUsed   = 0;
        $maxMomentumDiff = 0;
        $turnCount       = 0;

        foreach ($log as $entry) {
            if ($entry['type'] === 'turn') {
                $turnCount++;
            }

            if ($entry['type'] === 'reversal') {
                $reversals++;
            }

            if ($entry['type'] === 'move' && isset($entry['data']['move_name'])) {
                $moveName         = $entry['data']['move_name'];
                $finisherKeywords = ['Splash', 'Bomb', 'Cutter', 'Stunner', 'Piledriver', 'Clutch', 'Lock', 'Kinshasa', 'RKO', 'Pedigree', 'F5', 'Attitude Adjustment'];
                foreach ($finisherKeywords as $kw) {
                    if (stripos($moveName, $kw) !== false) {
                        $finishersUsed++;
                        break;
                    }
                }
            }
            if ($entry['type'] === 'update') {
                $w1Mom = $entry['data']['w1']['momentum'] ?? 50;
                $w2Mom = $entry['data']['w2']['momentum'] ?? 50;
                $diff  = abs($w1Mom - $w2Mom);
                if ($diff > $maxMomentumDiff) {
                    $maxMomentumDiff = $diff;
                }

            }
        }

        $rating = 2.0; // base rating
        if ($turnCount > 20) {
            $rating += 0.5;
        }

        $rating += min(1.0, $reversals * 0.1);
        $rating += min(0.5, $finishersUsed * 0.1);
        if ($maxMomentumDiff > 30) {
            $rating += 0.2;
        }

        $rating += mt_rand(0, 50) / 100; // small random factor
        return min(5.0, round($rating, 1));
    }

    // ==================== PAGE RENDERING & FORM HANDLING ====================

    /**
     * Show the promoter name entry form.
     */
    public function enterName(): Response
    {
        return $this->view('booker/enter_name.twig');
    }

    /**
     * Process the name submission – creates or updates the booker_user_state record.
     * If the user has not yet run initial tournaments, redirect to tournament preview.
     */
    public function postEnterName(Request $request): Response
    {
        $data = $request->getBody();
        $name = trim($data['promoter_name'] ?? '');
        if (empty($name)) {
            return redirect('/booker/enter-name')->with('error', 'Please enter a name.');
        }

        $user = $this->em->find(User::class, Session::get('user_id'));
        $conn = $this->em->getConnection();

        $existing = $conn->fetchAssociative('SELECT * FROM booker_user_state WHERE user_id = ?', [$user->getUserId()]);

        if ($existing) {
            $conn->update('booker_user_state', [
                'promoter_name'   => $name,
                'balance'         => $existing['balance'] ?? 5000,
                'total_profit'    => $existing['total_profit'] ?? 0,
                'retired'         => $existing['retired'] ?? 0,
                'retired_balance' => $existing['retired_balance'] ?? null,
            ], ['user_id' => $user->getUserId()]);
        } else {
            $conn->insert('booker_user_state', [
                'user_id'         => $user->getUserId(),
                'promoter_name'   => $name,
                'balance'         => 5000,
                'total_profit'    => 0,
                'retired'         => 0,
                'retired_balance' => null,
            ]);
        }

        $state = $conn->fetchAssociative('SELECT * FROM booker_user_state WHERE user_id = ?', [$user->getUserId()]);
        if (! $state['has_initial_tournaments_run']) {
            return redirect('/booker/initial-tournaments');
        }

        return redirect('/booker');
    }

    /**
     * Show the initial tournament bracket preview.
     * Generates two pools of 32 wrestlers each (World: level≥8, US: level 6-8)
     * and stores the wrestler IDs in session for later simulation.
     */
    public function showInitialTournaments(): Response
    {
        $user  = $this->em->find(User::class, Session::get('user_id'));
        $state = $this->getBookerState($user);
        if (! $state || $state['has_initial_tournaments_run']) {
            return redirect('/booker');
        }

        $pools = $this->getInitialTournamentPools();

        // Store only IDs in session so the same wrestlers are used when running tournaments
        Session::set('initial_tournament_pools', [
            'world_ids' => array_map(fn($w) => $w->getWrestlerId(), $pools['world_pool']),
            'us_ids'    => array_map(fn($w) => $w->getWrestlerId(), $pools['us_pool']),
        ]);

        return $this->view('booker/initial_tournaments.twig', [
            'world_tournament' => $pools['world_pool'],
            'us_tournament'    => $pools['us_pool'],
        ]);
    }

    /**
     * Run the initial tournaments using the stored wrestler pools.
     * Creates a dummy event, simulates the tournaments via BookerTournamentService,
     * stores champions, and redirects to the event booking flow.
     */
    public function runInitialTournaments(Request $request): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user) {
            return redirect('/login');
        }

        $stateEntity = $this->em->getRepository(BookerUserState::class)->find($user->getUserId());
        if (! $stateEntity || $stateEntity->hasInitialTournamentsRun()) {
            Session::flash('error', 'Tournaments already run.');
            return redirect('/booker');
        }

        // Retrieve stored pools from session or fallback to regeneration
        $stored = Session::get('initial_tournament_pools');
        if (! $stored || empty($stored['world_ids']) || empty($stored['us_ids'])) {
            $pools     = $this->getInitialTournamentPools();
            $worldPool = $pools['world_pool'];
            $usPool    = $pools['us_pool'];
        } else {
            $repo      = $this->em->getRepository(Roster::class);
            $worldPool = array_filter(array_map(fn($id) => $repo->find($id), $stored['world_ids']));
            $usPool    = array_filter(array_map(fn($id) => $repo->find($id), $stored['us_ids']));
        }

        $venueRepo    = $this->em->getRepository(Venue::class);
        $defaultVenue = $venueRepo->findOneBy([], ['id' => 'ASC']);
        if (! $defaultVenue) {
            throw new \Exception('No venues found.');
        }

        // Create a BookerEvent record for the tournament
        $tournamentEvent = new BookerEvent();
        $tournamentEvent->setUserId($user->getUserId());
        $tournamentEvent->setVenue($defaultVenue);
        $tournamentEvent->setEventName('Initial Championship Tournaments');
        $this->em->persist($tournamentEvent);
        $this->em->flush();

        // Run the tournaments using the service
        $tournamentService = new BookerTournamentService($this->em, $this->simulationService);
        $result            = $tournamentService->runInitialTournamentsWithPools($user, $stateEntity, $tournamentEvent, $worldPool, $usPool);

        Session::set('tournament_results', [
            'world' => $result['world_champion']->getName(),
            'us'    => $result['us_champion']->getName(),
        ]);
        Session::flash('success', 'Champions crowned!');
        return redirect('/booker/new-event');
    }

    /**
     * Main dashboard for booker mode. Shows balance, total profit, and last event.
     * If retired, shows leaderboard instead.
     */
    public function index(): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user) {
            return redirect('/login');
        }

        $state = $this->getBookerState($user);
        if (! $state) {
            return redirect('/booker/enter-name')->with('error', 'This is a test');
        }

        if ($state['retired']) {
            $leaderboard = $this->getLeaderboard($user);
            return $this->view('booker/retired.twig', [
                'balance'       => $state['retired_balance'],
                'total_profit'  => $state['total_profit'],
                'promoter_name' => $state['promoter_name'],
                'leaderboard'   => $leaderboard['top20'],
                'user_rank'     => $leaderboard['user_rank'],
            ]);
        }

        $conn      = $this->em->getConnection();
        $lastEvent = $conn->fetchAssociative(
            'SELECT e.*, v.name as venue_name FROM booker_events e
             LEFT JOIN venues v ON e.venue_id = v.id
             WHERE e.user_id = ? ORDER BY e.event_date DESC LIMIT 1',
            [$user->getUserId()]
        );

        return $this->view('booker/index.twig', [
            'balance'       => $state['balance'],
            'total_profit'  => $state['total_profit'],
            'last_event'    => $lastEvent,
            'promoter_name' => $state['promoter_name'],
        ]);
    }

    /**
     * Step 1 of event booking: select a venue.
     */
    public function newEvent(): Response
    {
        $user  = $this->em->find(User::class, Session::get('user_id'));
        $state = $this->getBookerState($user);
        if (! $state) {
            return redirect('/booker/enter-name');
        }

        $conn   = $this->em->getConnection();
        $venues = $conn->fetchAllAssociative('SELECT * FROM venues ORDER BY capacity ASC');
        return $this->view('booker/step_venue.twig', [
            'venues'  => $venues,
            'balance' => $state['balance'],
        ]);
    }

    /**
     * Process venue selection and store in session.
     */
    public function postVenue(Request $request): Response
    {
        $data    = $request->getBody();
        $venueId = (int) ($data['venue_id'] ?? 0);
        if (! $venueId) {
            return redirect('/booker/new-event')->with('error', 'Please select a venue.');
        }

        $conn  = $this->em->getConnection();
        $venue = $conn->fetchAssociative('SELECT * FROM venues WHERE id = ?', [$venueId]);

        $eventName        = trim($data['event_name'] ?? '');
        $isPpv            = isset($data['is_ppv']) ? 1 : 0;
        $specialMatchType = $data['special_match_type'] ?? null;

        Session::set('booker_event', [
            'venue_id'           => $venueId,
            'venue_cost'         => $venue['rental_cost'],
            'event_name'         => $eventName,
            'is_ppv'             => $isPpv,
            'special_match_type' => $specialMatchType,
        ]);
        return redirect('/booker/advertising');
    }

    /**
     * Step 2: set advertising spend.
     */
    public function advertising(): Response
    {
        $event = Session::get('booker_event');
        if (! $event || ! isset($event['venue_id'])) {
            return redirect('/booker/new-event');
        }

        $conn  = $this->em->getConnection();
        $venue = $conn->fetchAssociative('SELECT * FROM venues WHERE id = ?', [$event['venue_id']]);

        $user  = $this->em->find(User::class, Session::get('user_id'));
        $state = $this->getBookerState($user);

        return $this->view('booker/step_advertising.twig', [
            'venue'   => $venue,
            'balance' => $state['balance'],
        ]);
    }

    /**
     * Process advertising spend.
     */
    public function postAdvertising(Request $request): Response
    {
        $data    = $request->getBody();
        $adSpend = (int) ($data['advertising_spend'] ?? 0);
        $adSpend = max(0, min(1000, $adSpend));

        $event                      = Session::get('booker_event');
        $event['advertising_spend'] = $adSpend;
        Session::set('booker_event', $event);
        return redirect('/booker/hire');
    }

    /**
     * Step 3: hire wrestlers. Shows available wrestlers with their fees,
     * current champions, and a budget tracker.
     */
    public function hireWrestlers(): Response
    {
        $event = Session::get('booker_event');
        if (! $event || ! isset($event['venue_id'])) {
            return redirect('/booker/new-event');
        }

        $user               = $this->em->find(User::class, Session::get('user_id'));
        $state              = $this->getBookerState($user);
        $originalBalance    = $state['balance'];
        $prestigeMultiplier = (float) ($state['prestige_multiplier'] ?? 1.0);

        $venueCost       = $event['venue_cost'] ?? 0;
        $adSpend         = $event['advertising_spend'] ?? 0;
        $remainingBudget = max(0, $originalBalance - $venueCost - $adSpend);

        // Get champion IDs and their types
        $worldChampId = $state['world_champion_id'] ?? null;
        $usChampId    = $state['us_champion_id'] ?? null;

        $roster            = $this->em->getRepository(Roster::class)->findAll();
        $wrestlersWithFees = [];

        foreach ($roster as $w) {
            $championType = null;
            if ($worldChampId && $w->getWrestlerId() == $worldChampId) {
                $championType = 'world';
            } elseif ($usChampId && $w->getWrestlerId() == $usChampId) {
                $championType = 'us';
            }

            $fee = $this->calculateWrestlerFee($w, $championType, $prestigeMultiplier);

            $wrestlersWithFees[] = [
                'id'             => $w->getWrestlerId(),
                'name'           => $w->getName(),
                'level'          => $w->getLvl(),
                'overall'        => $w->getOverallRating(),
                'fee'            => $fee,
                'image'          => $w->getImage(),
                'is_champion'    => ($championType !== null),
                'champion_title' => $championType === 'world' ? 'World Champion' : ($championType === 'us' ? 'US Champion' : null),
            ];
        }

        return $this->view('booker/step_hire.twig', [
            'wrestlers'        => $wrestlersWithFees,
            'remaining_budget' => $remainingBudget,
            'venue_cost'       => $venueCost,
            'ad_spend'         => $adSpend,
            'original_balance' => $originalBalance,
            'world_champion'   => $worldChampId ? $this->em->find(Roster::class, $worldChampId)->getName() : null,
            'us_champion'      => $usChampId ? $this->em->find(Roster::class, $usChampId)->getName() : null,
        ]);
    }

    /**
     * Process hired wrestlers, check budget, and store in session.
     */
    public function postHireWrestlers(Request $request): Response
    {
        $data        = $request->getBody();
        $selectedIds = $data['wrestler_ids'] ?? [];
        if (empty($selectedIds)) {
            return redirect('/booker/hire')->with('error', 'You must hire at least one wrestler.');
        }

        $user               = $this->em->find(User::class, Session::get('user_id'));
        $state              = $this->getBookerState($user);
        $originalBalance    = $state['balance'];
        $prestigeMultiplier = (float) ($state['prestige_multiplier'] ?? 1.0);
        $event              = Session::get('booker_event');
        $venueCost          = $event['venue_cost'] ?? 0;
        $adSpend            = $event['advertising_spend'] ?? 0;
        $remainingBudget    = max(0, $originalBalance - $venueCost - $adSpend);

        $worldChampId = $state['world_champion_id'] ?? null;
        $usChampId    = $state['us_champion_id'] ?? null;

        $totalFee = 0;
        $hired    = [];

        foreach ($selectedIds as $wrestlerId) {
            $wrestler = $this->em->find(Roster::class, (int) $wrestlerId);
            if ($wrestler) {
                $championType = null;
                if ($worldChampId && $wrestler->getWrestlerId() == $worldChampId) {
                    $championType = 'world';
                } elseif ($usChampId && $wrestler->getWrestlerId() == $usChampId) {
                    $championType = 'us';
                }
                $fee       = $this->calculateWrestlerFee($wrestler, $championType, $prestigeMultiplier);
                $totalFee += $fee;
                $hired[]   = [
                    'id'   => $wrestler->getWrestlerId(),
                    'name' => $wrestler->getName(),
                    'fee'  => $fee,
                ];
            }
        }

        if ($totalFee > $remainingBudget) {
            return redirect('/booker/hire')->with('error', "Hiring cost ({$totalFee}) exceeds your remaining budget ({$remainingBudget}).");
        }

        $event['hired_wrestlers']     = $hired;
        $event['hired_wrestlers_ids'] = $selectedIds;
        $event['hired_total_fee']     = $totalFee;
        Session::set('booker_event', $event);

        return redirect('/booker/matches');
    }

    /**
     * Step 4: Book matches. Shows hired wrestlers and budget summary.
     */
    public function bookMatches(): Response
    {
        $event = Session::get('booker_event');
        if (! $event || ! isset($event['hired_wrestlers'])) {
            return redirect('/booker/hire');
        }

        $user              = $this->em->find(User::class, Session::get('user_id'));
        $state             = $this->getBookerState($user);
        $originalBalance   = $state['balance'];
        $venueCost         = $event['venue_cost'] ?? 0;
        $adSpend           = $event['advertising_spend'] ?? 0;
        $hiredTotal        = $event['hired_total_fee'] ?? 0;
        $totalSpent        = $venueCost + $adSpend + $hiredTotal;
        $remainingAfterAll = $originalBalance - $totalSpent;

        $hired = $event['hired_wrestlers'];

        // Get champion IDs for conditional championship checkbox
        $worldChampionId = $state['world_champion_id'] ?? null;
        $usChampionId    = $state['us_champion_id'] ?? null;
        $championIds     = [];
        if ($worldChampionId) {
            $championIds[] = (int) $worldChampionId;
        }

        if ($usChampionId) {
            $championIds[] = (int) $usChampionId;
        }

        return $this->view('booker/step_matches.twig', [
            'hired_wrestlers'     => $hired,
            'total_hired_fee'     => $hiredTotal,
            'venue_cost'          => $venueCost,
            'ad_spend'            => $adSpend,
            'original_balance'    => $originalBalance,
            'remaining_after_all' => $remainingAfterAll,
            'champion_ids'        => $championIds,
        ]);
    }

    /**
     * Process booked matches, enforce MVP rule (no wrestler in two matches),
     * validate title matches, and store stipulations.
     */
    public function postBookMatches(Request $request): Response
    {
        $data    = $request->getBody();
        $matches = $data['matches'] ?? [];
        if (empty($matches)) {
            return redirect('/booker/matches')->with('error', 'You must book at least one match.');
        }

        $user         = $this->em->find(User::class, Session::get('user_id'));
        $state        = $this->getBookerState($user);
        $worldChampId = $state['world_champion_id'] ?? null;
        $usChampId    = $state['us_champion_id'] ?? null;

        $usedWrestlers = [];
        $finalMatches  = [];

        foreach ($matches as $idx => $match) {
            $w1 = (int) ($match['wrestler1'] ?? 0);
            $w2 = (int) ($match['wrestler2'] ?? 0);
            if (! $w1 || ! $w2) {
                return redirect('/booker/matches')->with('error', 'Each match must have two wrestlers.');
            }
            if (in_array($w1, $usedWrestlers) || in_array($w2, $usedWrestlers)) {
                return redirect('/booker/matches')->with('error', 'A wrestler cannot be booked in more than one match (MVP restriction).');
            }
            $usedWrestlers[] = $w1;
            $usedWrestlers[] = $w2;

            $isTitleMatch = isset($match['is_title_match']) && $match['is_title_match'];
            $titleType    = null;
            if ($isTitleMatch) {
                if ($worldChampId && ($w1 == $worldChampId || $w2 == $worldChampId)) {
                    $titleType = 'world';
                } elseif ($usChampId && ($w1 == $usChampId || $w2 == $usChampId)) {
                    $titleType = 'us';
                } else {
                    return redirect('/booker/matches')->with('error', 'A title match must include the current champion.');
                }
            }

            $stipulation        = $match['stipulation'] ?? null;
            $finalMatches[$idx] = [
                'wrestler1'      => $w1,
                'wrestler2'      => $w2,
                'is_title_match' => $isTitleMatch ? 1 : 0,
                'title_type'     => $titleType,
                'stipulation'    => $stipulation,
                'is_main_event'  => 0,
            ];
        }

        // Mark the last match as main event
        if (! empty($finalMatches)) {
            $lastKey                                 = array_key_last($finalMatches);
            $finalMatches[$lastKey]['is_main_event'] = 1;
        }

        $event            = Session::get('booker_event');
        $event['matches'] = $finalMatches;
        Session::set('booker_event', $event);

        return redirect('/booker/simulate');
    }

    /**
     * Step 5: simulate the event.
     * Calculates attendance, revenue, profit, runs each match simulation,
     * records matches and title changes, updates user balance, and displays a summary.
     */
    public function simulateEvent(): Response
    {
        $eventData = Session::get('booker_event');
        if (! $eventData || ! isset($eventData['matches'])) {
            return redirect('/booker/matches');
        }

        $user  = $this->em->find(User::class, Session::get('user_id'));
        $state = $this->getBookerState($user);
        $conn  = $this->em->getConnection();

        $venue            = $conn->fetchAssociative('SELECT * FROM venues WHERE id = ?', [$eventData['venue_id']]);
        $adSpend          = $eventData['advertising_spend'] ?? 0;
        $isPpv            = $eventData['is_ppv'] ?? 0;
        $specialMatchType = $eventData['special_match_type'] ?? null;

        // Load hired wrestlers as entities
        $hiredWrestlers = [];
        foreach ($eventData['hired_wrestlers_ids'] as $wid) {
            $w = $this->em->find(Roster::class, (int) $wid);
            if ($w) {
                $hiredWrestlers[] = $w;
            }

        }

        // PPV cooldown check
        if ($isPpv && $state['events_since_last_ppv'] < 10) {
            return redirect('/booker/new-event')->with('error', 'You must run 10 regular events before another PPV.');
        }

        // Calculate live attendance & revenue
        $titleMatchesOnCard = [];
        foreach ($eventData['matches'] as $match) {
            if ($match['is_title_match']) {
                $titleMatchesOnCard[] = $match['title_type'];
            }

        }
        $attendance   = $this->calculateProjectedAttendance($venue, $adSpend, $hiredWrestlers, $isPpv, $titleMatchesOnCard);
        $liveRevenue  = $this->calculateRevenue($attendance, $venue);
        $ppvRevenue   = $isPpv ? $this->calculatePpvRevenue($hiredWrestlers) : 0;
        $totalRevenue = $liveRevenue + $ppvRevenue;

        $venueCost    = $venue['rental_cost'];
        $wrestlerFees = $eventData['hired_total_fee'];
        $totalCost    = $venueCost + $adSpend + $wrestlerFees;
        $profit       = $totalRevenue - $totalCost;

        $eventName = $eventData['event_name'] ?? '';
        if (empty($eventName)) {
            $eventName = 'Event ' . date('Y-m-d H:i:s');
        }

        // Insert event record
        $conn->insert('booker_events', [
            'user_id'            => $user->getUserId(),
            'venue_id'           => $venue['id'],
            'event_name'         => $eventName,
            'event_date'         => date('Y-m-d H:i:s'),
            'advertising_spend'  => $adSpend,
            'attendance'         => $attendance,
            'total_revenue'      => $liveRevenue,
            'total_cost'         => $totalCost,
            'profit'             => $profit,
            'is_retired'         => 0,
            'is_ppv'             => $isPpv,
            'ppv_revenue'        => $ppvRevenue ?: null,
            'special_match_type' => $specialMatchType,
        ]);
        $eventId = $conn->lastInsertId();

        // Track current champions during the event (may change after each match)
        $currentWorldChampId = $state['world_champion_id'];
        $currentUsChampId    = $state['us_champion_id'];

        $matches          = $eventData['matches'];
        $simulatedMatches = [];
        $totalStarRating  = 0;

        foreach ($matches as $order => $match) {
            $w1 = $this->em->find(Roster::class, (int) $match['wrestler1']);
            $w2 = $this->em->find(Roster::class, (int) $match['wrestler2']);
            if (! $w1 || ! $w2) {
                continue;
            }

            $stipulation = $match['stipulation'] ?? null;
            $log         = $this->simulationService->runVisualMatch($w1, $w2, true, $stipulation);

            $endEvent      = end($log);
            $winnerId      = null;
            $winnerImage   = null;
            $victoryMethod = null;
            if ($endEvent['type'] === 'end') {
                $winnerKey = $endEvent['data']['winner_id'];
                if ($winnerKey === 'w1') {
                    $winnerId    = $w1->getWrestlerId();
                    $winnerImage = $w1->getImage();
                } elseif ($winnerKey === 'w2') {
                    $winnerId    = $w2->getWrestlerId();
                    $winnerImage = $w2->getImage();
                }
                $victoryMethod = $endEvent['data']['victory_method'] ?? null;
            }

            $starRating      = $this->calculateStarRating($log);
            $totalStarRating += $starRating;

            // Title match handling
            $titleId      = null;
            $isTitleMatch = isset($match['is_title_match']) && $match['is_title_match'];
            $titleChanged = false;
            $titleName    = null;

            if ($isTitleMatch && $winnerId) {
                // Determine which title is involved based on current champions
                $titleType = null;
                if ($currentWorldChampId && ($winnerId == $currentWorldChampId || $w1->getWrestlerId() == $currentWorldChampId || $w2->getWrestlerId() == $currentWorldChampId)) {
                    $titleType = 'world';
                } elseif ($currentUsChampId && ($winnerId == $currentUsChampId || $w1->getWrestlerId() == $currentUsChampId || $w2->getWrestlerId() == $currentUsChampId)) {
                    $titleType = 'us';
                }

                if ($titleType) {
                    $currentChampId = ($titleType === 'world') ? $currentWorldChampId : $currentUsChampId;
                    if ($winnerId != $currentChampId) {
                        $titleChanged = true;
                        $titleName    = ucfirst($titleType) . ' Champion';
                    }

                    // Record the championship (same as before)
                    if ($winnerId != $currentChampId) {
                        $repo   = $this->em->getRepository(\App\Entities\Championship::class);
                        $active = $repo->findOneBy(['title_name' => ucfirst($titleType), 'date_lost' => null]);
                        if ($active) {
                            $active->setDateLost(new \DateTime());
                            $this->em->persist($active);
                        }
                        $newReign = new \App\Entities\Championship();
                        $newReign->setTitleName(ucfirst($titleType));
                        $newReign->setWrestlerId((string) $winnerId);
                        $newReign->setEventId($eventId);
                        $newReign->setPreviousChampionId($active ? (string) $active->getWrestlerId() : null);
                        $newReign->setReignNumber(($active ? $active->getReignNumber() : 0) + 1);
                        $newReign->setDateWon(new \DateTime());
                        $this->em->persist($newReign);
                        $this->em->flush();

                        $conn->update('booker_user_state', [
                            $titleType . '_champion_id' => $winnerId,
                        ], ['user_id' => $user->getUserId()]);
                        $titleId = $newReign->getId();

                        // Update current champion for subsequent matches on the same card
                        if ($titleType === 'world') {
                            $currentWorldChampId = $winnerId;
                        } else {
                            $currentUsChampId = $winnerId;
                        }
                    } else {
                        $repo   = $this->em->getRepository(\App\Entities\Championship::class);
                        $active = $repo->findOneBy(['title_name' => ucfirst($titleType), 'date_lost' => null]);
                        if ($active) {
                            $titleId = $active->getId();
                        }

                    }
                }
            }

            // Insert unified match record
            $conn->insert('matches', [
                'match_type'       => 'single',
                'wrestler1_id'     => (string) $w1->getWrestlerId(),
                'wrestler2_id'     => (string) $w2->getWrestlerId(),
                'winner_id'        => $winnerId ? (string) $winnerId : null,
                'is_draw'          => $winnerId ? 0 : 1,
                'source'           => 'booker',
                'source_id'        => null,
                'event_id'         => $eventId,
                'stipulation'      => $stipulation,
                'title_id'         => $titleId,
                'duration_seconds' => rand(300, 1800),
                'log'              => json_encode($log),
                'star_rating'      => $starRating,
                'is_main_event'    => $match['is_main_event'] ?? 0,
                'match_date'       => date('Y-m-d H:i:s'),
            ]);

            $simulatedMatches[] = [
                'order'          => $order + 1,
                'wrestler1'      => $w1->getName(),
                'wrestler2'      => $w2->getName(),
                'winner'         => $winnerId ? ($winnerId == $w1->getWrestlerId() ? $w1->getName() : $w2->getName()) : 'Draw',
                'winner_image'   => $winnerImage,
                'stipulation'    => $stipulation,
                'star_rating'    => $starRating,
                'log'            => json_encode($log),
                'victory_method' => $victoryMethod,
                'title_changed'  => $titleChanged,
                'title_name'     => $titleName,
            ];
        }

        $averageStarRating = count($simulatedMatches) > 0 ? round($totalStarRating / count($simulatedMatches), 1) : 0;

        // Update user balance and profit
        $newBalance     = $state['balance'] + $profit;
        $newTotalProfit = $state['total_profit'] + ($profit > 0 ? $profit : 0);
        $conn->update('booker_user_state', [
            'balance'      => $newBalance,
            'total_profit' => $newTotalProfit,
        ], ['user_id' => $user->getUserId()]);

        // Increase prestige multiplier (capped at 2.0)
        $currentPrestige = (float) ($state['prestige_multiplier'] ?? 1.0);
        $newPrestige     = min(2.0, $currentPrestige + 0.01);
        $conn->update('booker_user_state', ['prestige_multiplier' => $newPrestige], ['user_id' => $user->getUserId()]);

        // Update PPV cooldown counter
        if ($isPpv) {
            $conn->update('booker_user_state', ['events_since_last_ppv' => 0], ['user_id' => $user->getUserId()]);
        } else {
            $conn->executeStatement('UPDATE booker_user_state SET events_since_last_ppv = events_since_last_ppv + 1 WHERE user_id = ?', [$user->getUserId()]);
        }

        Session::remove('booker_event');

        return $this->view('booker/event_summary.twig', [
            'event_id'            => $eventId,
            'venue'               => $venue['name'],
            'attendance'          => $attendance,
            'revenue'             => $liveRevenue,
            'ppv_revenue'         => $ppvRevenue,
            'total_revenue'       => $totalRevenue,
            'total_cost'          => $totalCost,
            'profit'              => $profit,
            'new_balance'         => $newBalance,
            'matches'             => $simulatedMatches,
            'average_star_rating' => $averageStarRating,
            'is_ppv'              => $isPpv,
            'special_match_type'  => $specialMatchType,
        ]);
    }

    /**
     * Get leaderboard of retired bookers (top 20 by final balance) and the user's rank.
     *
     * @param User|null $currentUser
     * @return array
     */
    private function getLeaderboard(?User $currentUser = null): array
    {
        $conn  = $this->em->getConnection();
        $top20 = $conn->fetchAllAssociative('
            SELECT promoter_name, retired_balance
            FROM booker_user_state
            WHERE retired = 1 AND retired_balance IS NOT NULL
            ORDER BY retired_balance DESC
            LIMIT 20
        ');

        $userRank = null;
        if ($currentUser) {
            $rankStmt = $conn->prepare('
                SELECT COUNT(*) + 1 as rank
                FROM booker_user_state
                WHERE retired = 1 AND retired_balance > (
                    SELECT retired_balance FROM booker_user_state WHERE user_id = :user_id AND retired = 1
                )
            ');
            $rankStmt->execute(['user_id' => $currentUser->getUserId()]);
            $userRank = $rankStmt->fetchOne();
            if ($userRank === false) {
                $userRank = null;
            }

        }

        return [
            'top20'     => $top20,
            'user_rank' => $userRank,
        ];
    }

    /**
     * Retire the current promotion. Sets retired flag and records final balance.
     * Shows leaderboard after retirement.
     */
    public function retire(): Response
    {
        $user  = $this->em->find(User::class, Session::get('user_id'));
        $state = $this->getBookerState($user);
        if (! $state || $state['retired']) {
            return redirect('/booker')->with('error', 'Already retired or no active promotion.');
        }

        $conn = $this->em->getConnection();
        $conn->update('booker_user_state', [
            'retired'         => 1,
            'retired_balance' => $state['balance'],
        ], ['user_id' => $user->getUserId()]);

        $leaderboard = $this->getLeaderboard($user);

        return $this->view('booker/retired.twig', [
            'balance'       => $state['balance'],
            'total_profit'  => $state['total_profit'],
            'promoter_name' => $state['promoter_name'],
            'leaderboard'   => $leaderboard['top20'],
            'user_rank'     => $leaderboard['user_rank'],
        ]);
    }

    /**
     * Generate the initial tournament pools (World and US) without running matches.
     * World: 32 random wrestlers level 8-10.
     * US: 32 random wrestlers level 6-8, excluding those already in the World pool.
     * If not enough wrestlers are found, lower levels are used as fallback.
     *
     * @return array ['world_pool' => Roster[], 'us_pool' => Roster[]]
     */
    private function getInitialTournamentPools(): array
    {
        $repo = $this->em->getRepository(Roster::class);

        // World: all wrestlers level 8 or higher
        $worldCandidates = $repo->findBy(['lvl' => [8, 9, 10]]);
        shuffle($worldCandidates);
        $worldPool = array_slice($worldCandidates, 0, 32);

        // US: all wrestlers level 6,7,8
        $usCandidates = $repo->findBy(['lvl' => [6, 7, 8]]);
        shuffle($usCandidates);
        // Remove any that are already in world pool to avoid duplicate champions
        $worldIds     = array_map(fn($w) => $w->getWrestlerId(), $worldPool);
        $usCandidates = array_filter($usCandidates, fn($w) => ! in_array($w->getWrestlerId(), $worldIds));
        shuffle($usCandidates);
        $usPool = array_slice($usCandidates, 0, 32);

        // Fallback: if any pool has fewer than 32, fill from lower levels
        if (count($worldPool) < 32) {
            $lower = $repo->findBy(['lvl' => [5, 6, 7]]);
            shuffle($lower);
            $needed    = 32 - count($worldPool);
            $worldPool = array_merge($worldPool, array_slice($lower, 0, $needed));
        }
        if (count($usPool) < 32) {
            $lower = $repo->findBy(['lvl' => [5]]);
            shuffle($lower);
            $needed = 32 - count($usPool);
            $usPool = array_merge($usPool, array_slice($lower, 0, $needed));
        }

        return [
            'world_pool' => array_values($worldPool),
            'us_pool'    => array_values($usPool),
        ];
    }
}
