<?php
namespace App\Model;

use PDO;
use Src\Model\System_Model;

class ManagerModel extends System_Model
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
    }

    /**
     * Fetches all managers from the database.
     * @return array
     */
    public function getAllManagers()
    {
        try {
            $stmt = $this->db->prepare( "SELECT * FROM managers ORDER BY level_requirement ASC, cost DESC" );
            $stmt->execute();
            return $stmt->fetchAll( PDO::FETCH_ASSOC );
        }
        catch ( \PDOException $e )
        {
            return [];
        }
    }

    /**
     * Fetches a single manager by their ID.
     * @param int $managerId
     * @return array|false
     */
    public function getManagerById( $managerId )
    {
        try {
            $stmt = $this->db->prepare( "SELECT * FROM managers WHERE id = :id" );
            $stmt->execute( [':id' => $managerId] );
            return $stmt->fetch( PDO::FETCH_ASSOC );
        }
        catch ( \PDOException $e )
        {
            return false;
        }
    }

    /**
     * Hires a manager for a prospect.
     * @param array $prospect The full prospect data array.
     * @param int $managerId
     * @return bool|string
     */
    public function hireManagerForProspect( $prospect, $managerId )
    {
        $this->db->beginTransaction();
        try {
            $manager = $this->getManagerById( $managerId );
            if ( !$manager )
            {
                return "Manager not found.";
            }

            // The prospect data is now passed directly, no need to load another model.
            if ( !$prospect )
            {
                return "Prospect not found.";
            }

            if ( $prospect['lvl'] < $manager['level_requirement'] )
            {
                return "Your level is too low to hire this manager.";
            }

            if ( $prospect['gold'] < $manager['cost'] )
            {
                return "Not enough gold to hire this manager.";
            }

            $newGold    = $prospect['gold'] - $manager['cost'];
            $sql        = "UPDATE prospects SET manager_id = :manager_id, gold = :gold WHERE pid = :pid";
            $stmtUpdate = $this->db->prepare( $sql );
            $stmtUpdate->execute( [
                ':manager_id' => $managerId,
                ':gold'       => $newGold,
                ':pid'        => $prospect['pid'],
            ] );

            $this->db->commit();
            return true;

        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
            return "Database error: " . $e->getMessage();
        }
    }
}
