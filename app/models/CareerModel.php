<?php

namespace App\Model;
use Src\Model\System_Model;
use PDO;
use RedBeanPHP\R;

class CareerModel extends System_Model
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * Fetches a prospect's data from the database by their user ID.
     * @param string $userId The ID of the user.
     * @return \RedBeanPHP\OODBBean|NULL The wrestler data bean or null if not found.
     */
    public function getWrestlerByUserId($userId)
    {
        // The error indicates the 'user_id' column is missing in the prospects table.
        // We will use the relationship from the 'users' table instead.
        // First, find the user to get their associated prospect_id.
        $user = R::findOne('users', 'user_id = ?', [$userId]);

        // If the user is found and has a prospect_id, load that prospect bean.
        if ($user && $user->prospect_id) {
            return R::load('prospects', $user->prospect_id);
        }

        // If no user or prospect_id is found, return null.
        return null;
    }
    
    /**
     * Fetches a main roster wrestler's data by their ID.
     * @param int $wrestlerId The ID of the wrestler.
     * @return \RedBeanPHP\OODBBean|NULL The wrestler data bean or null if not found.
     */
    public function getWrestlerById($wrestlerId)
    {
        return R::findOne('wrestler', 'wrestler_id = ?', [$wrestlerId]);
    }

    /**
     * Creates a new prospect for a user.
     * @param array $data An array containing the new prospect's details.
     * @return bool True on success, false on failure.
     */
    public function createProspect($data)
    {
        $prospect = R::dispense('prospects');
        // Defensively remove 'id' to prevent the "Undefined array key" warning when storing a new bean.
        unset($data['id']);
        // Import the data from the array into the RedBeanPHP bean object.
        $prospect->import($data);
        // Store the bean in the database.
        $id = R::store($prospect);
        return $id > 0;
    }

    /**
     * Upgrades a prospect's attribute, using a free point if available, otherwise costing gold with a compounding formula.
     *
     * @param string $userId The ID of the user.
     * @param string $attribute The name of the attribute to upgrade.
     * @return bool|string True on success, or an error message string on failure.
     */
    public function purchaseAttributePoint($userId, $attribute)
    {
        try {
            // Step 1: Find the user to get their associated prospect_id (pid).
            $sqlUser = "SELECT prospect_id FROM users WHERE user_id = :user_id";
            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->execute([':user_id' => $userId]);
            $user = $stmtUser->fetch(\PDO::FETCH_ASSOC);

            if (!$user || empty($user['prospect_id'])) {
                return "Could not find a prospect for the current user.";
            }
            $prospectId = $user['prospect_id'];

            // Step 2: Whitelist the attribute to prevent SQL injection.
            $validAttributes = ['strength', 'technicalAbility', 'brawlingAbility', 'stamina', 'aerialAbility', 'toughness'];
            if (!in_array($attribute, $validAttributes)) {
                return "Invalid attribute specified.";
            }

            // Step 3: Fetch all necessary prospect data in one query.
            $sqlProspect = "SELECT attribute_points, gold, {$attribute} FROM prospects WHERE pid = :pid";
            $stmtProspect = $this->db->prepare($sqlProspect);
            $stmtProspect->execute([':pid' => $prospectId]);
            $prospect = $stmtProspect->fetch(\PDO::FETCH_ASSOC);

            if (!$prospect) {
                return "Prospect data could not be found.";
            }

            // Step 4: Determine the update method (Free Point or Gold).
            if ($prospect['attribute_points'] > 0) {
                // Use a free attribute point.
                $sqlUpdate = "UPDATE prospects 
                            SET {$attribute} = {$attribute} + 1, 
                                attribute_points = attribute_points - 1 
                            WHERE pid = :pid";
                $params = [':pid' => $prospectId];
            } else {
                // **NEW COST LOGIC:** Use gold with the compounding formula.
                $currentLevel = (int)$prospect[$attribute];
                if ($currentLevel >= 100) {
                    return "This attribute is already at its maximum level.";
                }

                // The cost is 50 * (1.1 ^ (currentLevel - 50))
                $upgradeCost = ceil(50 * pow(1.1, $currentLevel - 50));

                if ($prospect['gold'] < $upgradeCost) {
                    return "You do not have enough gold ({$upgradeCost}) to upgrade this attribute.";
                }

                $sqlUpdate = "UPDATE prospects 
                            SET {$attribute} = {$attribute} + 1, 
                                gold = gold - :cost 
                            WHERE pid = :pid";
                $params = [':pid' => $prospectId, ':cost' => $upgradeCost];
            }

            // Step 5: Execute the update.
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute($params);

            if ($stmtUpdate->rowCount() > 0) {
                return true;
            } else {
                return "Failed to update the attribute. Please try again.";
            }

        } catch (\PDOException $e) {
            // In a production environment, you would log this error.
            // error_log($e->getMessage());
            return "A database error occurred during the upgrade process.";
        }
    }

    public function can_upgrade($prospect, $cost)
    {
        return $prospect->gold >= $cost;
    }

    public function upgrade_prospect_attribute($prospect, $attribute, $cost)
    {
        // The prospect is already loaded, so we can just modify it
        $prospect->gold -= $cost;
        $prospect->{$attribute} += 1; // Increment the attribute

        // Now, store the updated prospect. RedBeanPHP knows the ID.
        R::store($prospect);
    }

    /**
     * Finds a suitable opponent for the prospect from the main roster.
     * @param object $prospect The prospect bean.
     * @return \RedBeanPHP\OODBBean|NULL An opponent bean or null if none are found.
     */
    public function findOpponentForProspect($prospect)
    {
        // Corrected the column name from 'id' to 'wrestler_id' to match the database schema.
        return R::findOne('wrestler', ' wrestler_id != ? ORDER BY RAND() LIMIT 1', [$prospect->id]);
    }

    /**
     * Updates the prospect's record and stats after a match.
     * @param int $prospectId The ID of the prospect.
     * @param bool $won True if the prospect won, false otherwise.
     * @return bool True on success, false on failure.
     */
    public function updateProspectAfterMatch($prospectId, $won)
    {
        $prospect = R::load('prospects', $prospectId);
        if (!$prospect->id) {
            return false;
        }

        if ($won) {
            $prospect->wins++;
            $prospect->money += 500;
            $prospect->attribute_points++;
        } else {
            $prospect->losses++;
            $prospect->money += 100;
        }
        R::store($prospect);
        return true;
    }

    /**
     * Records the outcome of a match in the matches table.
     * @param int $wrestler1_id The ID of the first wrestler (prospect).
     * @param int $wrestler2_id The ID of the second wrestler (opponent).
     * @param int $winner_id The ID of the winning wrestler.
     * @return bool True on success, false on failure.
     */
    public function recordMatchOutcome($wrestler1_id, $wrestler2_id, $winner_id)
    {
        $match = R::dispense('matches');
        $match->wrestler1_id = $wrestler1_id;
        $match->wrestler2_id = $wrestler2_id;
        $match->winner_id = $winner_id;
        $match->match_date = R::isoDateTime();
        $id = R::store($match);
        return $id > 0;
    }

    /**
     * Fetches the moveset for a given prospect.
     * @param int $prospectId The ID of the prospect.
     * @return array An array of move objects.
     */
    public function getProspectMoves($prospectId)
    {
        // Use RedBeanPHP's SQL querying for joins.
        $sql = 'SELECT am.* FROM all_moves am JOIN prospect_moves pm ON am.move_id = pm.move_id WHERE pm.prospect_id = :prospect_id';
        return R::getAll($sql, [':prospect_id' => $prospectId]);
    }
}
