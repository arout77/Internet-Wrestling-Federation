<?php
namespace App\Models;

use PDO;
use Rhapsody\Core\BaseModel;

class Api extends BaseModel
{
    /**
     * Alias for getWrestlerById. Now includes a check to extract ID if an object/array is passed.
     * @param mixed $id Wrestler ID (string/int) or potentially an object/array containing the ID.
     * @return array|null Wrestler data or null.
     */
    public function get_wrestler($id)
    {
        $actualId = null;

        // --- FIX: Extract ID if an object or array is passed ---
        if (is_object($id)) {
            if (isset($id->wrestler_id)) {
                $actualId = $id->wrestler_id;
            } elseif (isset($id->pid)) {
                $actualId = $id->pid;
            }
        } elseif (is_array($id)) {
            if (isset($id['wrestler_id'])) {
                $actualId = $id['wrestler_id'];
            } elseif (isset($id['pid'])) {
                $actualId = $id['pid'];
            }
        } elseif (is_string($id) || is_numeric($id)) {
            // It's already an ID
            $actualId = $id;
        }
        // --- END FIX ---

        if ($actualId === null) {
            error_log("Api::get_wrestler could not determine a valid ID from input: " . print_r($id, true));
            return null;
        }

        // Call the core method with the guaranteed string/int ID
        return $this->getWrestlerById($actualId);
    }

    /**
     * Fetches a single wrestler strictly by their ID (string|int), checking both roster and prospects.
     * Returns data as a consistent associative array with both 'wrestler_id' and 'pid' keys.
     * @param string|int $id The wrestler's ID (roster ID or prospect PID). MUST be string or int.
     * @return array|null The wrestler data as a consistent associative array, or null if not found.
     */
    public function getWrestlerById($id): ?array
    {
        // --- Strict Type Check ---
        if (! is_string($id) && ! is_numeric($id)) {
            error_log("Api::getWrestlerById received invalid ID type: " . gettype($id) . " Value: " . print_r($id, true));
            return null; // Do not proceed if it's not a string or number
        }
        // --- End Strict Type Check ---

        $wrestler = null;

        // 1. Try fetching from the roster table if the ID is numeric
        if (is_numeric($id)) {
            $stmt = $this->db->prepare("SELECT *, wrestler_id as pid FROM roster WHERE wrestler_id = ?");
            $stmt->execute([(int) $id]); // Cast to int for safety
            $wrestler = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($wrestler) {
                // Ensure consistency
                $wrestler['pid'] = $wrestler['wrestler_id'];
                return $wrestler;
            }
        }

                                  // 2. If not found in roster OR if the ID wasn't numeric, try fetching from prospects using the ID as PID
        $stringId = (string) $id; // Cast to string for PID comparison
        $stmt     = $this->db->prepare("SELECT *, pid as wrestler_id FROM prospects WHERE pid = ?");
        $stmt->execute([$stringId]);
        $wrestler = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($wrestler) {
            // Ensure consistency
            $wrestler['wrestler_id'] = $wrestler['pid'];
            return $wrestler;
        }

        // 3. If not found in either table, log it and return null
        error_log("Wrestler not found in roster or prospects with ID/PID: " . $id);
        return null;
    }

