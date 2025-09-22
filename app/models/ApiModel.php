<?php
namespace App\Model;

use Src\Model\System_Model;

class ApiModel extends System_Model
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
    }

    /**
     * Fetches a single wrestler from the roster by their name.
     * @param string $name The name of the wrestler to fetch.
     * @return object|false The wrestler data object, or false if not found.
     */
    public function getWrestlerByName( $name )
    {
        $stmt = $this->db->prepare( "SELECT * FROM roster WHERE name = ?" );
        $stmt->execute( [$name] );
        $wrestler = $stmt->fetch( \PDO::FETCH_OBJ );

        if ( $wrestler )
        {
            // Fetch and attach traits
            $traitStmt = $this->db->prepare( "
                SELECT t.name FROM traits t
                JOIN roster_traits rt ON t.trait_id = rt.trait_id
                WHERE rt.roster_wrestler_id = ?
            " );
            $traitStmt->execute( [$wrestler->wrestler_id] );
            $wrestler->traits = $traitStmt->fetchAll( \PDO::FETCH_COLUMN ) ?: [];

            // Fetch and attach moveset
            $moveStmt = $this->db->prepare( "
                SELECT am.*
                FROM all_moves am
                JOIN roster_moves rm ON am.move_id = rm.move_id
                WHERE rm.roster_wrestler_id = ?
            " );
            $moveStmt->execute( [$wrestler->wrestler_id] );
            $moves = $moveStmt->fetchAll( \PDO::FETCH_OBJ );

            // Organize moves by type
            $wrestler->moves = new \stdClass();
            foreach ( $moves as $move )
            {
                $type = $move->type;
                if ( !isset( $wrestler->moves->$type ) )
                {
                    $wrestler->moves->$type = [];
                }
                $wrestler->moves->$type[] = $move;
            }
        }

        return $wrestler;
    }

    /**
     * Fetches a single wrestler from the roster by their ID, now including their full moveset.
     * @param int $id The ID of the wrestler to fetch.
     * @return object|false The wrestler data object, or false if not found.
     */
    public function getWrestlerById( $id )
    {
        $stmt = $this->db->prepare( "SELECT * FROM roster WHERE wrestler_id = ?" );
        $stmt->execute( [$id] );
        $wrestler = $stmt->fetch( \PDO::FETCH_OBJ );

        if ( $wrestler )
        {
            // Fetch and attach traits
            $traitStmt = $this->db->prepare( "
                SELECT t.name FROM traits t
                JOIN roster_traits rt ON t.trait_id = rt.trait_id
                WHERE rt.roster_wrestler_id = ?
            " );
            $traitStmt->execute( [$id] );
            $wrestler->traits = $traitStmt->fetchAll( \PDO::FETCH_COLUMN ) ?: [];

            // Fetch and attach moveset
            $moveStmt = $this->db->prepare( "
                SELECT am.*
                FROM all_moves am
                JOIN roster_moves rm ON am.move_id = rm.move_id
                WHERE rm.roster_wrestler_id = ?
            " );
            $moveStmt->execute( [$id] );
            $moves = $moveStmt->fetchAll( \PDO::FETCH_OBJ );

            // Organize moves by type (strike, grapple, etc.)
            $wrestler->moves = [];
            foreach ( $moves as $move )
            {
                $wrestler->moves[$move->type][] = $move;
            }
        }

        return $wrestler;
    }

    public function submissionMoves()
    {
        $moves = $this->simulatorModel->getSubmissionMoves();
        header( 'Content-Type: application/json' );
        echo json_encode( $moves );
    }

    /**
     * Fetches all wrestlers and their data, now including full movesets.
     * @return array
     */
    public function get_all_wrestlers()
    {
        $sql  = "SELECT * FROM roster";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute();
        $wrestlers = $stmt->fetchAll( \PDO::FETCH_OBJ );

        $all_moves_stmt = $this->db->prepare( "
            SELECT am.*, am.type, rm.roster_wrestler_id
            FROM all_moves am
            JOIN roster_moves rm ON am.move_id = rm.move_id
        " );
        $all_moves_stmt->execute();
        $all_moves_data = $all_moves_stmt->fetchAll( \PDO::FETCH_OBJ );

        // Create a map of wrestler_id to their moves for efficient lookup
        $moves_map = [];
        foreach ( $all_moves_data as $move )
        {
            $moves_map[$move->roster_wrestler_id][$move->type][] = $move;
        }

        foreach ( $wrestlers as $wrestler )
        {
            // Calculate Overall
            $coreSkillSum       = ( $wrestler->strength * 1.01 ) + ( $wrestler->technicalAbility * 1.2 ) + $wrestler->brawlingAbility + ( $wrestler->aerialAbility * 1.15 );
            $coreSkillAvg       = $coreSkillSum / 4.36;
            $durabilityAvg      = ( $wrestler->stamina + $wrestler->toughness ) / 2;
            $preliminaryOverall = ( $coreSkillAvg * 0.7 ) + ( $durabilityAvg * 0.3 );

            $num_stats_over_80 = 0;
            $num_stats_over_95 = 0;
            if ( $wrestler->strength >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->technicalAbility >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->brawlingAbility >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->aerialAbility >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->strength >= 95 )
            {
                $num_stats_over_95++;
            }

            if ( $wrestler->technicalAbility >= 95 )
            {
                $num_stats_over_95++;
            }

            if ( $wrestler->brawlingAbility >= 95 )
            {
                $num_stats_over_95++;
            }

            if ( $wrestler->aerialAbility >= 95 )
            {
                $num_stats_over_95++;
            }

            $bonus = 0;
            if ( $num_stats_over_80 >= 4 && $durabilityAvg >= 90 )
            {
                $bonus = 5 + $num_stats_over_95;
            }
            // Icon Bonus

            elseif ( $num_stats_over_80 >= 3 )
            {
                $bonus = 3 + $num_stats_over_95;
            }
            // Legend Bonus

            elseif ( $num_stats_over_80 >= 2 )
            {
                $bonus = 1 + $num_stats_over_95;
            }
            // Prime Bonus

            $wrestler->overall = round( $preliminaryOverall + $bonus );

            // Fetch and attach traits
            $traitStmt = $this->db->prepare( "
                SELECT t.name FROM traits t
                JOIN roster_traits rt ON t.trait_id = rt.trait_id
                WHERE rt.roster_wrestler_id = ?
            " );
            $traitStmt->execute( [$wrestler->wrestler_id] );
            $wrestler->traits = $traitStmt->fetchAll( \PDO::FETCH_COLUMN ) ?: [];

            // Assign moves from the map
            $wrestler->moves = $moves_map[$wrestler->wrestler_id] ?? [];
        }

        return $wrestlers;
    }

    /**
     * Fetches all moves from the database.
     * @return array A list of all move objects.
     */
    public function getAllMoves()
    {
        $sql  = "SELECT * FROM all_moves";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute();
        return $stmt->fetchAll( \PDO::FETCH_OBJ );
    }

    /**
     * Fetches all valid moves
     */
    public function get_moves()
    {
        header( "Access-Control-Allow-Origin: *" ); // Allows requests from any origin. For production, restrict this to your React app's domain.
        header( "Content-Type: application/json; charset=UTF-8" );
        header( "Access-Control-Allow-Methods: GET" );
        header( "Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With" );

        try {
            // SQL query to fetch all move data, including damage, stamina cost, and description
            $sql = "SELECT * FROM all_moves";

            $stmt = $this->db->prepare( $sql );
            $stmt->execute();

            // Fetch all results as an associative array
            // This is the key change to ensure 'name', 'type', 'damage_min', etc., are direct keys
            $moves = $stmt->fetchAll( \PDO::FETCH_ASSOC );

            // Encode the moves array as JSON and output it
            echo json_encode( $moves );

        }
        catch ( \PDOException $e )
        {
            // Return a JSON error response
            echo json_encode( ['error' => 'Database error: ' . $e->getMessage()] );
        }
    }

    /**
     * Records a wrestler's win - loss record, as well as
     * win - loss record verses other wrestlers
     *
     * @param $data
     */
    public function record_match_result( $data )
    {
        try {
            $db->beginTransaction();

            $matchType = $data->match_type;
            $isDraw    = isset( $data->is_draw ) ? (bool) $data->is_draw : false;

            if ( $matchType === 'single' )
            {
                if ( !isset( $data->player1_id ) || !isset( $data->player2_id ) || ( !isset( $data->winner_id ) && !$isDraw ) )
                {
                    http_response_code( 400 );
                    echo json_encode( ['error' => 'Missing data for single match'] );
                    exit();
                }

                $player1Id = $data->player1_id;
                $player2Id = $data->player2_id;
                $winnerId  = $isDraw ? null : $data->winner_id;
                $loserId   = null;
                if ( !$isDraw )
                {
                    $loserId = ( $winnerId === $player1Id ) ? $player2Id : $player1Id;
                }

                // Insert into matches table
                $stmt = $db->prepare( "INSERT INTO matches (match_type, player1_id, player2_id, single_winner_id, single_loser_id, is_draw) VALUES (?, ?, ?, ?, ?, ?)" );
                $stmt->execute( [$matchType, $player1Id, $player2Id, $winnerId, $loserId, $isDraw] );

                // Update wrestler records
                $participants = [$player1Id, $player2Id];
                foreach ( $participants as $wrestlerId )
                {
                    $stmt   = $db->prepare( "INSERT INTO wrestler_records (wrestler_id, wins, losses, draws) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE wins = wins + ?, losses = losses + ?, draws = draws + ?" );
                    $wins   = 0;
                    $losses = 0;
                    $draws  = 0;

                    if ( $isDraw )
                    {
                        $draws = 1;
                    }
                    elseif ( $wrestlerId === $winnerId )
                    {
                        $wins = 1;
                    }
                    else
                    {
                        $losses = 1;
                    }
                    $stmt->execute( [$wrestlerId, $wins, $losses, $draws, $wins, $losses, $draws] );
                }

            }
            elseif ( $matchType === 'tag_team' )
            {
                if ( !isset( $data->team1_player1_id ) || !isset( $data->team1_player2_id ) ||
                    !isset( $data->team2_player1_id ) || !isset( $data->team2_player2_id ) ||
                    ( !isset( $data->winning_team_ids ) && !$isDraw ) )
                {
                    http_response_code( 400 );
                    echo json_encode( ['error' => 'Missing data for tag team match'] );
                    exit();
                }

                $team1P1 = $data->team1_player1_id;
                $team1P2 = $data->team1_player2_id;
                $team2P1 = $data->team2_player1_id;
                $team2P2 = $data->team2_player2_id;

                // Create canonical team IDs
                $team1Ids = [$team1P1, $team1P2];
                sort( $team1Ids );
                $canonicalTeam1Id = implode( '_', $team1Ids );

                $team2Ids = [$team2P1, $team2P2];
                sort( $team2Ids );
                $canonicalTeam2Id = implode( '_', $team2Ids );

                $winningTeamId = $isDraw ? null : $data->winning_team_ids; // This should be the canonical ID of the winning team
                $losingTeamId  = null;
                if ( !$isDraw )
                {
                    $losingTeamId = ( $winningTeamId === $canonicalTeam1Id ) ? $canonicalTeam2Id : $canonicalTeam1Id;
                }

                // Insert into matches table
                $stmt = $db->prepare( "INSERT INTO matches (match_type, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id, team_winner_id, team_loser_id, is_draw) VALUES (?, ?, ?, ?, ?, ?, ?, ?)" );
                $stmt->execute( [$matchType, $team1P1, $team1P2, $team2P1, $team2P2, $winningTeamId, $losingTeamId, $isDraw] );

                // Update individual wrestler records
                $allParticipants = [$team1P1, $team1P2, $team2P1, $team2P2];
                foreach ( $allParticipants as $wrestlerId )
                {
                    $stmt   = $db->prepare( "INSERT INTO wrestler_records (wrestler_id, wins, losses, draws) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE wins = wins + ?, losses = losses + ?, draws = draws + ?" );
                    $wins   = 0;
                    $losses = 0;
                    $draws  = 0;

                    if ( $isDraw )
                    {
                        $draws = 1;
                    }
                    elseif ( in_array( $wrestlerId, ( $winningTeamId === $canonicalTeam1Id ? [$team1P1, $team1P2] : [$team2P1, $team2P2] ) ) )
                    {
                        $wins = 1;
                    }
                    else
                    {
                        $losses = 1;
                    }
                    $stmt->execute( [$wrestlerId, $wins, $losses, $draws, $wins, $losses, $draws] );
                }

                // Update team records
                $teamsToUpdate = [];
                if ( $isDraw )
                {
                    $teamsToUpdate[$canonicalTeam1Id] = ['wrestlers' => [$team1P1, $team1P2], 'result' => 'draw'];
                    $teamsToUpdate[$canonicalTeam2Id] = ['wrestlers' => [$team2P1, $team2P2], 'result' => 'draw'];
                }
                else
                {
                    $teamsToUpdate[$winningTeamId] = ['wrestlers' => ( $winningTeamId === $canonicalTeam1Id ? [$team1P1, $team1P2] : [$team2P1, $team2P2] ), 'result' => 'win'];
                    $teamsToUpdate[$losingTeamId]  = ['wrestlers' => ( $losingTeamId === $canonicalTeam1Id ? [$team1P1, $team1P2] : [$team2P1, $team2P2] ), 'result' => 'loss'];
                }

                foreach ( $teamsToUpdate as $teamId => $teamData )
                {
                    $w1 = $teamData['wrestlers'][0];
                    $w2 = $teamData['wrestlers'][1];

                    $stmt   = $db->prepare( "INSERT INTO team_records (team_id, wrestler1_id, wrestler2_id, wins, losses, draws) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE wins = wins + ?, losses = losses + ?, draws = draws + ?" );
                    $wins   = 0;
                    $losses = 0;
                    $draws  = 0;

                    if ( $teamData['result'] === 'win' )
                    {
                        $wins = 1;
                    }
                    elseif ( $teamData['result'] === 'loss' )
                    {
                        $losses = 1;
                    }
                    else
                    { // draw
                        $draws = 1;
                    }
                    $stmt->execute( [$teamId, $w1, $w2, $wins, $losses, $draws, $wins, $losses, $draws] );
                }

            }
            else
            {
                http_response_code( 400 );
                echo json_encode( ['error' => 'Invalid match_type'] );
                exit();
            }

            $db->commit();
            http_response_code( 200 );
            echo json_encode( ['message' => 'Match result recorded successfully'] );

        }
        catch ( PDOException $e )
        {
            $db->rollBack();
            http_response_code( 500 );
            echo json_encode( ['error' => 'Database error: ' . $e->getMessage()] );
        }
    }

    /**
     * @param $category
     * @param $subcategory
     * @param $version
     */
    public function updateDocPage( $category, $subcategory, $version = '1.0.0' )
    {
        // Create new page
        $db                 = $this->load( 'documentation' );
        $db->category       = $category;
        $db->subcategory    = $subcategory;
        $db->content        = '';
        $db->version        = $version;
        $db->last_edit_date = date( "Y-m-d" );
        $id                 = $this->store( $db );
    }

    /**
     * Runs a specified number of simulations and returns the aggregated results.
     */
    public function runBulkSimulations( $wrestler1, $wrestler2, $simCount )
    {
        $winCounts = [
            $wrestler1->name => 0,
            $wrestler2->name => 0,
            'draw'           => 0,
        ];

        for ( $i = 0; $i < $simCount; $i++ )
        {
            $result = $this->simulateMatch( $wrestler1, $wrestler2, true );

            if ( is_object( $result['winner'] ) )
            {
                $winCounts[$result['winner']->name]++;
            }
            else
            {
                $winCounts['draw']++;
            }
        }

        // **NEW:** Calculate probabilities and moneyline odds
        $probabilities = [];
        $moneylineOdds = [];
        foreach ( $winCounts as $name => $wins )
        {
            $probability          = $wins / $simCount;
            $probabilities[$name] = $probability;
            $moneylineOdds[$name] = $this->calculateMoneyline( $probability );
        }

        return [
            'wins'          => $winCounts,
            'probabilities' => $probabilities,
            'moneyline'     => $moneylineOdds,
        ];
    }

    /**
     * Helper function to calculate American (moneyline) odds from a probability, including a 10% vig.
     * @param float $probability The probability of an outcome (0 to 1).
     * @return string The moneyline odds string (e.g., "+250", "-150").
     */
    private function calculateMoneyline( $probability )
    {
        if ( $probability <= 0 )
        {
            return '+9900';
        }

        if ( $probability >= 1 )
        {
            return '-99900';
        }

        if ( $probability < 0.5 )
        {
            $odds = ( 100 / $probability ) - 100;
            return '+' . min( 9900, round( $odds ) );
        }
        else
        {
            $odds = -100 / ( ( 100 / ( 100 * $probability ) ) - 1 );
            return round( $odds );
        }
    }

    /**
     * Fetches wrestlers based on a minimum weight.
     * @param int $min_weight The minimum weight to qualify.
     * @return array A list of wrestler objects.
     */
    public function get_wrestlers_by_weight( $min_weight )
    {
        $sql  = "SELECT * FROM roster WHERE weight >= :min_weight";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':min_weight' => $min_weight] );
        return $this->process_wrestler_overalls( $stmt->fetchAll( \PDO::FETCH_OBJ ) );
    }

    /**
     * Fetches wrestlers based on a minimum technical ability.
     * @param int $min_technical The minimum technical ability to qualify.
     * @return array A list of wrestler objects.
     */
    public function get_wrestlers_by_technical( $min_technical )
    {
        $sql  = "SELECT * FROM roster WHERE technicalAbility >= :min_technical";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':min_technical' => $min_technical] );
        return $this->process_wrestler_overalls( $stmt->fetchAll( \PDO::FETCH_OBJ ) );
    }

    /**
     * Fetches wrestlers based on a minimum brawling ability.
     * @param int $min_brawling The minimum brawling ability to qualify.
     * @return array A list of wrestler objects.
     */
    public function get_wrestlers_by_brawling( $min_brawling )
    {
        $sql  = "SELECT * FROM roster WHERE brawlingAbility >= :min_brawling";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':min_brawling' => $min_brawling] );
        return $this->process_wrestler_overalls( $stmt->fetchAll( \PDO::FETCH_OBJ ) );
    }

    /**
     * Fetches wrestlers based on a minimum aerial ability.
     * @param int $min_aerial The minimum aerial ability to qualify.
     * @return array A list of wrestler objects.
     */
    public function get_wrestlers_by_aerial( $min_aerial )
    {
        $sql  = "SELECT * FROM roster WHERE aerialAbility >= :min_aerial";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':min_aerial' => $min_aerial] );
        return $this->process_wrestler_overalls( $stmt->fetchAll( \PDO::FETCH_OBJ ) );
    }

    /**
     * Fetches wrestlers based on a minimum strength.
     * @param int $min_strength The minimum strength to qualify.
     * @return array A list of wrestler objects.
     */
    public function get_wrestlers_by_strength( $min_strength )
    {
        $sql  = "SELECT * FROM roster WHERE strength >= :min_strength";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':min_strength' => $min_strength] );
        return $this->process_wrestler_overalls( $stmt->fetchAll( \PDO::FETCH_OBJ ) );
    }

    /**
     * Helper function to process overalls for a list of wrestlers.
     * This avoids duplicating the overall calculation logic.
     * @param array $wrestlers An array of wrestler objects.
     * @return array The array of wrestler objects with overalls calculated.
     */
    private function process_wrestler_overalls( $wrestlers )
    {
        foreach ( $wrestlers as $wrestler )
        {
            // Your existing overall calculation logic
            $coreSkillSum =
            ( $wrestler->strength * 1.01 ) +
            ( $wrestler->technicalAbility * 1.2 ) +
            $wrestler->brawlingAbility +
                ( $wrestler->aerialAbility * 1.15 );
            $coreSkillAvg       = $coreSkillSum / 4.36;
            $durabilityAvg      = ( $wrestler->stamina + $wrestler->toughness ) / 2;
            $preliminaryOverall = ( $coreSkillAvg * 0.7 ) + ( $durabilityAvg * 0.3 );
            $num_stats_over_80  = 0;
            $num_stats_over_95  = 0;
            if ( $wrestler->strength >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->technicalAbility >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->brawlingAbility >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->aerialAbility >= 80 )
            {
                $num_stats_over_80++;
            }

            if ( $wrestler->strength >= 95 )
            {
                $num_stats_over_95++;
            }

            if ( $wrestler->technicalAbility >= 95 )
            {
                $num_stats_over_95++;
            }

            if ( $wrestler->brawlingAbility >= 95 )
            {
                $num_stats_over_95++;
            }

            if ( $wrestler->aerialAbility >= 95 )
            {
                $num_stats_over_95++;
            }

            $bonus = 0;
            if ( $num_stats_over_80 >= 4 && $durabilityAvg >= 90 )
            {
                $bonus = 5 + $num_stats_over_95; // Icon Bonus
            }
            elseif ( $num_stats_over_80 >= 3 )
            {
                $bonus = 3 + $num_stats_over_95; // Legend Bonus
            }
            elseif ( $num_stats_over_80 >= 2 )
            {
                $bonus = 1 + $num_stats_over_95; // Prime Bonus
            }
            $wrestler->overall = round( $preliminaryOverall + $bonus );
        }
        return $wrestlers;
    }
}
