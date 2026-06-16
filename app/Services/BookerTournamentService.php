<?php
namespace App\Services;

use App\Entities\BookerEvent;
use App\Entities\BookerUserState;
use App\Entities\Championship;
use App\Entities\Roster;
use App\Entities\User;
use Doctrine\ORM\EntityManager;
use Exception;

/**
 * BookerTournamentService handles the simulation of the initial tournaments
 * that crown the first World and US champions for a new promotion.
 */
class BookerTournamentService
{
    public function __construct(
        protected EntityManager $em,
        protected SimulationService $simulationService
    ) {}

    /**
     * Run the initial tournaments using pre‑built wrestler pools.
     * This is used after the user has viewed the bracket preview.
     *
     * @param User $user
     * @param BookerUserState $state
     * @param BookerEvent $event     Already persisted tournament event (has venue set)
     * @param array $worldPool       Array of Roster entities for World tournament
     * @param array $usPool          Array of Roster entities for US tournament
     * @return array ['world_champion' => Roster, 'us_champion' => Roster]
     */
    public function runInitialTournamentsWithPools(User $user, BookerUserState $state, BookerEvent $event, array $worldPool, array $usPool): array
    {
        $worldChampion = $this->runSingleTournament($worldPool, $event->getId(), 'World');
        $usChampion    = $this->runSingleTournament($usPool, $event->getId(), 'US');

        $state->setWorldChampionId($worldChampion->getWrestlerId());
        $state->setUsChampionId($usChampion->getWrestlerId());
        $state->setHasInitialTournamentsRun(true);
        $this->em->persist($state);
        $this->em->flush();

        return [
            'world_champion' => $worldChampion,
            'us_champion'    => $usChampion,
        ];
    }

    /**
     * Legacy method: builds its own pools and runs tournaments.
     * Retained for compatibility but not used in the current flow.
     *
     * @param User $user
     * @param BookerUserState $state
     * @param \App\Entities\Venue $venue
     * @return array
     */
    public function runInitialTournaments(User $user, BookerUserState $state, \App\Entities\Venue $venue): array
    {
        // Build pools based on level counts (old method)
        $worldPool = $this->buildTournamentPool(12, 11, 9, 0);
        $usPool    = $this->buildTournamentPool(0, 8, 12, 12);

        $worldIds = array_map(fn($w) => $w->getWrestlerId(), $worldPool);
        $usPool   = array_filter($usPool, fn($w) => ! in_array($w->getWrestlerId(), $worldIds));

        shuffle($worldPool);
        shuffle($usPool);
        $worldPool = array_slice($worldPool, 0, 32);
        $usPool    = array_slice($usPool, 0, 32);

        // Create the event and persist it
        $tournamentEvent = new BookerEvent();
        $tournamentEvent->setUserId($user->getUserId());
        $tournamentEvent->setVenue($venue);
        $tournamentEvent->setEventName('Initial Championship Tournaments');
        $tournamentEvent->setEventDate(new \DateTime());
        $tournamentEvent->setAdvertisingSpend(0);
        $tournamentEvent->setAttendance(0);
        $tournamentEvent->setTotalRevenue(0);
        $tournamentEvent->setTotalCost(0);
        $tournamentEvent->setProfit(0);
        $tournamentEvent->setIsRetired(false);

        $this->em->persist($tournamentEvent);
        $this->em->flush();

        // Run tournaments
        $worldChampion = $this->runSingleTournament($worldPool, $tournamentEvent->getId(), 'World');
        $usChampion    = $this->runSingleTournament($usPool, $tournamentEvent->getId(), 'US');

        $state->setWorldChampionId($worldChampion->getWrestlerId());
        $state->setUsChampionId($usChampion->getWrestlerId());
        $state->setHasInitialTournamentsRun(true);
        $this->em->persist($state);
        $this->em->flush();

        return [
            'world_champion' => $worldChampion,
            'us_champion'    => $usChampion,
        ];
    }

