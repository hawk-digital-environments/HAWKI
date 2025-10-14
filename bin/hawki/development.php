<?php

/**
 * Development server management commands
 */

function runDev() {
    echo BOLD . "Starting development servers..." . RESET . PHP_EOL;

    // Create a temporary bash script
    $script = "#!/bin/bash
                trap 'kill \$(jobs -p) 2>/dev/null' EXIT

                # Start services in parallel
                php artisan serve &
                npm run dev &
                php artisan reverb:start &
                php artisan queue:work &
                php artisan queue:work --queue=mails &
                php artisan queue:work --queue=message_broadcast &
                php artisan schedule:work &

                # Print process info
                echo \"All services started. Press Ctrl+C to stop all.\"

                # Wait for user to press Ctrl+C
                wait
                ";

    $tmpfile = tempnam(sys_get_temp_dir(), 'hawki_');
    file_put_contents($tmpfile, $script);
    chmod($tmpfile, 0755);

    // Run the script
    passthru("bash $tmpfile");

    // Clean up the temporary file
    unlink($tmpfile);
}

function runBuild() {
    echo BOLD . "Building project..." . RESET . PHP_EOL;

    // Run composer install
    echo YELLOW . "Running composer install..." . RESET . PHP_EOL;
    passthru('composer install');

    // Check if we need to update composer
    echo YELLOW . "Checking for composer updates..." . RESET . PHP_EOL;
    exec('composer outdated --direct', $outdatedOutput);
    if (!empty($outdatedOutput) && count($outdatedOutput) > 1) { // First line is header
        echo "Outdated packages found. Running composer update..." . PHP_EOL;
        passthru('composer update');
    }

    // Run npm build
    echo YELLOW . "Running npm run build..." . RESET . PHP_EOL;
    passthru('npm run build');

    // Clear all caches to ensure build is fresh
    clearCache();

    echo PHP_EOL . GREEN . BOLD . "Build completed!" . RESET . PHP_EOL;
}

function stopProcesses() {
    echo BOLD . "Stopping all HAWKI processes..." . RESET . PHP_EOL;

    // Find and kill PHP artisan processes
    echo "Finding and stopping PHP artisan processes..." . PHP_EOL;
    $script = "
    pkill -f 'php artisan serve' 2>/dev/null
    pkill -f 'php artisan queue:work' 2>/dev/null
    pkill -f 'php artisan reverb:start' 2>/dev/null
    pkill -f 'php artisan schedule:work' 2>/dev/null
    ";

    // Find and kill any Node processes related to Vite
    echo "Finding and stopping npm/node processes..." . PHP_EOL;
    $script .= "
    pkill -f 'vite' 2>/dev/null
    ";

    $tmpfile = tempnam(sys_get_temp_dir(), 'hawki_stop_');
    file_put_contents($tmpfile, $script);
    chmod($tmpfile, 0755);

    // Run the script
    passthru("bash $tmpfile");

    // Clean up the temporary file
    unlink($tmpfile);

    echo GREEN . "All HAWKI processes have been stopped." . RESET . PHP_EOL;
}
