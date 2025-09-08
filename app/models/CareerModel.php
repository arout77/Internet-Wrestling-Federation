<?php

namespace App\Model;

use PDO;
use Src\Model\System_Model;

class CareerModel extends System_Model
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
    }

    /**
     * Fetches a prospect's data from the database by their user ID.
     * This now correctly uses PDO.
     * @param string $userId The ID of the user.
     * @return array|null The wrestler data array or null if not found.
     */
    public function getWrestlerByUserId( $userId )
    {
        $sql  = "SELECT p.* FROM prospects p JOIN users u ON p.pid = u.prospect_id WHERE u.user_id = :user_id";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':user_id' => $userId] );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Fetches a main roster wrestler's data by their ID.
     * @param int $wrestlerId The ID of the wrestler.
     * @return array|null The wrestler data array or null if not found.
     */
    public function getWrestlerById( $wrestlerId )
    {
        $sql  = "SELECT * FROM roster WHERE wrestler_id = :id";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':id' => $wrestlerId] );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Creates a new prospect for a user.
     * @param array $data An array containing the new prospect's details.
     * @return string|false The new prospect's ID on success, false on failure.
     */
    public function createProspect( $data )
    {
        $sql = "INSERT INTO prospects (pid, name, height, weight, image, baseHp, strength, technicalAbility, brawlingAbility, stamina, aerialAbility, toughness, reversalAbility, submissionDefense, staminaRecoveryRate, moves, lvl, attribute_points)
                VALUES (:pid, :name, :height, :weight, :image, :baseHp, :strength, :technicalAbility, :brawlingAbility, :stamina, :aerialAbility, :toughness, :reversalAbility, :submissionDefense, :staminaRecoveryRate, :moves, 1, 5)";
        $stmt = $this->db->prepare( $sql );

        $pid          = bin2hex( random_bytes( 16 ) );
        $defaultMoves = json_encode( [
            "strike"     => ["Punch", "Clothesline", "Knee Drop"],
            "grapple"    => ["Body Slam", "Suplex", "Inverted atomic drop", "Abdominal Stretch", "Hip Toss", "Arm Bar"],
            "finisher"   => ["Piledriver"],
            "highFlying" => ["Dropkick"],
        ] );

        $success = $stmt->execute( [
            ':pid'                 => $pid,
            ':name'                => $data['name'],
            ':height'              => $data['height'],
            ':weight'              => $data['weight'],
            ':image'               => $data['image'],
            ':baseHp'              => 1000,
            ':strength'            => 50,
            ':technicalAbility'    => 50,
            ':brawlingAbility'     => 50,
            ':stamina'             => 50,
            ':aerialAbility'       => 50,
            ':toughness'           => 50,
            ':reversalAbility'     => 50,
            ':submissionDefense'   => 50,
            ':staminaRecoveryRate' => 5,
            ':moves'               => $defaultMoves,
        ] );

        return $success ? $pid : false;
    }

    /**
     * Upgrades a prospect's attribute, using a free point if available, otherwise costing gold with a compounding formula.
     *
     * @param string $userId The ID of the user.
     * @param string $attribute The name of the attribute to upgrade.
     * @return bool|string True on success, or an error message string on failure.
     */
    public function purchaseAttributePoint( $userId, $attribute )
    {
        try {
            $sqlUser  = "SELECT prospect_id FROM users WHERE user_id = :user_id";
            $stmtUser = $this->db->prepare( $sqlUser );
            $stmtUser->execute( [':user_id' => $userId] );
            $user = $stmtUser->fetch( PDO::FETCH_ASSOC );

            if ( !$user || empty( $user['prospect_id'] ) )
            {
                return "Could not find a prospect for the current user.";
            }
            $prospectId = $user['prospect_id'];

            $validAttributes = ['strength', 'technicalAbility', 'brawlingAbility', 'stamina', 'aerialAbility', 'toughness'];
            if ( !in_array( $attribute, $validAttributes ) )
            {
                return "Invalid attribute specified.";
            }

            $sqlProspect  = "SELECT attribute_points, gold, {$attribute} FROM prospects WHERE pid = :pid";
            $stmtProspect = $this->db->prepare( $sqlProspect );
            $stmtProspect->execute( [':pid' => $prospectId] );
            $prospect = $stmtProspect->fetch( PDO::FETCH_ASSOC );

            if ( !$prospect )
            {
                return "Prospect data could not be found.";
            }

            if ( $prospect['attribute_points'] > 0 )
            {
                $sqlUpdate = "UPDATE prospects
                            SET {$attribute} = {$attribute} + 1,
                                attribute_points = attribute_points - 1
                            WHERE pid = :pid";
                $params = [':pid' => $prospectId];
            }
            else
            {
                $currentLevel = (int) $prospect[$attribute];
                if ( $currentLevel >= 100 )
                {
                    return "This attribute is already at its maximum level.";
                }

                $upgradeCost = ceil( 50 * pow( 1.1, $currentLevel - 50 ) );

                if ( $prospect['gold'] < $upgradeCost )
                {
                    return "You do not have enough gold ({$upgradeCost}) to upgrade this attribute.";
                }

                $sqlUpdate = "UPDATE prospects
                            SET {$attribute} = {$attribute} + 1,
                                gold = gold - :cost
                            WHERE pid = :pid";
                $params = [':pid' => $prospectId, ':cost' => $upgradeCost];
            }

            $stmtUpdate = $this->db->prepare( $sqlUpdate );
            $stmtUpdate->execute( $params );

            return $stmtUpdate->rowCount() > 0;

        }
        catch ( \PDOException $e )
        {
            return "A database error occurred during the upgrade process.";
        }
    }

    /**
     * Determines the XP required to reach the next level based on a tiered system.
     * @param int $currentLevel The prospect's current level.
     * @return int The total XP needed for the next level.
     */
    public function getXpForNextLevel( $currentLevel )
    {
        if ( $currentLevel >= 61 )
        {
            return 1000;
        }
        // Legend Tier
        if ( $currentLevel >= 31 )
        {
            return 500;
        }
        // Main Eventer Tier
        if ( $currentLevel >= 11 )
        {
            return 250;
        }
        // Mid-Carder Tier
        return 100; // Rookie Tier
    }

    /**
     * Finds a suitable opponent for the prospect from the main roster.
     * Opponent level must be less than or equal to the prospect's level.
     * @param int $prospectLevel The prospect's current level.
     * @return array A list of suitable opponent objects.
     */
    public function findOpponentForProspect( $prospectLevel )
    {
        $sql  = "SELECT * FROM roster WHERE lvl <= :level ORDER BY RAND()";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':level' => $prospectLevel] );
        return $stmt->fetchAll( PDO::FETCH_OBJ );
    }

    /**
     * Updates the prospect's record and stats after a match, including handling level-ups.
     * @param string $userId The ID of the user.
     * @param int $xp_earned The amount of XP earned from the match.
     * @param int $gold_earned The amount of Gold earned from the match.
     * @param bool $won True if the prospect won, false otherwise.
     * @return array Status of the update, including level-up and bonus info.
     */
    public function updateProspectAfterMatch( $userId, $xp_earned, $gold_earned, $won )
    {
        $this->db->beginTransaction();
        try {
            $prospect = $this->getWrestlerByUserId( $userId );
            if ( !$prospect )
            {
                $this->db->rollBack();
                return ['success' => false, 'leveled_up' => false, 'bonus_ap' => false];
            }

            $new_xp    = $prospect['current_xp'] + $xp_earned;
            $new_gold  = $prospect['gold'] + $gold_earned;
            $new_level = $prospect['lvl'];
            $new_ap    = $prospect['attribute_points'];

            $leveled_up       = false;
            $bonus_ap_awarded = false;

            $xp_needed = $this->getXpForNextLevel( $new_level );
            while ( $new_xp >= $xp_needed )
            {
                $new_level++;
                $new_xp -= $xp_needed;
                $new_ap += 5;
                $leveled_up = true;

                if ( rand( 1, 3 ) === 1 )
                {
                    $new_ap++;
                    $bonus_ap_awarded = true;
                }

                $xp_needed = $this->getXpForNextLevel( $new_level );
            }

            // **FIX START:** Update prospect stats and win/loss records in separate, correct queries.

            // Step 4: Update the prospect's main stats in the `prospects` table.
            $sqlProspect = "UPDATE prospects SET
                        current_xp = :xp,
                        gold = :gold,
                        lvl = :level,
                        attribute_points = :ap
                    WHERE pid = :pid";
            $stmtProspect = $this->db->prepare( $sqlProspect );
            $stmtProspect->execute( [
                ':xp'    => $new_xp,
                ':gold'  => $new_gold,
                ':level' => $new_level,
                ':ap'    => $new_ap,
                ':pid'   => $prospect['pid'],
            ] );

            // Step 5: Update the win/loss record in the `wrestler_records` table.
            $sqlRecord = "INSERT INTO wrestler_records (wrestler_id, wins, losses, draws)
                          VALUES (:pid, :wins, :losses, 0)
                          ON DUPLICATE KEY UPDATE
                          wins = wins + :wins_update,
                          losses = losses + :losses_update";
            $stmtRecord = $this->db->prepare( $sqlRecord );
            $stmtRecord->execute( [
                ':pid'           => $prospect['pid'],
                ':wins'          => ( $won ? 1 : 0 ),
                ':losses'        => ( $won ? 0 : 1 ),
                ':wins_update'   => ( $won ? 1 : 0 ),
                ':losses_update' => ( $won ? 0 : 1 ),
            ] );

            // **FIX END**

            $this->db->commit();
            return ['success' => true, 'leveled_up' => $leveled_up, 'bonus_ap' => $bonus_ap_awarded];

        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
            // Optionally log the error: error_log($e->getMessage());
            return ['success' => false, 'leveled_up' => false, 'bonus_ap' => false];
        }
    }

    /**
     * Records the outcome of a match in the matches table.
     * @param int $wrestler1_id The ID of the first wrestler (prospect).
     * @param int $wrestler2_id The ID of the second wrestler (opponent).
     * @param int $winner_id The ID of the winning wrestler.
     * @return bool True on success, false on failure.
     */
    public function recordMatchOutcome( $wrestler1_id, $wrestler2_id, $winner_id )
    {
        $sql  = "INSERT INTO matches (wrestler1_id, wrestler2_id, winner_id, match_date) VALUES (:w1, :w2, :winner, NOW())";
        $stmt = $this->db->prepare( $sql );
        return $stmt->execute( [
            ':w1'     => $wrestler1_id,
            ':w2'     => $wrestler2_id,
            ':winner' => $winner_id,
        ] );
    }

    /**
     * Fetches traits for a given prospect.
     * @param int $prospectId The integer ID of the prospect.
     * @return array An array of trait names.
     */
    public function getProspectTraits( $prospectId )
    {
        $sql = 'SELECT t.name FROM traits t
                JOIN prospect_traits pt ON t.trait_id = pt.trait_id
                WHERE pt.prospect_id = :prospect_id';
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':prospect_id' => $prospectId] );
        return $stmt->fetchAll( PDO::FETCH_COLUMN );
    }

    /**
     * Fetches a prospect's default moveset from the JSON blob in the database.
     * @param string $movesJson The JSON string of moves.
     * @return array An array of move objects.
     */
    public function getMovesByNames( $movesJson )
    {
        $moveNamesArray = json_decode( $movesJson, true );
        if ( json_last_error() !== JSON_ERROR_NONE || !is_array( $moveNamesArray ) )
        {
            return [];
        }

        $allMoveNames = array_merge( ...array_values( $moveNamesArray ) );
        if ( empty( $allMoveNames ) )
        {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $allMoveNames ), '?' ) );
        $sql          = "SELECT * FROM all_moves WHERE move_name IN ($placeholders)";
        $stmt         = $this->db->prepare( $sql );
        $stmt->execute( $allMoveNames );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * Fetches the moveset for a given prospect that they have learned.
     * @param string $prospectPid The PID of the prospect.
     * @return array An array of move objects.
     */
    public function getProspectLearnedMoves( $prospectPid )
    {
        $sql  = 'SELECT am.* FROM all_moves am JOIN prospect_moves pm ON am.move_id = pm.move_id WHERE pm.prospect_pid = :prospect_pid';
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':prospect_pid' => $prospectPid] );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * Calculates XP and Gold rewards for a match.
     * @param object $prospect The prospect object.
     * @param object $opponent The opponent object.
     * @param bool $isWin True if the prospect won.
     * @return array An array containing 'xp' and 'gold' rewards.
     */
    public function calculateRewards( $prospect, $opponent, $isWin )
    {
        $levelDifference = $opponent->lvl - $prospect->lvl;

        $baseXp   = $isWin ? 100 : 25;
        $baseGold = $isWin ? 500 : 100;

        // Bonus for fighting a tougher opponent
        $levelBonusFactor = max( 0, $levelDifference * 0.1 ); // 10% bonus per level higher
        $xp_earned        = $baseXp * ( 1 + $levelBonusFactor );
        $gold_earned      = $baseGold * ( 1 + $levelBonusFactor );

        // Apply manager bonuses if a manager is hired
        if ( !empty( $prospect->manager_id ) )
        {
            $managerModel = new ManagerModel( $this->app );
            $manager      = $managerModel->getManagerById( $prospect->manager_id );
            if ( $manager )
            {
                $xp_earned += $xp_earned * $manager['xp_bonus'];
                $gold_earned += $gold_earned * $manager['gold_bonus'];
            }
        }

        return [
            'xp'   => round( $xp_earned ),
            'gold' => round( $gold_earned ),
        ];
    }
}