    /**
     * Fetches all wrestlers from the roster, including their traits and calculated overall.
     */
    public function get_all_wrestlers()
    {
        $sql  = "SELECT * FROM roster ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $wrestlers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                             // Loop through wrestlers to add traits and calculate overall
        foreach ($wrestlers as &$wrestler) { // Use a reference to modify the array directly
                                                 // Fetch and attach traits
            $traitStmt = $this->db->prepare("
                SELECT t.name FROM traits t
                JOIN roster_traits rt ON t.trait_id = rt.trait_id
                WHERE rt.roster_wrestler_id = ?
            ");
            $traitStmt->execute([$wrestler['wrestler_id']]);
            // Ensure traits is always an array, even if empty
            $wrestler['traits'] = $traitStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            // --- Overall Calculation Logic ---
            $coreSkillSum       = ($wrestler['strength'] * 1.01) + ($wrestler['technicalAbility'] * 1.2) + $wrestler['brawlingAbility'] + ($wrestler['aerialAbility'] * 1.15);
            $coreSkillAvg       = $coreSkillSum / 4.36;
            $durabilityAvg      = ($wrestler['stamina'] + $wrestler['toughness']) / 2;
            $preliminaryOverall = ($coreSkillAvg * 0.7) + ($durabilityAvg * 0.3);

            $num_stats_over_80 = 0;
            $num_stats_over_95 = 0;
            if ($wrestler['strength'] >= 80) {
                $num_stats_over_80++;
            }

            if ($wrestler['technicalAbility'] >= 80) {
                $num_stats_over_80++;
            }

            if ($wrestler['brawlingAbility'] >= 80) {
                $num_stats_over_80++;
            }

            if ($wrestler['aerialAbility'] >= 80) {
                $num_stats_over_80++;
            }

            if ($wrestler['strength'] >= 95) {
                $num_stats_over_95++;
            }

            if ($wrestler['technicalAbility'] >= 95) {
                $num_stats_over_95++;
            }

            if ($wrestler['brawlingAbility'] >= 95) {
                $num_stats_over_95++;
            }

            if ($wrestler['aerialAbility'] >= 95) {
                $num_stats_over_95++;
            }

            $bonus = 0;
            if ($num_stats_over_80 >= 4 && $durabilityAvg >= 90) {
                $bonus = 5 + $num_stats_over_95; // Icon Bonus
            } elseif ($num_stats_over_80 >= 3) {
                $bonus = 3 + $num_stats_over_95; // Legend Bonus
            } elseif ($num_stats_over_80 >= 2) {
                $bonus = 1 + $num_stats_over_95; // Prime Bonus
            }
            $wrestler['overall'] = round($preliminaryOverall + $bonus);
        }
        unset($wrestler); // Important: unset the reference after the loop

        return $wrestlers;
    }

    public function get_wrestlers_by_weight($weight)
    {
        $stmt = $this->db->prepare("SELECT * FROM roster WHERE weight >= ?");
        $stmt->execute([$weight]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_wrestlers_by_technical($val)
    {
        $stmt = $this->db->prepare("SELECT * FROM roster WHERE technicalAbility >= ?");
        $stmt->execute([$val]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_wrestlers_by_brawling($val)
    {
        $stmt = $this->db->prepare("SELECT * FROM roster WHERE brawlingAbility >= ?");
        $stmt->execute([$val]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_wrestlers_by_aerial($val)
    {
        $stmt = $this->db->prepare("SELECT * FROM roster WHERE aerialAbility >= ?");
        $stmt->execute([$val]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_wrestlers_by_strength($val)
    {
        $stmt = $this->db->prepare("SELECT * FROM roster WHERE strength >= ?");
        $stmt->execute([$val]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all moves from the database.
     */
    public function getAllMoves()
    {
        $stmt = $this->db->prepare("SELECT * FROM all_moves");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all tag teams and their members.
     */
    public function getAllTagTeams(): array
    {
        $sql = "SELECT tt.team_name, tt.team_image, GROUP_CONCAT(r.wrestler_id) as members
                FROM tag_teams tt
                JOIN roster r ON tt.team_name = r.tag_team
                GROUP BY tt.team_name, tt.team_image";
        $stmt  = $this->db->query($sql);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($teams as &$team) {
            $team['members'] = explode(',', $team['members']);
        }
        return $teams;
    }

    /**
     * AJAX endpoint to get prospect details and odds.
     */
    public function ajaxGetChallengeDetails(Request $request, string $pid): Response
    {
        if (! Session::has('user_id')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $opponent = $this->em->getRepository(Prospect::class)->findOneBy(['pid' => $pid]);
        if (! $opponent) {
            return $this->json(['error' => 'Prospect not found'], 404);
        }

        $user       = $this->em->find(User::class, Session::get('user_id'));
        $myProspect = $user->getProspect();
        if (! $myProspect) {
            return $this->json(['error' => 'You need a prospect to view challenge details'], 400);
        }

        // Generate odds (1000 simulations)
        $oddsData = $this->simulationService->generateOdds($myProspect, $opponent, 1000);

        $opponentData = [
            'pid'              => $opponent->getPid(),
            'name'             => $opponent->getName(),
            'lvl'              => $opponent->getLvl(),
            'height'           => $opponent->getHeight(),
            'weight'           => $opponent->getWeight(),
            'strength'         => $opponent->getStrength(),
            'technicalAbility' => $opponent->getTechnicalAbility(),
            'brawlingAbility'  => $opponent->getBrawlingAbility(),
            'stamina'          => $opponent->getStamina(),
            'aerialAbility'    => $opponent->getAerialAbility(),
            'toughness'        => $opponent->getToughness(),
            'image'            => $opponent->getImage(),
            'traits'           => $opponent->getTraits()->map(fn($t) => ['name' => $t->getName()])->toArray(),
            'manager_name'     => $opponent->getManagerId() ? $this->em->find(Manager::class, $opponent->getManagerId())?->getName() : null,
        ];

        return $this->json([
            'success'           => true,
            'opponent_prospect' => $opponentData,
            'odds'              => [
                'probabilities' => [
                    $myProspect->getName() => $oddsData['w1_win_percent'] / 100,
                    $opponent->getName()   => $oddsData['w2_win_percent'] / 100,
                ],
                'moneyline'     => [
                    $myProspect->getName() => $oddsData['w1_odds'],
                    $opponent->getName()   => $oddsData['w2_odds'],
                ],
            ],
        ]);
    }
}
