<?php
namespace App\Model;

use Src\Model\System_Model;
use PDO;

class ManagerModel extends System_Model
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * Fetches all managers from the database.
     * @return array
     */
    public function getAllManagers()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM managers ORDER BY cost DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // In a real application, you would log this error
            return [];
        }
    }

    /**
     * Hires a manager for a prospect.
     * @param string $prospectId
     * @param int $managerId
     * @return bool|string
     */
    public function hireManagerForProspect($prospectId, $managerId)
    {
        $this->db->beginTransaction();
        try {
            // Get manager details
            $stmtManager = $this->db->prepare("SELECT * FROM managers WHERE id = :id");
            $stmtManager->execute([':id' => $managerId]);
            $manager = $stmtManager->fetch(PDO::FETCH_ASSOC);

            if (!$manager) {
                return "Manager not found.";
            }

            // Get prospect details
            $stmtProspect = $this->db->prepare("SELECT * FROM prospects WHERE pid = :pid");
            $stmtProspect->execute([':pid' => $prospectId]);
            $prospect = $stmtProspect->fetch(PDO::FETCH_ASSOC);

            if (!$prospect) {
                return "Prospect not found.";
            }

            // Check if prospect can afford the manager
            if ($prospect['gold'] < $manager['cost']) {
                return "Not enough gold to hire this manager.";
            }

            // Deduct cost and update manager_id
            $newGold = $prospect['gold'] - $manager['cost'];
            $sql = "UPDATE prospects SET manager_id = :manager_id, gold = :gold WHERE pid = :pid";
            $stmtUpdate = $this->db->prepare($sql);
            $stmtUpdate->execute([
                ':manager_id' => $managerId,
                ':gold' => $newGold,
                ':pid' => $prospectId
            ]);

            $this->db->commit();
            return true;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            return "Database error: " . $e->getMessage();
        }
    }
}
