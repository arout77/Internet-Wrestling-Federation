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
            $this->redirect('app/career');
            exit;
        }

        $careerModel = $this->model('Career');
        $opponent = $careerModel->findOpponentForProspect($prospect['lvl']);

        if (!$opponent) {
            $this->redirect('app/career');
            exit;
        }

        $result = $this->simulate_match($prospect, $opponent);
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
        $prospect_power = $prospect['lvl'] * 10 + ($prospect['strength'] + $prospect['technicalAbility'] + $prospect['brawlingAbility']) / 3;
        $opponent_power = $opponent['lvl'] * 10 + ($opponent['strength'] + $opponent['technicalAbility'] + $opponent['brawlingAbility']) / 3;

        $winner = ($prospect_power >= $opponent_power) ? $prospect['name'] : $opponent['name'];

        $xp_earned = ($winner === $prospect['name']) ? 100 : 25;
        
        // Gold earnings now scale with the prospect's level
        $base_gold_win = 500;
        $base_gold_loss = 100;
        $gold_per_level_win = 50;
        $gold_per_level_loss = 10;

        if ($winner === $prospect['name']) {
            $gold_earned = $base_gold_win + ($prospect['lvl'] * $gold_per_level_win);
        } else {
            $gold_earned = $base_gold_loss + ($prospect['lvl'] * $gold_per_level_loss);
        }

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
            'prospect_id' => $prospect['pid'],
            'opponent_id' => $opponent['wrestler_id'],
            'xp_earned' => round($xp_earned),
            'gold_earned' => round($gold_earned)
        ];
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
        
        $this->template->render('career/match_result.html.twig', [
            'result' => $result
        ]);
    }

    /**
     * Completes the match, updating the prospect's stats and recording the outcome.
     */
    public function complete_match()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('user/login');
            exit;
        }
        
        $xp_earned = $_POST['xp_earned'] ?? 0;
        $gold_earned = $_POST['gold_earned'] ?? 0;
        $prospect_id = $_POST['prospect_id'] ?? null;
        $opponent_id = $_POST['opponent_id'] ?? null;
        $winner_name = $_POST['winner_name'] ?? null;

        $careerModel = $this->model('Career');
        
        if ($prospect_id && $opponent_id && $winner_name) {
            $careerModel->recordMatchOutcome($prospect_id, $opponent_id, $winner_name);
        }

        $careerModel->updateProspectAfterMatch($_SESSION['user_id'], $xp_earned, $gold_earned);
        
        unset($_SESSION['match_result']);
        $this->redirect('app/career');
    }

    /**
     * Handles the request to upgrade a prospect's attribute.
     */
    public function upgrade_attribute($attribute)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $careerModel = $this->model('Career');
        $result = $careerModel->spendAttributePoint($_SESSION['user_id'], $attribute);

        if (is_array($result)) {
            echo json_encode(['success' => true, 'prospect' => $result]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result]);
        }
    }
}
