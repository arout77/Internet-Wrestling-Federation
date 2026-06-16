<?php
namespace App\Controllers;

use App\Entities\Move;
use App\Entities\Roster;
use App\Entities\User;
use App\Entities\WrestlerTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\RedirectResponse;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Twig\Environment;

class AdminController extends BaseController
{
    public function __construct(protected EntityManager $em, Environment $twig)
    {
        parent::__construct($twig);
    }

    public function index()
    {
        // Ensure you are returning a response that the Router can handle
        return $this->view('admin/dashboard.twig');
    }

    public function listWrestlers()
    {
        // Fetches all wrestlers for the index page
        $wrestlers = $this->em->getRepository(Roster::class)->findAll();
        return $this->view('admin/wrestlers_list.twig', ['wrestlers' => $wrestlers]);
    }

    public function editWrestler(Request $request, $id): Response
    {
        $wrestler    = $this->em->find(Roster::class, $id);
        $allTagTeams = $this->em->getConnection()->fetchAllAssociative('SELECT team_name FROM tag_teams ORDER BY team_name');

        // Fetch ALL traits available in the system
        $allTraits = $this->em->getRepository(WrestlerTrait::class)->findAll();

        // Get the IDs of traits this wrestler ALREADY has
        $wrestlerTraitIds = $wrestler->getTraits()->map(fn($t) => $t->getTraitId())->toArray();

        $allMoves        = $this->em->getRepository(Move::class)->findBy([], ['move_name' => 'ASC']);
        $wrestlerMoveIds = $wrestler->getMoves()->map(fn($m) => $m->getMoveId())->toArray();

        return $this->view('admin/edit_wrestler.twig', [
            'wrestler'           => $wrestler,
            'all_traits'         => $allTraits,
            'wrestler_trait_ids' => $wrestlerTraitIds,
            'all_tag_teams'      => $allTagTeams,
            'all_moves'          => $allMoves,
            'wrestler_move_ids'  => $wrestlerMoveIds,
        ]);
    }

    public function updateWrestler(Request $request, $id): Response
    {
        $wrestler = $this->em->find(Roster::class, $id);
        if (! $wrestler) {
            return redirect('/admin/wrestlers')->with('error', 'Wrestler not found');
        }
        $tagTeam = $request->post('tag_team');

        // Basic info
        $wrestler->setName($request->post('name'));
        $wrestler->setArchetype($request->post('archetype'));
        $wrestler->setHeight($request->post('height'));
        $wrestler->setWeight($request->post('weight'));
        $wrestler->setDescription($request->post('description'));
        $wrestler->setLvl((int) $request->post('lvl', 1));
        $wrestler->setIsWorldChamp((bool) $request->post('is_world_champ', false));
        $wrestler->setTagTeam(empty($tagTeam) ? null : $tagTeam);
        $wrestler->setImage($request->post('image'));

        // Core stats
        $wrestler->setBaseHp((int) $request->post('baseHp'));
        $wrestler->setStrength((int) $request->post('strength'));
        $wrestler->setTechnicalAbility((int) $request->post('technicalAbility'));
        $wrestler->setBrawlingAbility((int) $request->post('brawlingAbility'));
        $wrestler->setStamina((int) $request->post('stamina'));
        $wrestler->setAerialAbility((int) $request->post('aerialAbility'));
        $wrestler->setToughness((int) $request->post('toughness'));
        $wrestler->setReversalAbility((int) $request->post('reversalAbility'));
        $wrestler->setSubmissionDefense((int) $request->post('submissionDefense'));
        $wrestler->setStaminaRecoveryRate((int) $request->post('staminaRecoveryRate'));

        // Tendencies
        $wrestler->setTendencyStrike((int) $request->post('tendency_strike', 50));
        $wrestler->setTendencyGrapple((int) $request->post('tendency_grapple', 50));
        $wrestler->setTendencySubmission((int) $request->post('tendency_submission', 50));
        $wrestler->setTendencyHighfly((int) $request->post('tendency_highfly', 50));

        // Traits (ManyToMany)
        $submittedTraitIds = array_map('intval', $request->post('traits', []));
        $traitsRepo        = $this->em->getRepository(WrestlerTrait::class);
        $newTraits         = new ArrayCollection();
        foreach ($submittedTraitIds as $traitId) {
            $trait = $traitsRepo->find($traitId);
            if ($trait) {
                $newTraits->add($trait);
            }
        }
        // Sync traits
        foreach ($wrestler->getTraits() as $existingTrait) {
            if (! $newTraits->contains($existingTrait)) {
                $wrestler->getTraits()->removeElement($existingTrait);
            }
        }
        foreach ($newTraits as $newTrait) {
            if (! $wrestler->getTraits()->contains($newTrait)) {
                $wrestler->getTraits()->add($newTrait);
            }
        }

        // Handle moves (ManyToMany)
        $submittedMoveIds = array_map('intval', $request->post('moves', []));
        $moveRepo         = $this->em->getRepository(Move::class);
        $newMoves         = new ArrayCollection();
        foreach ($submittedMoveIds as $moveId) {
            $move = $moveRepo->find($moveId);
            if ($move) {
                $newMoves->add($move);
            }
        }
        // Sync moves collection
        foreach ($wrestler->getMoves() as $existingMove) {
            if (! $newMoves->contains($existingMove)) {
                $wrestler->getMoves()->removeElement($existingMove);
            }
        }
        foreach ($newMoves as $newMove) {
            if (! $wrestler->getMoves()->contains($newMove)) {
                $wrestler->getMoves()->add($newMove);
            }
        }

        $this->em->flush();

        return redirect('/admin/wrestlers')->with('success', 'Wrestler updated!');
    }
}
