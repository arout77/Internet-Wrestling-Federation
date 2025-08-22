<?php
namespace App\Model;

use Src\Model\System_Model;
use PDO;

class TrainModel extends System_Model
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * Gets moves available for a prospect to learn, with filtering and sorting.
     * @param string $prospectPid
     * @param int $prospectLevel
     * @param string $filterType
     * @param string $sortBy
     * @param string $sortOrder
     * @return array
     */
    public function getAvailableMoves($prospectPid, $prospectLevel, $filterType = 'all', $sortBy = 'level_requirement', $sortOrder = 'ASC')
    {
        try {
            $sql = "SELECT * FROM all_moves 
                    WHERE level_requirement <= :level 
                    AND type != 'finisher'
                    AND move_id NOT IN (SELECT move_id FROM prospect_moves WHERE prospect_pid = :pid)";

            // Add filtering
            if ($filterType !== 'all') {
                $sql .= " AND type = :type";
            }

            // Add sorting
            $validSortColumns = ['cost', 'level_requirement', 'max_damage'];
            if (in_array($sortBy, $validSortColumns)) {
                $sql .= " ORDER BY " . $sortBy . " " . ($sortOrder === 'DESC' ? 'DESC' : 'ASC');
            } else {
                $sql .= " ORDER BY level_requirement ASC"; // Default sort
            }

            $stmt = $this->db->prepare($sql);
            $params = [':level' => $prospectLevel, ':pid' => $prospectPid];
            if ($filterType !== 'all') {
                $params[':type'] = $filterType;
            }
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Allows a prospect to learn a new move.
     * @param string $prospectPid
     * @param int $moveId
     * @return bool|string
     */
    public function learnMove($prospectPid, $moveId)
    {
        $this->db->beginTransaction();
        try {
            $stmtMove = $this->db->prepare("SELECT * FROM all_moves WHERE move_id = :id");
            $stmtMove->execute([':id' => $moveId]);
            $move = $stmtMove->fetch(PDO::FETCH_ASSOC);

            if (!$move) return "Move not found.";

            $userModel = $this->model('User');
            $prospect = $userModel->getProspectByPid($prospectPid);

            if ($prospect['gold'] < $move['cost']) return "Not enough gold.";

            $newGold = $prospect['gold'] - $move['cost'];
            $sqlProspect = "UPDATE prospects SET gold = :gold WHERE pid = :pid";
            $stmtProspect = $this->db->prepare($sqlProspect);
            $stmtProspect->execute([':gold' => $newGold, ':pid' => $prospectPid]);

            $sqlMove = "INSERT INTO prospect_moves (prospect_pid, move_id) VALUES (:pid, :mid)";
            $stmtMove = $this->db->prepare($sqlMove);
            $stmtMove->execute([':pid' => $prospectPid, ':mid' => $moveId]);
            
            $this->db->commit();
            return true;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            return "Database error: " . $e->getMessage();
        }
    }
}
