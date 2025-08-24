<?php
namespace App\Controller;

use Src\Controller\Base_Controller;

class Career_Controller extends Base_Controller
{
    /**
     * Finds a suitable opponent for the player's prospect and initiates a match simulation.
     */
    public function find_match()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('user/login');
            exit;
        }

        $userModel = $this->model('User');
        $prospect = $userModel->getProspectByUserId($_SESSION['user_id']);

        if (!$prospect) {
            // Handle case where prospect doesn't exist
            $this->redirect('app/career');
            exit;
        }

        $careerModel = $this->model('Career');
        $opponent = $careerModel->findOpponentForProspect($prospect['lvl']);

        if (!$opponent) {
            // Handle case where no suitable opponent is found
            // For now, redirect back to career page with a message (future enhancement)
            $this->redirect('app/career');
            exit;
        }

        // Simulate the match
        $result = $this->simulate_match($prospect, $opponent);

        // Store result in session to be displayed on the result page
        $_SESSION['match_result'] = $result;

        $this->redirect('career/match_result');
    }

    /**
     * Simulates a match between the prospect and an opponent.
     * @param array $prospect
     * @param array $opponent
     * @return array
     */
    private function simulate_match($prospect, $opponent)
    {
        // This is a simplified simulation logic. In a real scenario,
        // you would use your detailed turn-by-turn simulation engine.
        $prospect_power = $prospect['lvl'] * 10 + ($prospect['strength'] + $prospect['technicalAbility'] + $prospect['brawlingAbility']) / 3;
        $opponent_power = $opponent['lvl'] * 10 + ($opponent['strength'] + $opponent['technicalAbility'] + $opponent['brawlingAbility']) / 3;

        $winner = ($prospect_power >= $opponent_power) ? $prospect['name'] : $opponent['name'];

        $xp_earned = ($winner === $prospect['name']) ? 100 : 25;
        $gold_earned = ($winner === $prospect['name']) ? 500 : 100;

        // Apply manager bonuses if a manager is hired
        if ($prospect['manager_id']) {
            $managerModel = $this->model('Manager');
            $manager = $managerModel->getManagerById($prospect['manager_id']);
            if ($manager) {
                $xp_earned += $xp_earned * $manager['xp_bonus'];
                $gold_earned += $gold_earned * $manager['gold_bonus'];
            }
        }

        return [
            'winner' => $winner,
            'prospect_name' => $prospect['name'],
            'opponent_name' => $opponent['name'],
            'xp_earned' => round($xp_earned),
            'gold_earned' => round($gold_earned)
        ];
    }

    public function train()
    {
        $careerModel = $this->model('Career');
        $wrestler = $careerModel->getWrestlerByUserId($_SESSION['user_id']);

        if ($wrestler) {
            // Pass the wrestler data to the view here as well.
            $data = [
                'wrestler' => $wrestler
            ];
            $this->view('career/train', $data);
        } else {
            // If a user without a wrestler lands here, redirect them to the creation page.
            header('location:' . $this->config->setting('site_url') . '/managers/hire');
        }
    }

    public function upgrade_attribute($attribute)
    {
        // Set the content type to JSON for the response
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
            exit;
        }
        
        // Load the Career model
        $careerModel = $this->model('Career');
        
        // Attempt to purchase the attribute point upgrade
        $result = $careerModel->purchaseAttributePoint($_SESSION['user_id'], $attribute);

        if ($result === true) {
            // If successful, fetch the updated prospect data to send back
            $userModel = $this->model('User');
            $updatedProspect = $userModel->getProspectByUserId($_SESSION['user_id']);

            http_response_code(200); // OK
            echo json_encode([
                'success' => true,
                'message' => 'Attribute upgraded successfully!',
                'prospect' => $updatedProspect // Send the updated data back to the frontend
            ]);
        } else {
            // If there was an error, send the error message from the model
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'error' => $result]);
        }
        
        exit;
    }

    /**
     * Displays the result of the last match.
     */
    public function match_result()
    {
        if (!isset($_SESSION['match_result'])) {
            $this->redirect('app/career');
            exit;
        }

        $result = $_SESSION['match_result'];
        unset($_SESSION['match_result']); // Clear the result from session

        $this->template->render('career/match_result.html.twig', [
            'result' => $result
        ]);
    }

    /**
     * Completes the match, updating the prospect's stats.
     */
    public function complete_match()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('user/login');
            exit;
        }
        
        $xp_earned = $_POST['xp_earned'] ?? 0;
        $gold_earned = $_POST['gold_earned'] ?? 0;

        $careerModel = $this->model('Career');
        $careerModel->updateProspectAfterMatch($_SESSION['user_id'], $xp_earned, $gold_earned);

        $this->redirect('app/career');
    }
}
