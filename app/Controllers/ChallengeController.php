<?php
namespace App\Controllers;

use App\Entities\Challenge;
use App\Entities\Manager;
use App\Entities\Notification;
use App\Entities\Prospect;
use App\Entities\User;
use App\Services\SimulationService;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Twig\Environment;

class ChallengeController extends BaseController
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly SimulationService $simulationService,
        Environment $twig
    ) {
        parent::__construct($twig);
    }

    // ==================== PAGE RENDERING ====================

    public function manage(): Response
    {
        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user?->getProspect()) {
            return redirect('/career')->with('error', 'You need a prospect to manage challenges.');
        }

        $myProspect = $user->getProspect();

        // Incoming challenges (my prospect is defender)
        $incomingChallenges = $this->em->createQueryBuilder()
            ->select('c', 'challenger')
            ->from(Challenge::class, 'c')
            ->join('c.challenger', 'challenger')
            ->where('c.defender = :defender')
            ->andWhere('c.status = :status')
            ->setParameter('defender', $myProspect)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();

        $incoming = array_map(fn(Challenge $c) => [
            'id'               => $c->getId(),
            'challenger_pid'   => $c->getChallenger()->getPid(),
            'challenger_name'  => $c->getChallenger()->getName(),
            'challenger_level' => $c->getChallenger()->getLvl(),
            'wager_amount'     => $c->getWagerAmount(),
        ], $incomingChallenges);

        // Outgoing challenges (my prospect is challenger)
        $outgoingChallenges = $this->em->createQueryBuilder()
            ->select('c', 'defender')
            ->from(Challenge::class, 'c')
            ->join('c.defender', 'defender')
            ->where('c.challenger = :challenger')
            ->andWhere('c.status = :status')
            ->setParameter('challenger', $myProspect)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();

        $outgoing = array_map(fn(Challenge $c) => [
            'id'             => $c->getId(),
            'defender_pid'   => $c->getDefender()->getPid(),
            'defender_name'  => $c->getDefender()->getName(),
            'defender_level' => $c->getDefender()->getLvl(),
            'wager_amount'   => $c->getWagerAmount(),
        ], $outgoingChallenges);

        $myProspectData = [
            'pid'              => $myProspect->getPid(),
            'name'             => $myProspect->getName(),
            'lvl'              => $myProspect->getLvl(),
            'strength'         => $myProspect->getStrength(),
            'technicalAbility' => $myProspect->getTechnicalAbility(),
            'brawlingAbility'  => $myProspect->getBrawlingAbility(),
            'stamina'          => $myProspect->getStamina(),
            'aerialAbility'    => $myProspect->getAerialAbility(),
            'toughness'        => $myProspect->getToughness(),
            'image'            => $myProspect->getImage(),
        ];

        return $this->view('challenge/manage.html.twig', [
            'myProspect'          => $myProspectData,
            'incoming_challenges' => $incoming,
            'outgoing_challenges' => $outgoing,
            'meta'                => ['title' => 'Manage Challenges | IWF'],
        ]);
    }

    public function create(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user               = $this->em->find(User::class, Session::get('user_id'));
        $challengerProspect = $user->getProspect();

        if (! $challengerProspect) {
            return $this->json(['error' => 'You must have a prospect to send a challenge.'], 400);
        }

        $data        = $request->getBody();
        $defenderPid = $data['defender_pid'] ?? null;
        $wager       = (int) ($data['wager_amount'] ?? 0);

        if (! $defenderPid) {
            return $this->json(['error' => 'No defender specified.'], 400);
        }

        if ($wager < 1) {
            return $this->json(['error' => 'Minimum wager is 1 Gold.'], 400);
        }

        $defenderProspect = $this->em->getRepository(Prospect::class)->findOneBy(['pid' => $defenderPid]);
        if (! $defenderProspect) {
            return $this->json(['error' => 'Defender prospect not found.'], 404);
        }

        // Check challenger's USER gold
        if ($user->getGold() < $wager) {
            return $this->json(['error' => 'You do not have enough Gold to send this challenge.'], 400);
        }

        $defenderUser = $defenderProspect->getUser();
        if (! $defenderUser) {
            return $this->json(['error' => 'Could not find the owner of that prospect.'], 500);
        }

        try {
            $challenge = new Challenge();
            $challenge->setChallenger($challengerProspect);
            $challenge->setDefender($defenderProspect);
            $challenge->setWagerAmount($wager);
            $this->em->persist($challenge);

            $notification = new Notification();
            $notification->setUser($defenderUser);
            $notification->setMessage("{$challengerProspect->getName()} has challenged you to a match for {$wager} Gold!");
            $notification->setLink('/challenge/manage');
            $this->em->persist($notification);

            $this->em->flush();

            return $this->json(['success' => true, 'message' => 'Challenge sent!'], 201);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function accept(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data        = $request->getBody();
        $challengeId = $data['challenge_id'] ?? null;
        if (! $challengeId) {
            return $this->json(['error' => 'Missing challenge ID'], 400);
        }

        $challenge = $this->em->find(Challenge::class, $challengeId);
        if (! $challenge || $challenge->getStatus() !== 'pending') {
            return $this->json(['error' => 'Challenge not found or already resolved'], 404);
        }

        $user       = $this->em->find(User::class, Session::get('user_id'));
        $myProspect = $user->getProspect();
        if (! $myProspect || $challenge->getDefender()->getPid() !== $myProspect->getPid()) {
            return $this->json(['error' => 'You are not the defender of this challenge'], 403);
        }

        $opponent = $challenge->getChallenger();
        $wager    = $challenge->getWagerAmount();

        // Check defender's USER gold
        if ($user->getGold() < $wager) {
            return $this->json(['error' => 'You do not have enough Gold to accept this challenge.'], 400);
        }

        // Run the simulation
        $log      = $this->simulationService->runVisualMatch($myProspect, $opponent, true);
        $endEvent = end($log);
        $winnerId = $endEvent['data']['winner_id'] ?? null;

        $winner     = null;
        $loser      = null;
        $winnerUser = null;
        $loserUser  = null;

        if ($winnerId === 'w1') {
            $winner     = $myProspect;
            $winnerUser = $user;
            $loser      = $opponent;
            $loserUser  = $opponent->getUser();
        } elseif ($winnerId === 'w2') {
            $winner     = $opponent;
            $winnerUser = $opponent->getUser();
            $loser      = $myProspect;
            $loserUser  = $user;
        }

        if ($winnerUser && $loserUser) {
            $winnerUser->setGold($winnerUser->getGold() + $wager);
            $loserUser->setGold(max(0, $loserUser->getGold() - $wager));
            $this->em->persist($winnerUser);
            $this->em->persist($loserUser);
        }

        $challenge->setStatus('completed');
        $challenge->setWinner($winner);
        $challenge->setResolvedAt(new \DateTime());
        $this->em->persist($challenge);
        $this->em->flush();

        // Notify both users
        $this->sendChallengeNotification($challenge->getChallenger()->getUser(), "Your challenge against {$myProspect->getName()} has been completed. " . ($winner ? "You " . ($winner === $opponent ? "won" : "lost") . " {$wager} Gold." : "It was a draw."));
        $this->sendChallengeNotification($user, "Your match against {$opponent->getName()} is complete. " . ($winner ? "You " . ($winner === $myProspect ? "won" : "lost") . " {$wager} Gold." : "It was a draw."));

        return $this->json([
            'success' => true,
            'result'  => [
                'winner' => $winner ? ['name' => $winner->getName()] : null,
                'loser'  => $loser ? ['name' => $loser->getName()] : null,
                'wager'  => $wager,
            ],
        ]);
    }

    public function decline(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data        = $request->getBody();
        $challengeId = $data['challenge_id'] ?? null;
        if (! $challengeId) {
            return $this->json(['error' => 'Missing challenge ID'], 400);
        }

        $challenge = $this->em->find(Challenge::class, $challengeId);
        if (! $challenge || $challenge->getStatus() !== 'pending') {
            return $this->json(['error' => 'Challenge not found or already resolved'], 404);
        }

        $user       = $this->em->find(User::class, Session::get('user_id'));
        $myProspect = $user->getProspect();
        if (! $myProspect || $challenge->getDefender()->getPid() !== $myProspect->getPid()) {
            return $this->json(['error' => 'You are not the defender of this challenge'], 403);
        }

        $challenge->setStatus('declined');
        $challenge->setResolvedAt(new \DateTime());
        $this->em->persist($challenge);
        $this->em->flush();

        $this->sendChallengeNotification($challenge->getChallenger()->getUser(), "Your challenge against {$myProspect->getName()} was declined.");

        return $this->json(['success' => true, 'message' => 'Challenge declined']);
    }

    // ==================== AJAX ENDPOINTS ====================

    public function ajaxGetChallengeDetails(Request $request, string $pid): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $opponent = $this->em->getRepository(Prospect::class)->findOneBy(['pid' => $pid]);
        if (! $opponent) {
            return $this->json(['error' => 'Prospect not found'], 404);
        }

        $user       = $this->em->find(User::class, Session::get('user_id'));
        $myProspect = $user->getProspect();
        if (! $myProspect) {
            return $this->json(['error' => 'You need a prospect to view challenge details'], 400);
        }

        $oddsData = $this->simulationService->generateOdds($myProspect, $opponent, 1000);

        $opponentData = [
            'pid'              => $opponent->getPid(),
            'name'             => $opponent->getName(),
            'lvl'              => $opponent->getLvl(),
            'height'           => $opponent->getHeight(),
            'weight'           => $opponent->getWeight(),
            'strength'         => $opponent->getStrength(),
            'technicalAbility' => $opponent->getTechnicalAbility(),
            'brawlingAbility'  => $opponent->getBrawlingAbility(),
            'stamina'          => $opponent->getStamina(),
            'aerialAbility'    => $opponent->getAerialAbility(),
            'toughness'        => $opponent->getToughness(),
            'image'            => $opponent->getImage(),
            'traits'           => $opponent->getTraits()->map(fn($t) => ['name' => $t->getName()])->toArray(),
            'manager_name'     => $opponent->getManagerId()
                ? $this->em->find(Manager::class, $opponent->getManagerId())?->getName()
                : null,
        ];

        return $this->json([
            'success'           => true,
            'opponent_prospect' => $opponentData,
            'odds'              => [
                'probabilities' => [
                    $myProspect->getName() => $oddsData['w1_win_percent'] / 100,
                    $opponent->getName()   => $oddsData['w2_win_percent'] / 100,
                ],
                'moneyline'     => [
                    $myProspect->getName() => $oddsData['w1_odds'],
                    $opponent->getName()   => $oddsData['w2_odds'],
                ],
            ],
        ]);
    }

    // ==================== PRIVATE HELPERS ====================

    private function sendChallengeNotification(User $user, string $message): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setLink('/challenge/manage');
        $this->em->persist($notification);
        $this->em->flush();
    }
}
