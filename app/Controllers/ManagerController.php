<?php
namespace App\Controllers;

use App\Entities\Manager;
use App\Entities\Prospect;
use App\Entities\User;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Twig\Environment;

class ManagerController extends BaseController
{
    protected EntityManager $em;

    public function __construct(Environment $twig, EntityManager $em)
    {
        parent::__construct($twig);
        $this->em = $em;
    }

    /**
     * Show the list of available managers.
     */
    public function index(): Response
    {
        $userId = Session::get('user_id');
        if (! $userId) {
            return redirect('/login');
        }

        $user = $this->em->find(User::class, $userId);
        if (! $user || ! $user->getProspect()) {
            return redirect('/career')->with('error', 'You need a prospect to hire a manager.');
        }

        $prospect = $user->getProspect();
        $managers = $this->em->getRepository(Manager::class)->findAll();

        return $this->view('managers/hire.html.twig', [
            'prospect'       => $prospect,
            'user'           => $user,
            'managers'       => $managers,
            'currentManager' => $prospect->getManagerId() ? $this->em->find(Manager::class, $prospect->getManagerId()) : null,
        ]);
    }

    /**
     * Purchase a manager.
     */
    public function purchase(Request $request, int $managerId): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user || ! $user->getProspect()) {
            return $this->json(['error' => 'You need a prospect to hire a manager.'], 400);
        }

        $prospect = $user->getProspect();
        $manager  = $this->em->find(Manager::class, $managerId);
        if (! $manager) {
            return $this->json(['error' => 'Manager not found.'], 404);
        }

        if ($prospect->getLvl() < $manager->getLevelRequirement()) {
            return $this->json(['error' => 'Your prospect does not meet the level requirement for this manager.'], 400);
        }

        $cost = $manager->getCost();
        if ($user->getGold() < $cost) {
            return $this->json(['error' => "You need {$cost} Gold to hire this manager."], 400);
        }

        // If the prospect already has a manager, remove its bonuses first
        if ($prospect->getManagerId()) {
            $oldManager = $this->em->find(Manager::class, $prospect->getManagerId());
            if ($oldManager) {
                $this->removeManagerBonuses($prospect, $oldManager);
            }
        }

        // Apply new manager bonuses
        $this->applyManagerBonuses($prospect, $manager);

        // Deduct gold and set manager
        $user->setGold($user->getGold() - $cost);
        $prospect->setManagerId($manager->getId());

        $this->em->persist($user);
        $this->em->persist($prospect);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => "You have hired {$manager->getName()}!",
            'newGold' => $user->getGold(),
        ]);
    }

    /**
     * Fire the current manager.
     */
    public function fire(Request $request): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user || ! $user->getProspect()) {
            return $this->json(['error' => 'No prospect found.'], 400);
        }

        $prospect  = $user->getProspect();
        $managerId = $prospect->getManagerId();
        if (! $managerId) {
            return $this->json(['error' => 'You do not have a manager to fire.'], 400);
        }

        $manager = $this->em->find(Manager::class, $managerId);
        if ($manager) {
            $this->removeManagerBonuses($prospect, $manager);
        }

        $prospect->setManagerId(null);
        $this->em->persist($prospect);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => "You have fired {$manager->getName()}. Their bonuses have been removed.",
        ]);
    }

    /**
     * Apply manager attribute bonuses to the prospect.
     */
    private function applyManagerBonuses(Prospect $prospect, Manager $manager): void
    {
        $prospect->setStrength($prospect->getStrength() + $manager->getStrengthBonus());
        $prospect->setBrawlingAbility($prospect->getBrawlingAbility() + $manager->getBrawlingAbilityBonus());
        $prospect->setTechnicalAbility($prospect->getTechnicalAbility() + $manager->getTechnicalAbilityBonus());
        $prospect->setAerialAbility($prospect->getAerialAbility() + $manager->getAerialAbilityBonus());
        $prospect->setStamina($prospect->getStamina() + $manager->getStaminaBonus());
        $prospect->setToughness($prospect->getToughness() + $manager->getToughnessBonus());
    }

    /**
     * Remove manager attribute bonuses from the prospect.
     */
    private function removeManagerBonuses(Prospect $prospect, Manager $manager): void
    {
        $prospect->setStrength($prospect->getStrength() - $manager->getStrengthBonus());
        $prospect->setBrawlingAbility($prospect->getBrawlingAbility() - $manager->getBrawlingAbilityBonus());
        $prospect->setTechnicalAbility($prospect->getTechnicalAbility() - $manager->getTechnicalAbilityBonus());
        $prospect->setAerialAbility($prospect->getAerialAbility() - $manager->getAerialAbilityBonus());
        $prospect->setStamina($prospect->getStamina() - $manager->getStaminaBonus());
        $prospect->setToughness($prospect->getToughness() - $manager->getToughnessBonus());
    }
}
