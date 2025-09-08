<?php
namespace App\Model;

use PDO;
use Src\Model\System_Model;

class TrainModel extends System_Model
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
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
    public function getAvailableMoves( $prospectPid, $prospectLevel, $filterType = 'all', $sortBy = 'level_requirement', $sortOrder = 'ASC' )
    {
        try {
            // The query below includes all move types (strike, grapple, submission, etc.) except for finishers.
            $sql = "SELECT * FROM all_moves
                    WHERE level_requirement <= :level
                    AND type != 'finisher'
                    AND move_id NOT IN (SELECT move_id FROM prospect_moves WHERE prospect_pid = :pid)";

            // Add filtering
            if ( $filterType !== 'all' )
            {
                $sql .= " AND type = :type";
            }

            // Add sorting
            $validSortColumns = ['cost', 'level_requirement', 'max_damage'];
            if ( in_array( $sortBy, $validSortColumns ) )
            {
                $sql .= " ORDER BY " . $sortBy . " " . ( $sortOrder === 'DESC' ? 'DESC' : 'ASC' );
            }
            else
            {
                $sql .= " ORDER BY level_requirement ASC"; // Default sort
            }

            $stmt   = $this->db->prepare( $sql );
            $params = [':level' => $prospectLevel, ':pid' => $prospectPid];
            if ( $filterType !== 'all' )
            {
                $params[':type'] = $filterType;
            }

            $stmt->execute( $params );
            return $stmt->fetchAll( PDO::FETCH_ASSOC );
        }
        catch ( \PDOException $e )
        {
            return [];
        }
    }

    /**
     * Allows a prospect to learn a new move.
     * @param string $prospectPid The PID of the prospect learning the move.
     * @param int $moveId The ID of the move to be learned.
     * @return bool|string True on success, or an error message string on failure.
     */
    public function learnMove( $prospectPid, $moveId )
    {
        $this->db->beginTransaction();
        try {
            // Fetch the move details to check cost, level, etc.
            $stmtMove = $this->db->prepare( "SELECT * FROM all_moves WHERE move_id = :id" );
            $stmtMove->execute( [':id' => $moveId] );
            $move = $stmtMove->fetch( PDO::FETCH_ASSOC );

            if ( !$move )
            {
                $this->db->rollBack();
                return "Move not found.";
            }

            // **FIX:** Fetch the latest prospect data from within the transaction for accuracy.
            $stmtProspectData = $this->db->prepare( "SELECT * FROM prospects WHERE pid = :pid" );
            $stmtProspectData->execute( [':pid' => $prospectPid] );
            $prospect = $stmtProspectData->fetch( PDO::FETCH_ASSOC );

            if ( !$prospect )
            {
                $this->db->rollBack();
                return "Prospect not found.";
            }

            // Now perform checks with the correct, full prospect data.
            if ( $prospect['gold'] < $move['cost'] )
            {
                $this->db->rollBack();
                return "Not enough gold.";
            }

            // Deduct cost and update prospect
            $newGold      = $prospect['gold'] - $move['cost'];
            $sqlProspect  = "UPDATE prospects SET gold = :gold WHERE pid = :pid";
            $stmtProspect = $this->db->prepare( $sqlProspect );
            $stmtProspect->execute( [':gold' => $newGold, ':pid' => $prospectPid] );

            // Add the move to the prospect's learned moves
            $sqlMove  = "INSERT INTO prospect_moves (prospect_pid, move_id) VALUES (:pid, :mid)";
            $stmtMove = $this->db->prepare( $sqlMove );
            $stmtMove->execute( [':pid' => $prospectPid, ':mid' => $moveId] );

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
