<?php

// A command-line script to simulate a predefined series of matches multiple times.

// --- BOOTSTRAP ---
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/KernelApi.php';

if ( !is_object( $app ) || !( $app instanceof \Pimple\Container ) )
{
    echo "Fatal Error: The application bootstrap failed.\n";
    exit( 1 );
}

// --- MODELS ---
$simulatorModel = new \App\Model\SimulatorModel( $app );
$apiModel       = new \App\Model\ApiModel( $app );

// --- CONFIGURATION ---
define( 'SIMULATION_COUNT', 1000 );

$matches_to_simulate = [
    ['Sting', 'Lex Luger'],
    ['Sting', 'Macho Man Randy Savage'],
    ['Sting', 'Barry Windham'],
    ['Sting', 'Ric Flair'],
    ['Sting', 'Vader'],
    ['Sting', 'Hulk Hogan'],
    ['Sting', 'Randy Orton'],
    ['Sting', 'Bret Hart'],
    ['Sting', 'The Big Show'],
    // ['Rick Rude', 'Lex Luger'],
    // ['Rick Rude', 'Macho Man Randy Savage'],
    // ['Rick Rude', 'Barry Windham'],
    // ['Rick Rude', 'Ric Flair'],
    // ['Rick Rude', 'Vader'],
    // ['Rick Rude', 'Hulk Hogan'],
    // ['Rick Rude', 'Randy Orton'],
    // ['Rick Rude', 'Bret Hart'],
    // ['Rick Rude', 'The Big Show'],
    // ['Scott Hall', 'Andre the Giant'],
    // ['Scott Hall', 'King Kong Bundy'],
    // ['Scott Hall', 'Bret Hart'],
    // ['Scott Hall', 'Lex Luger'],
    // ['Scott Hall', 'Sting'],
    // ['Scott Hall', 'Kevin Nash'],
    // ['Scott Hall', 'Ricky Steamboat'],
    // ['Chris Benoit', 'Lex Luger'],
    // ['Chris Benoit', 'Ric Flair'],
    // ['Chris Benoit', 'Sting'],
    // ['Chris Benoit', 'Hulk Hogan'],
    // ['Chris Benoit', 'Ultimate Warrior'],
    // ['Chris Benoit', 'Kerry Von Erich'],
    // ['Chris Benoit', 'Andre the Giant'],
    // ['Chris Benoit', 'Ted DiBiase'],
    // ['Chris Benoit', 'Bret Hart'],
    // ['Curt Hennig', 'Curt Hennig'],
    // ['Curt Hennig', 'Lex Luger'],
    // ['Curt Hennig', 'Sting'],
    // ['Curt Hennig', 'The Big Show'],
    // ['Curt Hennig', 'Chris Benoit'],
    // ['Curt Hennig', 'The Great Muta'],
    // ['Curt Hennig', 'Ric Flair'],
    // ['Curt Hennig', 'Randy Orton'],
    // ['Curt Hennig', 'Shawn Michaels'],
];

echo "Starting Dream Match Series Simulation...\n";
echo "=============================================\n";

try {
    foreach ( $matches_to_simulate as $matchup )
    {
        $wrestler1_name = $matchup[0];
        $wrestler2_name = $matchup[1];

        echo "Simulating: {$wrestler1_name} vs. {$wrestler2_name} (" . SIMULATION_COUNT . " times)\n";

        $wrestler1 = $apiModel->getWrestlerByName( $wrestler1_name );
        $wrestler2 = $apiModel->getWrestlerByName( $wrestler2_name );

        if ( !$wrestler1 )
        {
            echo "  - ERROR: Could not find wrestler: {$wrestler1_name}\n\n";
            continue;
        }
        if ( !$wrestler2 )
        {
            echo "  - ERROR: Could not find wrestler: {$wrestler2_name}\n\n";
            continue;
        }

        $results = $simulatorModel->runBulkSimulations( $wrestler1, $wrestler2, SIMULATION_COUNT );

        if ( isset( $results['wins'] ) )
        {
            $w1_wins = $results['wins'][$wrestler1->name] ?? 0;
            $w2_wins = $results['wins'][$wrestler2->name] ?? 0;
            $draws   = $results['wins']['draw'] ?? 0;

            echo "  - Results: {$wrestler1->name} Wins: {$w1_wins} | {$wrestler2->name} Wins: {$w2_wins} | Draws: {$draws}\n\n";
        }
        else
        {
            echo "  - ERROR: Simulation did not return expected results.\n\n";
        }
    }

    echo "=============================================\n";
    echo "All simulations complete.\n";

}
catch ( Exception $e )
{
    echo "\nAn unexpected error occurred: " . $e->getMessage() . "\n";
}
