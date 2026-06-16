<?php
namespace App\Controllers;

use App\Entities\Prospect;
use App\Entities\ProspectNickname;
use App\Entities\User;
use Doctrine\ORM\EntityManager;
use Rhapsody\Core\BaseController;
use Rhapsody\Core\Request;
use Rhapsody\Core\Response;
use Rhapsody\Core\Session;
use Rhapsody\Core\Validator;
use Twig\Environment;

class ProspectController extends BaseController
{
    /**
     * Inject all the services we need into the controller
     */
    public function __construct(
        protected EntityManager $em,
        protected Validator $validator,
        Environment $twig
    ) {
        parent::__construct($twig);
    }

    /**
     * Show the form for creating a new prospect.
     */
    public function showCreateForm(Request $request): Response
    {
        // 1. Get the logged-in user
        $user = $this->em->find(User::class, Session::get('user_id'));

        // 2. Check if user already has a prospect
        if ($user->getProspect()) {
            return redirect('/dashboard')->with('error', 'You already have a prospect.');
        }

        // 3. Fetch nicknames from the database
        $nicknameRepo = $this->em->getRepository(ProspectNickname::class);
        $nicknames    = $nicknameRepo->findAll();

        // 4. Scan avatar directory for images
        $avatarPath = dirname(__DIR__, 2) . '/public/images/avatars';
        $allFiles   = scandir($avatarPath);
        $avatars    = array_filter($allFiles, function ($file) {
            return str_ends_with($file, '.png');
        });

        // 5. Render the view, passing it the nicknames and avatars
        return $this->view('prospect/create.twig', [
            'nicknames' => $nicknames,
            'avatars'   => $avatars,
            'old'       => [],
            'errors'    => [],
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
            return $this->json([
                'success' => false,
                'error'   => 'You already have a prospect.',
            ], 400);
        }

        // 3. Get form data
        $data = $request->getBody();

        // 4. Define validation rules
        $rules = [
            'name'   => 'required|min:3|max:50',
            'height' => 'required|regex:/^[4-7]\'\d{1,2}\"$/',
            'weight' => 'required|numeric|min:150|max:600',
            // 'nickname' => 'alpha_num', // <-- BUG: Removed this rule. It was failing.
            'avatar' => 'required',
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

                // Associate it with the user
                $prospect->setUser($user);
                $user->setProspect($prospect);

                $this->em->persist($prospect);
                $this->em->persist($user);
                $this->em->flush();

                // --- FIX: ALWAYS RETURN JSON ---
                Session::flash('success', 'Your prospect has been created!');
                return $this->json([
                    'success'  => true,
                    'redirect' => '/career',
                ]);
                // --- END FIX ---

            } catch (\Exception $e) {
                // Return JSON on database error
                return $this->json([
                    'success' => false,
                    'error'   => 'An error occurred: ' . $e->getMessage(),
                ], 500);
            }
        }

        // 6. Validation Failed
        // --- FIX: RETURN JSON, NOT A VIEW ---
        return $this->json([
            'success' => false,
            'errors'  => $this->validator->getErrors(),
        ], 422); // 422 Unprocessable Entity
    }

    /**
     * Handle the attribute upgrade request.
     */
    public function upgradeAttribute(Request $request, string $attribute): Response
    {
        // 1. Get User and Prospect
        $user = $this->em->find(User::class, Session::get('user_id'));
        if (! $user) {
            return $this->json(['error' => 'Not authenticated.'], 401);
        }
        $prospect = $user->getProspect();
        if (! $prospect) {
            return $this->json(['error' => 'Prospect not found.'], 404);
        }

        // 2. Determine which attribute is being upgraded and get its current level
        $currentLevel = 0;
        switch ($attribute) {
            case 'strength':
                $currentLevel = $prospect->getStrength();
                break;
            case 'technicalAbility':
                $currentLevel = $prospect->getTechnicalAbility();
                break;
            case 'brawlingAbility':
                $currentLevel = $prospect->getBrawlingAbility();
                break;
            case 'aerialAbility':
                $currentLevel = $prospect->getAerialAbility();
                break;
            case 'stamina':
                $currentLevel = $prospect->getStamina();
                break;
            case 'toughness':
                $currentLevel = $prospect->getToughness();
                break;
            default:
                return $this->json(['error' => 'Invalid attribute specified.'], 400);
        }

        if ($currentLevel >= 100) {
            return $this->json(['error' => 'Attribute is already at max level.'], 400);
        }

        // 3. Check for AP first
        if ($prospect->getAttributePoints() > 0) {
            $prospect->setAttributePoints($prospect->getAttributePoints() - 1);
        } else {
            // 4. No AP, check USER'S Gold.
            // This formula MUST match your JavaScript
            $goldCost = (int) ceil(50 * pow(1.1, $currentLevel - 50));

            // *** THIS IS THE FIX for spending gold you don't have ***
            // We check the USER's gold, not the prospect's
            if ($user->getGold() >= $goldCost) {
                $user->setGold($user->getGold() - $goldCost);
                $this->em->persist($user); // Persist the user since we changed their gold
            } else {
                return $this->json(['error' => "Not enough AP or Gold. Need 1 AP or {$goldCost} Gold."], 400);
            }
        }

        // 5. Apply the attribute point
        switch ($attribute) {
            case 'strength':
                $prospect->setStrength($currentLevel + 1);
                break;
            case 'technicalAbility':
                $prospect->setTechnicalAbility($currentLevel + 1);
                break;
            case 'stamina':
                $prospect->setStamina($currentLevel + 1);
                break;
            case 'brawlingAbility':
                $prospect->setBrawlingAbility($currentLevel + 1);
                break;
            case 'aerialAbility':
                $prospect->setAerialAbility($currentLevel + 1);
                break;
            case 'toughness':
                $prospect->setToughness($currentLevel + 1);
                break;
        }

        // 6. Save and flush
        $this->em->persist($prospect);
        $this->em->flush();

        // 7. *** THIS IS THE FIX for the UI not updating ***
        // Return the JSON in the exact format the JavaScript expects
        return $this->json([
            'success'  => true,
            'message'  => 'Attribute upgraded!',
            'prospect' => [
                'strength'         => $prospect->getStrength(),
                'technicalAbility' => $prospect->getTechnicalAbility(),
                'brawlingAbility'  => $prospect->getBrawlingAbility(),
                'aerialAbility'    => $prospect->getAerialAbility(),
                'stamina'          => $prospect->getStamina(),
                'toughness'        => $prospect->getToughness(),
                'gold'             => $user->getGold(),                // Return the USER's new gold
                'attributePoints'  => $prospect->getAttributePoints(), // Return the Prospect's new AP
            ],
        ]);
    }
}
