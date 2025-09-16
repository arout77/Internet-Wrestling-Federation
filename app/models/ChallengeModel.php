<?php
namespace App\Model;

use PDO;
use Src\Model\System_Model;

class ChallengeModel extends System_Model
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
    }

    /**
     * Fetches all dummy prospects for the simulation script.
     * @return array
     */
    public function getAllDummyProspects()
    {
        $sql  = "SELECT p.* FROM prospects p JOIN users u ON p.pid = u.prospect_id WHERE u.name LIKE 'IWF Wrestler%'";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute();
        $prospects = $stmt->fetchAll( PDO::FETCH_ASSOC );

        // Fetch traits for each prospect
        foreach ( $prospects as &$prospect )
        {
            $prospect['traits'] = $this->getProspectTraits( $prospect['id'] );
        }

        return $prospects;
    }

    /**
     * @param $currentProspectPid
     * @return mixed
     */
    public function getAllOtherProspects( $currentProspectPid )
    {
        $sql = "SELECT p.*, r.wins, r.losses
                FROM prospects p
                LEFT JOIN wrestler_records r ON p.pid = r.wrestler_id
                WHERE p.pid != :current_pid";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':current_pid' => $currentProspectPid] );
        $prospects = $stmt->fetchAll( PDO::FETCH_ASSOC );

        // Get streak status for all fetched prospects
        $pids    = array_column( $prospects, 'pid' );
        $streaks = $this->getStreakStatusForProspects( $pids );

        // Add streak status to each prospect
        foreach ( $prospects as &$prospect )
        {
            $prospect['streak'] = $streaks[$prospect['pid']] ?? 'neutral';
        }

        return $prospects;
    }

    /**
     * Gets the hot/cold streak status for a list of prospects.
     * @param array $prospectPids
     * @return array
     */
    private function getStreakStatusForProspects( array $prospectPids )
    {
        if ( empty( $prospectPids ) )
        {
            return [];
        }

        $streaks = [];
        foreach ( $prospectPids as $pid )
        {
            try {
                $stmt = $this->db->prepare(
                    "SELECT single_winner_id FROM matches
                     WHERE (player1_id = :pid OR player2_id = :pid)
                     ORDER BY match_date DESC
                     LIMIT 5"
                );
                $stmt->execute( [':pid' => $pid] );
                $results = $stmt->fetchAll( PDO::FETCH_COLUMN );

                if ( count( $results ) < 5 )
                {
                    $streaks[$pid] = 'neutral';
                    continue;
                }

                $wins = 0;
                foreach ( $results as $winner_id )
                {
                    if ( $winner_id === $pid )
                    {
                        $wins++;
                    }
                }
                $losses = 5 - $wins;

                if ( $wins >= 4 )
                {
                    $streaks[$pid] = 'hot';
                }
                elseif ( $losses >= 4 )
                {
                    $streaks[$pid] = 'cold';
                }
                else
                {
                    $streaks[$pid] = 'neutral';
                }
            }
            catch ( \PDOException $e )
            {
                // If there's an error, default to neutral for that prospect
                $streaks[$pid] = 'neutral';
            }
        }
        return $streaks;
    }

    /**
     * @param $pid
     * @return mixed
     */
    public function getProspectDetailsByPid( $pid )
    {
        $sql = "SELECT p.*, m.name as manager_name
                FROM prospects p
                LEFT JOIN managers m ON p.manager_id = m.id
                WHERE p.pid = :pid";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':pid' => $pid] );
        $prospect = $stmt->fetch( PDO::FETCH_ASSOC );

        if ( $prospect )
        {
            $prospect['traits'] = $this->getProspectTraits( $prospect['id'] );
        }

        return $prospect;
    }

    /**
     * @param $myPid
     * @param $opponentPids
     * @return mixed
     */
    public function getChallengeStatusesForProspect( $myPid, $opponentPids )
    {
        if ( empty( $opponentPids ) )
        {
            return [];
        }
        $placeholders = implode( ',', array_fill( 0, count( $opponentPids ), '?' ) );

        $sql = "SELECT challenger_pid, defender_pid, status FROM challenges
                WHERE (challenger_pid = ? AND defender_pid IN ($placeholders))
                   OR (defender_pid = ? AND challenger_pid IN ($placeholders))
                AND status = 'pending'";

        $params = array_merge( [$myPid], $opponentPids, [$myPid], $opponentPids );
        $stmt   = $this->db->prepare( $sql );
        $stmt->execute( $params );
        $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $statuses = [];
        foreach ( $results as $row )
        {
            if ( $row['challenger_pid'] === $myPid )
            {
                $statuses[$row['defender_pid']] = 'sent'; // I sent them a challenge
            }
            else
            {
                $statuses[$row['challenger_pid']] = 'received'; // They sent me a challenge
            }
        }
        return $statuses;
    }

    /**
     * @param $challengerPid
     * @param $defenderPid
     * @param $wager
     */
    public function createChallenge( $challengerPid, $defenderPid, $wager )
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare( "SELECT gold FROM prospects WHERE pid = :pid" );
            $stmt->execute( [':pid' => $challengerPid] );
            $challengerGold = $stmt->fetchColumn();

            if ( $challengerGold < $wager )
            {
                $this->db->rollBack();
                return "Not enough gold.";
            }

            $sql  = "INSERT INTO challenges (challenger_pid, defender_pid, wager_amount, status) VALUES (:challenger, :defender, :wager, 'pending')";
            $stmt = $this->db->prepare( $sql );
            $stmt->execute( [
                ':challenger' => $challengerPid,
                ':defender'   => $defenderPid,
                ':wager'      => $wager,
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

    /**
     * @param $prospectPid
     * @return mixed
     */
    public function getIncomingChallenges( $prospectPid )
    {
        $sql = "SELECT c.*, p.name as challenger_name, p.lvl as challenger_level
                FROM challenges c
                JOIN prospects p ON c.challenger_pid = p.pid
                WHERE c.defender_pid = :pid AND c.status = 'pending'";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':pid' => $prospectPid] );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * @param $prospectPid
     * @return mixed
     */
    public function getOutgoingChallenges( $prospectPid )
    {
        $sql = "SELECT c.*, p.name as defender_name, p.lvl as defender_level
                FROM challenges c
                JOIN prospects p ON c.defender_pid = p.pid
                WHERE c.challenger_pid = :pid AND c.status = 'pending'";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':pid' => $prospectPid] );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * @param $challengeId
     * @return mixed
     */
    public function getChallengeById( $challengeId )
    {
        $stmt = $this->db->prepare( "SELECT * FROM challenges WHERE id = :id" );
        $stmt->execute( [':id' => $challengeId] );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * @param $prospectId
     * @return mixed
     */
    public function getProspectTraits( $prospectId )
    {
        $sql = 'SELECT t.* FROM traits t
                JOIN prospect_traits pt ON t.trait_id = pt.trait_id
                WHERE pt.prospect_id = :prospect_id';
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':prospect_id' => $prospectId] );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * @param $challengeId
     * @param $winnerPid
     */
    public function resolveChallenge( $challengeId, $winnerPid )
    {
        $this->db->beginTransaction();
        try {
            $challenge = $this->getChallengeById( $challengeId );
            if ( !$challenge )
            {
                throw new \Exception( "Challenge not found." );
            }

            $wager    = $challenge['wager_amount'];
            $loserPid = ( $winnerPid === $challenge['challenger_pid'] ) ? $challenge['defender_pid'] : $challenge['challenger_pid'];

            // Update winner's gold
            $stmt = $this->db->prepare( "UPDATE prospects SET gold = gold + :wager WHERE pid = :pid" );
            $stmt->execute( [':wager' => $wager, ':pid' => $winnerPid] );

            // Update loser's gold
            $stmt = $this->db->prepare( "UPDATE prospects SET gold = gold - :wager WHERE pid = :pid" );
            $stmt->execute( [':wager' => $wager, ':pid' => $loserPid] );

            // Update challenge status
            $this->updateChallengeStatus( $challengeId, 'completed', $winnerPid );

            $this->db->commit();
            return true;
        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * @param $challengeId
     * @param $status
     * @param $winnerPid
     * @return mixed
     */
    public function updateChallengeStatus( $challengeId, $status, $winnerPid = null )
    {
        $sql  = "UPDATE challenges SET status = :status, winner_pid = :winner, resolved_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [
            ':status' => $status,
            ':winner' => $winnerPid,
            ':id'     => $challengeId,
        ] );
        return $stmt->rowCount() > 0;
    }

    /**
     * @param $prospectPid
     * @return mixed
     */
    public function getUnreadChallengeCount( $prospectPid )
    {
        $sql  = "SELECT COUNT(id) FROM challenges WHERE defender_pid = :pid AND status = 'pending' AND is_read = 0";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':pid' => $prospectPid] );
        return $stmt->fetchColumn();
    }

    /**
     * @param $prospectPid
     * @return mixed
     */
    public function markChallengesAsRead( $prospectPid )
    {
        $sql  = "UPDATE challenges SET is_read = 1 WHERE defender_pid = :pid AND status = 'pending'";
        $stmt = $this->db->prepare( $sql );
        return $stmt->execute( [':pid' => $prospectPid] );
    }
}