    /**
     * Build a tournament pool by selecting specific numbers of wrestlers from given levels.
     * Used only by the legacy method – not needed for the current bracket preview flow.
     *
     * @param int $level10
     * @param int $level9
     * @param int $level8
     * @param int $level7
     * @return Roster[]
     */
    private function buildTournamentPool(int $level10, int $level9, int $level8, int $level7): array
    {
        $repo = $this->em->getRepository(Roster::class);
        $pool = [];

        $addByLevel = function (int $level, int $count) use (&$pool, $repo) {
            if ($count <= 0) {
                return;
            }

            $wrestlers = $repo->findBy(['lvl' => $level]);
            shuffle($wrestlers);
            $pool = array_merge($pool, array_slice($wrestlers, 0, $count));
        };

        $addByLevel(10, $level10);
        $addByLevel(9, $level9);
        $addByLevel(8, $level8);
        $addByLevel(7, $level7);

        return $pool;
    }

    /**
     * Simulate a single‑elimination tournament with a given array of wrestlers.
     * Records all matches in the unified `matches` table and returns the winner.
     *
     * @param Roster[] $wrestlers
     * @param int $eventId The ID of the BookerEvent this tournament belongs to
     * @param string $titleName 'World' or 'US'
     * @return Roster The champion
     */
    private function runSingleTournament(array $wrestlers, int $eventId, string $titleName): Roster
    {
        $round = 1;
        while (count($wrestlers) > 1) {
            $nextRound = [];
            for ($i = 0; $i < count($wrestlers); $i += 2) {
                $w1 = $wrestlers[$i];
                $w2 = $wrestlers[$i + 1] ?? null;
                if (! $w2) {
                    // Bye – automatically advance
                    $nextRound[] = $w1;
                    continue;
                }

                // Simulate the match (silent, no detailed log needed)
                $log       = $this->simulationService->runVisualMatch($w1, $w2, false);
                $endEvent  = end($log);
                $winnerKey = $endEvent['data']['winner_id'] ?? null;
                $winner    = ($winnerKey === 'w1') ? $w1 : (($winnerKey === 'w2') ? $w2 : null);
                if (! $winner) {
                    // Draw – pick a random winner
                    $winner = rand(0, 1) ? $w1 : $w2;
                }
                $nextRound[] = $winner;

                // Record the match in the database
                $this->recordMatch($eventId, $w1, $w2, $winner, $round, null);
            }
            $wrestlers = $nextRound;
            $round++;
        }

        $champion = $wrestlers[0];

        // Record the championship reign
        $championship = new Championship();
        $championship->setTitleName($titleName);
        $championship->setWrestlerId((string) $champion->getWrestlerId());
        $championship->setEventId($eventId);
        $championship->setReignNumber(1);
        $championship->setDateWon(new \DateTime());
        $this->em->persist($championship);
        $this->em->flush();

        return $champion;
    }

    /**
     * Insert a match record into the unified `matches` table.
     * Used for tournament matches (no detailed log, no star rating).
     *
     * @param int $eventId
     * @param Roster $w1
     * @param Roster $w2
     * @param Roster $winner
     * @param int $round
     * @param int|null $titleId
     */
    private function recordMatch(int $eventId, Roster $w1, Roster $w2, Roster $winner, int $round, ?int $titleId = null): void
    {
        $conn = $this->em->getConnection();
        $conn->insert('matches', [
            'match_type'       => 'single',
            'wrestler1_id'     => (string) $w1->getWrestlerId(),
            'wrestler2_id'     => (string) $w2->getWrestlerId(),
            'winner_id'        => (string) $winner->getWrestlerId(),
            'is_draw'          => 0,
            'source'           => 'tournament',
            'source_id'        => null,
            'event_id'         => $eventId,
            'stipulation'      => null,
            'title_id'         => $titleId,
            'duration_seconds' => rand(300, 1200),
            'log'              => null, // no detailed log for tournament matches
            'star_rating'      => null,
            'match_date'       => date('Y-m-d H:i:s'),
        ]);
    }
}
