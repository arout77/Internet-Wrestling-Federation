<?php
// scripts/migrate_moves.php

/**
 * This script migrates wrestler movesets from the old JSON format
 * in the `roster` and `prospects` tables to the new normalized
 * `roster_moves` and `prospect_moves` junction tables.
 *
 * USAGE: Run from the command line in your project's root directory:
 * > php scripts/migrate_moves.php
 */

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

echo "Starting moveset migration...\n";
echo "=============================\n";

try {
    // --- Step 1: Migrate Roster Moves ---
    echo "Migrating main roster moves...\n";

    $rosterStmt      = $db->query( "SELECT wrestler_id, moves FROM roster" );
    $rosterWrestlers = $rosterStmt->fetchAll( PDO::FETCH_ASSOC );

    $movesMapStmt = $db->query( "SELECT move_name, move_id FROM all_moves" );
    $movesMap     = $movesMapStmt->fetchAll( PDO::FETCH_KEY_PAIR );

    $insertRosterMoveStmt = $db->prepare( "INSERT IGNORE INTO roster_moves (roster_wrestler_id, move_id) VALUES (?, ?)" );

    $rosterCount = 0;
    foreach ( $rosterWrestlers as $wrestler )
    {
        $movesData = json_decode( $wrestler['moves'], true );
        if ( json_last_error() !== JSON_ERROR_NONE || !is_array( $movesData ) )
        {
            echo "  - Skipping wrestler ID {$wrestler['wrestler_id']} due to invalid moves JSON.\n";
            continue;
        }

        $allMoveNames = array_merge( ...array_values( $movesData ) );

        foreach ( $allMoveNames as $moveName )
        {
            if ( isset( $movesMap[$moveName] ) )
            {
                $moveId = $movesMap[$moveName];
                $insertRosterMoveStmt->execute( [$wrestler['wrestler_id'], $moveId] );
                $rosterCount++;
            }
            else
            {
                echo "  - Warning: Move '{$moveName}' not found in all_moves table for wrestler ID {$wrestler['wrestler_id']}.\n";
            }
        }
    }
    echo "Successfully migrated {$rosterCount} main roster move assignments.\n\n";

    // --- Step 2: Migrate Prospect Moves ---
    echo "Migrating prospect moves...\n";

    $prospectStmt = $db->query( "SELECT pid, moves FROM prospects" );
    $prospects    = $prospectStmt->fetchAll( PDO::FETCH_ASSOC );

    $insertProspectMoveStmt = $db->prepare( "INSERT IGNORE INTO prospect_moves (prospect_pid, move_id) VALUES (?, ?)" );

    $prospectCount = 0;
    foreach ( $prospects as $prospect )
    {
        $movesData = json_decode( $prospect['moves'], true );
        if ( json_last_error() !== JSON_ERROR_NONE || !is_array( $movesData ) )
        {
            echo "  - Skipping prospect PID {$prospect['pid']} due to invalid moves JSON.\n";
            continue;
        }

        $allMoveNames = array_merge( ...array_values( $movesData ) );

        foreach ( $allMoveNames as $moveName )
        {
            if ( isset( $movesMap[$moveName] ) )
            {
                $moveId = $movesMap[$moveName];
                $insertProspectMoveStmt->execute( [$prospect['pid'], $moveId] );
                $prospectCount++;
            }
            else
            {
                echo "  - Warning: Move '{$moveName}' not found in all_moves table for prospect PID {$prospect['pid']}.\n";
            }
        }
    }
    echo "Successfully migrated {$prospectCount} prospect move assignments.\n\n";

    echo "=============================\n";
    echo "Migration complete!\n";
    echo "RECOMMENDATION: You can now consider dropping the 'moves' column from the 'roster' and 'prospects' tables after verifying the data.\n";

}
catch ( PDOException $e )
{
    echo "\nAn error occurred: " . $e->getMessage() . "\n";
    exit( 1 );
}
