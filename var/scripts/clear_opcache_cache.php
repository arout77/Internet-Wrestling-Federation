<?php

// Set a high error reporting level to see all issues.
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

echo "<body style='font-family: sans-serif; padding: 2em;'>";

// Check if the OPcache extension is loaded and enabled.
if ( function_exists( 'opcache_reset' ) && ini_get( 'opcache.enable' ) )
{
    // Attempt to clear the OPcache.
    if ( opcache_reset() )
    {
        echo "<h1>✅ OPcache has been successfully cleared!</h1>";
        echo "<p>Your web server will now read the latest versions of your PHP files.</p>";
        echo "<p><strong>You can now navigate back to your tournament page and try again.</strong></p>";
    }
    else
    {
        echo "<h1>❌ Error: OPcache could not be cleared.</h1>";
        echo "<p>This can sometimes happen due to server permissions or configuration settings.</p>";
    }

}
else
{
    echo "<h1>ℹ️ OPcache is not enabled on this server.</h1>";
    echo "<p>The error you are seeing is likely not related to caching. Please ensure the file `app/models/SimulatorModel.php` has been saved and the method `start_simulation` exists.</p>";
}

// Display OPcache status for detailed debugging information.
if ( function_exists( 'opcache_get_status' ) )
{
    echo "<h2>Current OPcache Status:</h2>";
    echo "<pre style='background-color: #f0f0f0; padding: 1em; border-radius: 5px;'>";
    print_r( opcache_get_status( false ) );
    echo "</pre>";
}

echo "</body>";
