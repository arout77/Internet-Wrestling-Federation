<?php
namespace App\Model;

use Src\Model\System_Model;
use PDO;

class CareerModel extends System_Model
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * Finds a suitable opponent for the prospect based on their level.
     * @param int $prospectLevel
     * @return array|false
     */
    public function findOpponentForProspect($prospectLevel)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM roster WHERE lvl >= :level ORDER BY RAND() LIMIT 1");
            $stmt->execute([':level' => $prospectLevel]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Updates the prospect's XP, gold, and potentially level after a match.
     * @param string $userId
     * @param int $xp_earned
     * @param int $gold_earned
     */
    public function updateProspectAfterMatch($userId, $xp_earned, $gold_earned)
    {
        try {
            $userModel = $this->model('User');
            $prospect = $userModel->getProspectByUserId($userId);

            if ($prospect) {
                $new_xp = $prospect['current_xp'] + $xp_earned;
                $new_gold = $prospect['gold'] + $gold_earned;
                $new_level = $prospect['lvl'];
                $new_ap = $prospect['attribute_points'];

                $xp_for_next_level = floor(100 * pow(1.1, $new_level - 1));
                
                while ($new_xp >= $xp_for_next_level) {
                    $new_level++;
                    $new_xp -= $xp_for_next_level;
                    $new_ap += 3; // Award 3 attribute points per level
                    $xp_for_next_level = floor(100 * pow(1.1, $new_level - 1));
                }

                $sql = "UPDATE prospects SET current_xp = :xp, gold = :gold, lvl = :level, attribute_points = :ap WHERE pid = :pid";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':xp' => $new_xp,
                    ':gold' => $new_gold,
                    ':level' => $new_level,
                    ':ap' => $new_ap,
                    ':pid' => $prospect['pid']
                ]);
            }
        } catch (\PDOException $e) {
            // Handle error
        }
    }

    /**
     * Records the outcome of a match in the database.
     * @param string $prospectId (pid)
     * @param int $opponentId (wrestler_id)
     * @param string $winnerName
     */
    public function recordMatchOutcome($prospectId, $opponentId, $winnerName)
    {
        try {
            $userModel = $this->model('User');
            $prospect = $userModel->getProspectByPid($prospectId);

            if (!$prospect) return;

            $isProspectWinner = ($winnerName === $prospect['name']);
            $winnerId = $isProspectWinner ? $prospectId : $opponentId;
            $loserId = $isProspectWinner ? $opponentId : $prospectId;

            $sqlMatch = "INSERT INTO matches (match_type, player1_id, player2_id, single_winner_id, single_loser_id) VALUES ('single', :p1, :p2, :winner, :loser)";
            $stmtMatch = $this->db->prepare($sqlMatch);
            $stmtMatch->execute([':p1' => $prospectId, ':p2' => $opponentId, ':winner' => $winnerId, ':loser' => $loserId]);

            $sqlWin = "INSERT INTO wrestler_records (wrestler_id, wins) VALUES (:id, 1) ON DUPLICATE KEY UPDATE wins = wins + 1";
            $stmtWin = $this->db->prepare($sqlWin);
            $stmtWin->execute([':id' => $winnerId]);

            $sqlLoss = "INSERT INTO wrestler_records (wrestler_id, losses) VALUES (:id, 1) ON DUPLICATE KEY UPDATE losses = losses + 1";
            $stmtLoss = $this->db->prepare($sqlLoss);
            $stmtLoss->execute([':id' => $loserId]);

        } catch (\PDOException $e) {
            // Handle error
        }
    }

    /**
     * Spends an attribute point for a prospect.
     * @param string $userId
     * @param string $attribute
     * @return array|string
     */
    public function spendAttributePoint($userId, $attribute)
    {
        $this->db->beginTransaction();
        try {
            $userModel = $this->model('User');
            $prospect = $userModel->getProspectByUserId($userId);

            if (!$prospect) {
                return "Prospect not found.";
            }

            if ($prospect['attribute_points'] <= 0) {
                return "No attribute points available.";
            }

            // Whitelist of valid attributes to prevent SQL injection
            $valid_attributes = ['strength', 'technicalAbility', 'brawlingAbility', 'stamina', 'aerialAbility', 'toughness'];
            if (!in_array($attribute, $valid_attributes)) {
                return "Invalid attribute.";
            }

            $sql = "UPDATE prospects SET attribute_points = attribute_points - 1, `$attribute` = `$attribute` + 1 WHERE pid = :pid";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':pid' => $prospect['pid']]);

            $this->db->commit();
            return $userModel->getProspectByUserId($userId);

        } catch (\PDOException $e) {
            $this->db->rollBack();
            return "Database error: " . $e->getMessage();
        }
    }
}
