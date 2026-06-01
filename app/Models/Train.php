<?php

namespace App\Models;

use Core\BaseModel;
use Core\Session;
use PDO;

class Train extends BaseModel
{
    /**
     * Gets special, limited-time training opportunities for a prospect.
     * @param string $prospectPid
     * @return array
     */
    public function getTrainingOpportunities()
    {
        $prospectId = Session::get( 'prospect_id' );

        try {
            $sql = "SELECT am.*, to.expires_after_matches
                    FROM all_moves am
                    JOIN training_opportunities `to` ON am.move_id = to.move_id
                    WHERE to.prospect_pid = :pid";
            $stmt = $this->db->prepare( $sql );
            $stmt->execute( [':pid' => $prospectId] );
            return $stmt->fetchAll( PDO::FETCH_ASSOC );
        } catch ( \PDOException $e ) {
            return [];
        }
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
        $sql = "SELECT * FROM all_moves WHERE type != 'finisher' AND level_requirement <= :level";

        if ( $filterType !== 'all' ) {
            // Use TRIM to handle potential whitespace issues in the database data
            $sql .= " AND TRIM(type) = :type";
        }

        // Validate sortBy to prevent SQL injection
        $allowedSortColumns = ['level_requirement', 'cost', 'max_damage', 'move_name'];
        if ( !in_array( $sortBy, $allowedSortColumns ) ) {
            $sortBy = 'level_requirement';
        }

        // Validate sortOrder
        $sortOrder = strtoupper( $sortOrder ) === 'DESC' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY {$sortBy} {$sortOrder}";

        $stmt = $this->db->prepare( $sql );
        $stmt->bindValue( ':level', $prospectLevel, PDO::PARAM_INT );

        if ( $filterType !== 'all' ) {
            $stmt->bindValue( ':type', $filterType, PDO::PARAM_STR );
        }

        $stmt->execute();
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * Gets an array of move IDs that a prospect already knows.
     * @param int $prospectId The ID of the prospect.
     * @return array
     */
    public function getKnownMoveIds()
    {
        $prospectId = Session::get( 'prospect_id' );
        $sql        = "SELECT move_id FROM prospect_moves WHERE prospect_pid = :prospect_id";
        $stmt       = $this->db->prepare( $sql );
        $stmt->execute( [':prospect_id' => $prospectId] );
        return $stmt->fetchAll( PDO::FETCH_COLUMN );
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

            if ( !$move ) {
                $this->db->rollBack();
                return "Move not found.";
            }

            // **FIX:** Fetch the latest prospect data from within the transaction for accuracy.
            $stmtProspectData = $this->db->prepare( "SELECT * FROM prospects WHERE pid = :pid" );
            $stmtProspectData->execute( [':pid' => $prospectPid] );
            $prospect = $stmtProspectData->fetch( PDO::FETCH_ASSOC );

            if ( !$prospect ) {
                $this->db->rollBack();
                return "Prospect not found.";
            }

            // Now perform checks with the correct, full prospect data.
            if ( $prospect['gold'] < $move['cost'] ) {
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

            // If this was a special opportunity, remove it
            $deleteStmt = $this->db->prepare( "DELETE FROM training_opportunities WHERE prospect_pid = :pid AND move_id = :mid" );
            $deleteStmt->execute( [':pid' => $prospectPid, ':mid' => $moveId] );

            $this->db->commit();
            return true;

        } catch ( \PDOException $e ) {
            $this->db->rollBack();
            return "Database error: " . $e->getMessage();
        }
    }
}
