<?php

// A command-line script to simulate matches for dummy prospects and populate their records.

// --- BOOTSTRAP ---
// This section loads the necessary framework files to access models and the database.
// Adjust the path if your script is in a different location relative to the project root.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/KernelApi.php';

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
$apiModel       = new \App\Model\ApiModel( $app );

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

    // 2. Loop through each prospect to assign and structure their movesets first
    foreach ( $dummyProspects as $index => &$prospect )
    {
        $prospectLevel = $prospect['lvl'];
        $eligibleMoves = array_filter( $allMoves, function ( $move ) use ( $prospectLevel )
        {
            return $move->level_requirement <= $prospectLevel;
        } );

        $newMovesetNames = json_decode( $prospect['moves'], true );
        if ( !is_array( $newMovesetNames ) )
        {
            $newMovesetNames = [];
        }

        $moveTypes = ['strike', 'grapple', 'submission', 'highFlying', 'finisher'];
        foreach ( $moveTypes as $type )
        {
            if ( !isset( $newMovesetNames[$type] ) || !is_array( $newMovesetNames[$type] ) )
            {
                $newMovesetNames[$type] = [];
            }
        }

        $categorizedMoves = ['strike' => [], 'grapple' => [], 'submission' => [], 'highFlying' => [], 'finisher' => []];
        foreach ( $eligibleMoves as $move )
        {
            if ( isset( $categorizedMoves[$move->type] ) )
            {
                $categorizedMoves[$move->type][] = $move->move_name;
            }
        }

        $allEligibleBasicMoves = array_merge( $categorizedMoves['strike'], $categorizedMoves['grapple'], $categorizedMoves['submission'], $categorizedMoves['highFlying'] );
        $currentBasicMoves     = array_merge( $newMovesetNames['strike'], $newMovesetNames['grapple'], $newMovesetNames['submission'], $newMovesetNames['highFlying'] );
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
                        $newMovesetNames[$em->type][] = $moveNameToAdd;
                        $newMovesetNames[$em->type]   = array_unique( $newMovesetNames[$em->type] );
                        break;
                    }
                }
            }
        }

        if ( !empty( $categorizedMoves['finisher'] ) )
        {
            $newMovesetNames['finisher'] = [$categorizedMoves['finisher'][array_rand( $categorizedMoves['finisher'] )]];
        }

        $careerModel->updateProspectMoveset( $prospect['pid'], json_encode( $newMovesetNames ) );

        // Build the structured moves object that the simulator expects
        $structuredMoves = [];
        foreach ( $newMovesetNames as $type => $moveNames )
        {
            $structuredMoves[$type] = [];
            foreach ( $moveNames as $name )
            {
                foreach ( $allMoves as $moveObj )
                {
                    if ( $moveObj->move_name === $name )
                    {
                        $structuredMoves[$type][] = $moveObj;
                        break;
                    }
                }
            }
        }
        // Attach the fully structured moves object to the prospect array itself
        $prospect['moves'] = (object) $structuredMoves;
    }
    // Unset the reference to avoid accidental modification later
    unset( $prospect );

    echo "All prospect movesets have been assigned and prepared for simulation.\n";
    echo "=============================================\n";

    // 3. Loop through each prepared prospect to simulate matches
    foreach ( $dummyProspects as $index => $prospect )
    {
        $prospectName = $prospect['name'];
        echo "Simulating for: $prospectName (" . ( $index + 1 ) . "/$prospectCount)\n";

        for ( $i = 1; $i <= MATCHES_PER_PROSPECT; $i++ )
        {
            $opponent = null;
            do
            {
                $opponent = $dummyProspects[array_rand( $dummyProspects )];
            } while ( $opponent['pid'] === $prospect['pid'] );

            // Both prospect and opponent now have structured moves objects
            $prospectObj              = (object) $prospect;
            $prospectObj->wrestler_id = $prospect['pid'];
            $prospectObj->traits      = array_column( $prospect['traits'], 'name' );

            $opponentObj              = (object) $opponent;
            $opponentObj->wrestler_id = $opponent['pid'];
            $opponentObj->traits      = array_column( $opponent['traits'], 'name' );

            $result = $simulatorModel->simulateMatch( $prospectObj, $opponentObj, true );

            if ( isset( $result['winner'] ) && is_object( $result['winner'] ) )
            {
                $winnerPid = $result['winner']->pid;
                $loserPid  = ( $winnerPid === $prospect['pid'] ) ? $opponent['pid'] : $prospect['pid'];
                $careerModel->recordWinLoss( $winnerPid, $loserPid );
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
