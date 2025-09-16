<?php

// A command-line script to simulate matches for dummy prospects and populate their records.

// --- BOOTSTRAP ---
// This section loads the necessary framework files to access models and the database.
// Adjust the path if your script is in a different location relative to the project root.
require_once '../../vendor/autoload.php';
require_once '../../src/KernelApi.php';

// --- VALIDATION ---
// Ensure the bootstrap process returned the application container correctly.
if ( !is_object( $app ) || !( $app instanceof \Pimple\Container ) )
{
    echo "Fatal Error: The bootstrap process did not return a valid application container.\n";
    echo "This can happen when running a web-first framework from the command line.\n";
    exit( 1 );
}

$db = $app['db']; // Get the database connection from the app container.

// --- MODELS ---
// Instantiate the models we'll need for this task.
$challengeModel = new \App\Model\ChallengeModel( $app );
$careerModel    = new \App\Model\CareerModel( $app );
$simulatorModel = new \App\Model\SimulatorModel( $app );
$apiModel       = new \App\Model\ApiModel( $app ); // Added ApiModel to fetch moves

// --- CONFIGURATION ---
define( 'MATCHES_PER_PROSPECT', 25 );

echo "Starting dummy prospect match simulation...\n";
echo "=============================================\n";

try {
    // 0. Fetch all available moves once to be efficient
    $allMoves = $apiModel->getAllMoves();
    if ( empty( $allMoves ) )
    {
        echo "No moves found in the database. Cannot assign moves. Exiting.\n";
        exit;
    }
    echo "Fetched " . count( $allMoves ) . " moves from the database.\n\n";

    // 1. Fetch all dummy prospects
    $dummyProspects = $challengeModel->getAllDummyProspects();
    if ( empty( $dummyProspects ) )
    {
        echo "No dummy prospects found (name LIKE 'IWF Wrestler%'). Exiting.\n";
        exit;
    }
    $prospectCount = count( $dummyProspects );
    echo "Found " . $prospectCount . " dummy prospects.\n";
    echo "Each prospect will have moves assigned and then be simulated in " . MATCHES_PER_PROSPECT . " matches.\n\n";

    // 2. Loop through each prospect to assign moves and simulate matches
    foreach ( $dummyProspects as $index => &$prospect )
    { // Use reference to update in place
        $prospectName = $prospect['name'];
        echo "Processing: $prospectName (" . ( $index + 1 ) . "/$prospectCount)\n";

        // 2a. Assign new moves based on level
        $prospectLevel = $prospect['lvl'];
        $eligibleMoves = array_filter( $allMoves, function ( $move ) use ( $prospectLevel )
    {
            return $move->level_requirement <= $prospectLevel;
        } );

        $newMoveset = json_decode( $prospect['moves'], true );

        // FIX: Ensure the moveset structure is always a valid array to prevent type errors
        if ( !is_array( $newMoveset ) )
    {
            $newMoveset = [];
        }
        $moveTypes = ['strike', 'grapple', 'submission', 'highFlying', 'finisher'];
        foreach ( $moveTypes as $type )
    {
            if ( !isset( $newMoveset[$type] ) || !is_array( $newMoveset[$type] ) )
        {
                $newMoveset[$type] = [];
            }
        }

        // Categorize eligible moves
        $categorizedMoves = ['strike' => [], 'grapple' => [], 'submission' => [], 'highFlying' => [], 'finisher' => []];
        foreach ( $eligibleMoves as $move )
    {
            if ( isset( $categorizedMoves[$move->type] ) )
        {
                $categorizedMoves[$move->type][] = $move->move_name;
            }
        }

        // Add 5 new random moves, avoiding duplicates
        $allEligibleBasicMoves = array_merge( $categorizedMoves['strike'], $categorizedMoves['grapple'], $categorizedMoves['submission'], $categorizedMoves['highFlying'] );
        $currentBasicMoves     = array_merge( $newMoveset['strike'], $newMoveset['grapple'], $newMoveset['submission'], $newMoveset['highFlying'] );
        $potentialNewMoves     = array_diff( $allEligibleBasicMoves, $currentBasicMoves );

        if ( !empty( $potentialNewMoves ) )
    {
            shuffle( $potentialNewMoves );
            for ( $i = 0; $i < min( 5, count( $potentialNewMoves ) ); $i++ )
        {
                $moveNameToAdd = $potentialNewMoves[$i];
                foreach ( $eligibleMoves as $em )
            {
                    if ( $em->move_name === $moveNameToAdd )
                {
                        $newMoveset[$em->type][] = $moveNameToAdd;
                        $newMoveset[$em->type]   = array_unique( $newMoveset[$em->type] );
                        break;
                    }
                }
            }
        }

        // Assign a new, level-appropriate finisher
        if ( !empty( $categorizedMoves['finisher'] ) )
    {
            $newMoveset['finisher'] = [$categorizedMoves['finisher'][array_rand( $categorizedMoves['finisher'] )]];
        }

        // Save the new moveset to the database
        $newMovesetJson = json_encode( $newMoveset );
        $careerModel->updateProspectMoveset( $prospect['pid'], $newMovesetJson );
        $prospect['moves'] = $newMovesetJson; // Update the local prospect array for the simulation
        echo "  - Assigned new moveset.\n";

        // 2b. Simulate matches
        for ( $i = 1; $i <= MATCHES_PER_PROSPECT; $i++ )
    {
            // Select a random opponent
            $opponent = null;
            do
        {
                $opponent = $dummyProspects[array_rand( $dummyProspects )];
            } while ( $opponent['pid'] === $prospect['pid'] );

            // Prepare objects for the simulator
            $prospectObj              = (object) $prospect;
            $prospectObj->wrestler_id = $prospect['pid'];
            $prospectObj->traits      = array_column( $prospect['traits'], 'name' );

            $opponentObj              = (object) $opponent;
            $opponentObj->wrestler_id = $opponent['pid'];
            $opponentObj->traits      = array_column( $opponent['traits'], 'name' );

            // Run the simulation
            $result = $simulatorModel->simulateMatch( $prospectObj, $opponentObj, true );

            // Record the result
            if ( isset( $result['winner'] ) && is_object( $result['winner'] ) )
        {
                $winnerPid = $result['winner']->pid;
                $loserPid  = ( $winnerPid === $prospect['pid'] ) ? $opponent['pid'] : $prospect['pid'];
                $careerModel->recordWinLoss( $winnerPid, $loserPid );
                // Also record the full match outcome for streak tracking
                $careerModel->recordMatchOutcome( $prospect['pid'], $opponent['pid'], $winnerPid );
            }
        }
        echo "  - Completed " . MATCHES_PER_PROSPECT . " match simulations.\n";
        echo "---------------------------------------------\n";
    }

    echo "\nSimulation complete! All dummy prospect movesets and records have been updated.\n";

}
catch ( Exception $e )
{
    echo "\nAn error occurred: " . $e->getMessage() . "\n";
}
