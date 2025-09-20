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
     * Sets the archetype for a prospect and applies the initial attribute bonuses.
     * @param string $userId The ID of the user.
     * @param string $archetype The chosen archetype.
     * @return bool|string True on success, error message on failure.
     */
    public function setProspectArchetype( $userId, $archetype )
    {
        $this->db->beginTransaction();
        try {
            $prospect = $this->getWrestlerByUserId( $userId );

            if ( !$prospect )
            {
                $this->db->rollBack();
                return "Prospect not found.";
            }

            if ( $prospect['lvl'] < 5 )
            {
                $this->db->rollBack();
                return "You must be at least level 5 to choose an archetype.";
            }

            if ( !empty( $prospect['archetype'] ) )
            {
                $this->db->rollBack();
                return "An archetype has already been chosen for this prospect.";
            }

            $validArchetypes = ['brawler', 'technician', 'high-flyer', 'powerhouse'];
            if ( !in_array( $archetype, $validArchetypes ) )
            {
                $this->db->rollBack();
                return "Invalid archetype selected.";
            }

            $bonusQueryPart = "";
            switch ( $archetype )
            {
                case 'brawler':
                    $bonusQueryPart = "brawlingAbility = brawlingAbility + 5, toughness = toughness + 5";
                    break;
                case 'technician':
                    $bonusQueryPart = "technicalAbility = technicalAbility + 5, submissionDefense = submissionDefense + 5";
                    break;
                case 'high-flyer':
                    $bonusQueryPart = "aerialAbility = aerialAbility + 5, stamina = stamina + 5";
                    break;
                case 'powerhouse':
                    $bonusQueryPart = "strength = strength + 5, baseHp = baseHp + 50";
                    break;
            }

            $sql  = "UPDATE prospects SET archetype = :archetype, {$bonusQueryPart} WHERE pid = :pid";
            $stmt = $this->db->prepare( $sql );
            $stmt->execute( [':archetype' => $archetype, ':pid' => $prospect['pid']] );

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
     * Updates the moveset for a given prospect.
     * @param string $prospectPid The PID of the prospect.
     * @param string $movesetJson The new moveset as a JSON string.
     * @return bool True on success, false on failure.
     */
    public function updateProspectMoveset( $prospectPid, $movesetJson )
    {
        try {
            $sql  = "UPDATE prospects SET moves = :moves WHERE pid = :pid";
            $stmt = $this->db->prepare( $sql );
            $stmt->execute( [':moves' => $movesetJson, ':pid' => $prospectPid] );
            return $stmt->rowCount() > 0;
        }
        catch ( \PDOException $e )
        {
            // In a real app, you'd log this error
            return false;
        }
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
        $this->db->beginTransaction();
        try {
            $sqlUser  = "SELECT prospect_id FROM users WHERE user_id = :user_id";
            $stmtUser = $this->db->prepare( $sqlUser );
            $stmtUser->execute( [':user_id' => $userId] );
            $user = $stmtUser->fetch( PDO::FETCH_ASSOC );

            if ( !$user || empty( $user['prospect_id'] ) )
            {
                return "Could not find a prospect for the current user.";
            }
            $prospectPid = $user['prospect_id'];

            // Get prospect details by PID to ensure we have the internal ID
            $prospectData = $this->getProspectByPid( $prospectPid );
            if ( !$prospectData )
            {
                return "Prospect data could not be found.";
            }
            $prospectId = $prospectData['id'];

            $validAttributes = ['strength', 'technicalAbility', 'brawlingAbility', 'stamina', 'aerialAbility', 'toughness'];
            if ( !in_array( $attribute, $validAttributes ) )
            {
                return "Invalid attribute specified.";
            }

            $sqlProspect  = "SELECT attribute_points, gold, {$attribute} FROM prospects WHERE pid = :pid";
            $stmtProspect = $this->db->prepare( $sqlProspect );
            $stmtProspect->execute( [':pid' => $prospectPid] );
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
                $params = [':pid' => $prospectPid];
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
                $params = [':pid' => $prospectPid, ':cost' => $upgradeCost];
            }

            $stmtUpdate = $this->db->prepare( $sqlUpdate );
            $stmtUpdate->execute( $params );

            // After upgrading, sync traits
            $this->syncProspectTraits( $prospectId );

            $this->db->commit();
            return true;

        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
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
        if ( $currentLevel >= 31 )
        {
            return 500;
        }
        if ( $currentLevel >= 11 )
        {
            return 250;
        }
        return 100;
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
                return ['success' => false, 'leveled_up' => false, 'bonus_ap' => 0, 'leveled_up_rewards' => []];
            }

            $new_xp    = $prospect['current_xp'] + $xp_earned;
            $new_gold  = $prospect['gold'] + $gold_earned;
            $new_level = $prospect['lvl'];
            $new_ap    = $prospect['attribute_points'];

            $leveled_up         = false;
            $bonus_ap_awarded   = 0;
            $leveled_up_rewards = [];

            $xp_needed = $this->getXpForNextLevel( $new_level );
            while ( $new_xp >= $xp_needed )
            {
                $new_level++;
                $new_xp -= $xp_needed;
                $leveled_up = true;

                $level_up_reward = $this->getRewardsForLevel( $new_level );
                $new_ap += $level_up_reward['ap'];
                $new_gold += $level_up_reward['gold'];

                $leveled_up_rewards[] = $level_up_reward;

                if ( rand( 1, 3 ) === 1 )
                {
                    $new_ap++;
                    $bonus_ap_awarded++;
                }

                $xp_needed = $this->getXpForNextLevel( $new_level );
            }

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

            // Sync traits after potential level up and stat changes from AP might happen
            $this->syncProspectTraits( $prospect['id'] );

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

            $this->db->commit();
            return ['success' => true, 'leveled_up' => $leveled_up, 'bonus_ap' => $bonus_ap_awarded, 'leveled_up_rewards' => $leveled_up_rewards];

        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
            return ['success' => false, 'leveled_up' => false, 'bonus_ap' => 0, 'leveled_up_rewards' => []];
        }
    }

    /**
     * Records a win for one prospect and a loss for another.
     * @param string $winnerPid
     * @param string $loserPid
     * @return bool
     */
    public function recordWinLoss( $winnerPid, $loserPid )
    {
        try {
            // Record win for the winner
            $sqlWin = "INSERT INTO wrestler_records (wrestler_id, wins, losses) VALUES (:pid, 1, 0)
                       ON DUPLICATE KEY UPDATE wins = wins + 1";
            $stmtWin = $this->db->prepare( $sqlWin );
            $stmtWin->execute( [':pid' => $winnerPid] );

            // Record loss for the loser
            $sqlLoss = "INSERT INTO wrestler_records (wrestler_id, wins, losses) VALUES (:pid, 0, 1)
                        ON DUPLICATE KEY UPDATE losses = losses + 1";
            $stmtLoss = $this->db->prepare( $sqlLoss );
            $stmtLoss->execute( [':pid' => $loserPid] );

            return true;
        }
        catch ( \PDOException $e )
        {
            // In a real app, you'd log this error
            return false;
        }
    }

    /**
     * Determines the AP and Gold rewards for leveling up to a specific level.
     * @param int $newLevel The level the prospect has just reached.
     * @return array An array containing 'ap' and 'gold' rewards.
     */
    private function getRewardsForLevel( $newLevel )
    {
        $ap   = 0;
        $gold = 0;

        if ( $newLevel <= 10 )
        { // Rookie
            $ap   = 1;
            $gold = 1000;
        }
        elseif ( $newLevel <= 30 )
        { // Mid-Carder
            $ap   = 1;
            $gold = 1500;
        }
        elseif ( $newLevel <= 60 )
        { // Main Eventer
            $ap   = 2;
            $gold = 2000;
        }
        else
        { // Legend
            $ap   = 3;
            $gold = 3000;
        }
        return ['ap' => $ap, 'gold' => $gold];
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
        $sql  = "INSERT INTO matches (player1_id, player2_id, single_winner_id, match_date) VALUES (:w1, :w2, :winner, NOW())";
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
        $sql = 'SELECT t.* FROM traits t
                JOIN prospect_traits pt ON t.trait_id = pt.trait_id
                WHERE pt.prospect_id = :prospect_id';
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':prospect_id' => $prospectId] );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
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

        $baseXp   = $isWin ? rand( 15, 25 ) : rand( 5, 10 );
        $baseGold = $isWin ? rand( 25, 50 ) : rand( 5, 15 );

        // Bonus for fighting a tougher opponent
        $levelBonusFactor = max( 0, $levelDifference * 0.1 ); // 10% bonus per level higher
        $xp_earned        = $baseXp * ( 1 + $levelBonusFactor );
        $gold_earned      = $baseGold * ( 1 + $levelBonusFactor );

        // Apply Brawler Archetype bonus
        if ( $prospect->archetype === 'brawler' && $isWin )
        {
            $xp_earned *= 1.10; // 10% XP bonus for winning
        }

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

    /**
     * @param $pid
     * @return mixed
     */
    public function getProspectByPid( $pid )
    {
        $stmt = $this->db->prepare( "SELECT * FROM prospects WHERE pid = :pid" );
        $stmt->execute( [':pid' => $pid] );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Checks a prospect's stats and updates their traits accordingly.
     * @param int $prospectId The internal integer ID of the prospect.
     */
    public function syncProspectTraits( $prospectId )
    {
        $stmt = $this->db->prepare( "SELECT * FROM prospects WHERE id = :id" );
        $stmt->execute( [':id' => $prospectId] );
        $prospect = $stmt->fetch( PDO::FETCH_ASSOC );

        if ( !$prospect )
        {
            return;
        }

        $qualifiedTraitIds = [];

        // Define trait qualifications based on stats
        if ( $prospect['aerialAbility'] >= 85 )
        {
            $qualifiedTraitIds[] = 2;
        }
        // High-Flyer
        if ( $this->isGiant( $prospect['height'], $prospect['weight'] ) )
        {
            $qualifiedTraitIds[] = 3;
        }
        // Giant
        if ( $prospect['toughness'] >= 90 && $prospect['weight'] >= 350 )
        {
            $qualifiedTraitIds[] = 4;
        }
        // Brick Wall
        if ( $prospect['technicalAbility'] >= 95 )
        {
            $qualifiedTraitIds[] = 5;
        }
        // Submission Specialist
        if ( $prospect['brawlingAbility'] >= 95 )
        {
            $qualifiedTraitIds[] = 6;
        }
        // Brawler
        if ( $prospect['strength'] >= 95 )
        {
            $qualifiedTraitIds[] = 7;
        }
        // Powerhouse
        if ( $prospect['stamina'] >= 90 )
        {
            $qualifiedTraitIds[] = 10;
        }
        // Workhorse
        if ( $prospect['technicalAbility'] >= 92 )
        {
            $qualifiedTraitIds[] = 13;
        }
        // Technician

        // Get current traits from the database
        $stmt = $this->db->prepare( "SELECT trait_id FROM prospect_traits WHERE prospect_id = :id" );
        $stmt->execute( [':id' => $prospectId] );
        $currentTraitIds = $stmt->fetchAll( PDO::FETCH_COLUMN );

        // Determine which traits to add and remove
        $traitsToAdd    = array_diff( $qualifiedTraitIds, $currentTraitIds );
        $traitsToRemove = array_diff( $currentTraitIds, $qualifiedTraitIds );

        // Add new traits
        if ( !empty( $traitsToAdd ) )
        {
            $sqlAdd    = "INSERT INTO prospect_traits (prospect_id, trait_id) VALUES ";
            $paramsAdd = [];
            foreach ( $traitsToAdd as $traitId )
            {
                $sqlAdd .= "(?, ?),";
                array_push( $paramsAdd, $prospectId, $traitId );
            }
            $sqlAdd  = rtrim( $sqlAdd, ',' );
            $stmtAdd = $this->db->prepare( $sqlAdd );
            $stmtAdd->execute( $paramsAdd );
        }

        // Remove old traits
        if ( !empty( $traitsToRemove ) )
        {
            $placeholders = implode( ',', array_fill( 0, count( $traitsToRemove ), '?' ) );
            $sqlRemove    = "DELETE FROM prospect_traits WHERE prospect_id = ? AND trait_id IN ($placeholders)";
            $paramsRemove = array_merge( [$prospectId], $traitsToRemove );
            $stmtRemove   = $this->db->prepare( $sqlRemove );
            $stmtRemove->execute( $paramsRemove );
        }
    }

    /**
     * Helper to check if a wrestler qualifies as a Giant.
     * @param string $heightStr
     * @param int $weight
     * @return bool
     */
    private function isGiant( $heightStr, $weight )
    {
        preg_match( '/(\d+)\'(\d+)"?/', $heightStr, $matches );
        if ( count( $matches ) === 3 )
        {
            $feet        = (int) $matches[1];
            $inches      = (int) $matches[2];
            $totalInches = ( $feet * 12 ) + $inches;
            return $totalInches >= 82 && $weight >= 300; // 6'10" = 82 inches
        }
        return false;
    }

    /**
     * Retires a prospect by adding them to the main roster, then deleting their prospect entry.
     * @param string $userId The user ID of the retiring prospect's owner.
     * @return bool|string True on success, error message on failure.
     */
    public function retireProspectToRoster( $userId )
    {
        $this->db->beginTransaction();
        try {
            $prospect = $this->getWrestlerByUserId( $userId );

            if ( !$prospect )
            {
                $this->db->rollBack();
                return "Prospect not found.";
            }

            if ( $prospect['lvl'] < 100 )
            {
                $this->db->rollBack();
                return "Only prospects at Level 100 can be retired.";
            }

            $sql = "INSERT INTO roster (name, height, weight, description, lvl, baseHp, strength, technicalAbility, brawlingAbility, stamina, aerialAbility, toughness, reversalAbility, submissionDefense, staminaRecoveryRate, moves, image)
                    VALUES (:name, :height, :weight, :description, :lvl, :baseHp, :strength, :technicalAbility, :brawlingAbility, :stamina, :aerialAbility, :toughness, :reversalAbility, :submissionDefense, :staminaRecoveryRate, :moves, :image)";

            $stmt = $this->db->prepare( $sql );

            $stmt->execute( [
                ':name'                => $prospect['name'] . ' (Retired)',
                ':height'              => $prospect['height'],
                ':weight'              => $prospect['weight'],
                ':description'         => $prospect['description'] ?? 'A legendary prospect who has entered the Hall of Fame.',
                ':lvl'                 => $prospect['lvl'],
                ':baseHp'              => $prospect['baseHp'],
                ':strength'            => $prospect['strength'],
                ':technicalAbility'    => $prospect['technicalAbility'],
                ':brawlingAbility'     => $prospect['brawlingAbility'],
                ':stamina'             => $prospect['stamina'],
                ':aerialAbility'       => $prospect['aerialAbility'],
                ':toughness'           => $prospect['toughness'],
                ':reversalAbility'     => $prospect['reversalAbility'],
                ':submissionDefense'   => $prospect['submissionDefense'],
                ':staminaRecoveryRate' => $prospect['staminaRecoveryRate'],
                ':moves'               => $prospect['moves'],
                ':image'               => $prospect['image'],
            ] );

            // Now, delete the prospect and update the user
            $stmtDelete = $this->db->prepare( "DELETE FROM prospects WHERE pid = :pid" );
            $stmtDelete->execute( [':pid' => $prospect['pid']] );

            $stmtUser = $this->db->prepare( "UPDATE users SET prospect_id = NULL WHERE user_id = :user_id" );
            $stmtUser->execute( [':user_id' => $userId] );

            $this->db->commit();
            return true;

        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
            return 'Database error: ' . $e->getMessage();
        }
    }
}
