<?php
namespace App\Models;

use PDO;
use Rhapsody\Core\BaseModel;

class Train extends BaseModel
{
    public function getTrainingOpportunities(string $prospectPid): array
    {
        try {
            $sql = "SELECT am.*, to.expires_after_matches
                    FROM all_moves am
                    JOIN training_opportunities `to` ON am.move_id = to.move_id
                    WHERE to.prospect_pid = :pid";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':pid' => $prospectPid]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function getAvailableMoves(int $prospectLevel, string $filterType = 'all', string $sortBy = 'level_requirement', string $sortOrder = 'ASC'): array
    {
        $sql = "SELECT * FROM all_moves WHERE type != 'finisher' AND level_requirement <= :level";
        if ($filterType !== 'all') {
            $sql .= " AND TRIM(type) = :type";
        }
        $allowedSortColumns = ['level_requirement', 'cost', 'max_damage', 'move_name'];
        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'level_requirement';
        }
        $sortOrder  = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        $sql       .= " ORDER BY {$sortBy} {$sortOrder}";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':level', $prospectLevel, PDO::PARAM_INT);
        if ($filterType !== 'all') {
            $stmt->bindValue(':type', $filterType, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getKnownMoveIds(string $prospectPid): array
    {
        $stmt = $this->db->prepare("SELECT move_id FROM prospect_moves WHERE prospect_id = ?");
        $stmt->execute([$prospectPid]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function learnMove(string $prospectPid, int $moveId)
    {
        $this->db->beginTransaction();
        try {
            $stmtMove = $this->db->prepare("SELECT * FROM all_moves WHERE move_id = :id");
            $stmtMove->execute([':id' => $moveId]);
            $move = $stmtMove->fetch(PDO::FETCH_ASSOC);
            if (! $move) {
                $this->db->rollBack();
                return "Move not found.";
            }

            // Get user ID and gold from the user associated with this prospect
            $stmtUser = $this->db->prepare("
                SELECT u.user_id, u.gold
                FROM users u
                JOIN prospects p ON u.prospect_id = p.pid
                WHERE p.pid = :pid
            ");
            $stmtUser->execute([':pid' => $prospectPid]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (! $user) {
                $this->db->rollBack();
                return "User not found.";
            }

            if ($user['gold'] < $move['cost']) {
                $this->db->rollBack();
                return "Not enough gold.";
            }

            // Deduct gold from user
            $newGold    = $user['gold'] - $move['cost'];
            $stmtUpdate = $this->db->prepare("UPDATE users SET gold = :gold WHERE user_id = :uid");
            $stmtUpdate->execute([':gold' => $newGold, ':uid' => $user['user_id']]);

            // Add move to prospect_moves
            $sqlMove  = "INSERT INTO prospect_moves (prospect_id, move_id) VALUES (:pid, :mid)";
            $stmtMove = $this->db->prepare($sqlMove);
            $stmtMove->execute([':pid' => $prospectPid, ':mid' => $moveId]);

            // Remove from training opportunities
            $deleteStmt = $this->db->prepare("DELETE FROM training_opportunities WHERE prospect_pid = :pid AND move_id = :mid");
            $deleteStmt->execute([':pid' => $prospectPid, ':mid' => $moveId]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return "Database error: " . $e->getMessage();
        }
    }
}
