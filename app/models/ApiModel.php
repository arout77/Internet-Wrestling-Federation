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
     * Fetches all wrestlers and their data
     */
    public function get_all_wrestlers()
    {
        header( "Access-Control-Allow-Origin: *" ); // Allows requests from any origin. For production, restrict this to local
        header( "Content-Type: application/json; charset=UTF-8" );
        header( "Access-Control-Allow-Methods: GET" );
        header( "Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With" );

        try {
            // SQL query to fetch wrestler data, including the 'moves' JSON column directly
            // Using wrestler_id as the primary key as per the schema
            $sql = "SELECT * FROM roster";

            $stmt = $this->db->prepare( $sql );
            $stmt->execute();
            $wrestlers = $stmt->fetchAll( \PDO::FETCH_ASSOC );

            // No need for complex grouping here, as 'moves' is already a JSON string per wrestler
            echo json_encode( $wrestlers );

        }
        catch ( \PDOException $e )
        {
            // Return a JSON error response
            echo json_encode( ['error' => 'Database error: ' . $e->getMessage()] );
        }
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
}
