<?php

/**
 * Database management commands
 */

/**
 * Run database migrations
 */
function migrate($flags) {
    echo BOLD . "Running database migrations..." . RESET . PHP_EOL;

    // Check if we need to run a fresh migration
    $fresh = in_array('--fresh', $flags);

    if ($fresh) {
        // Get database information for warning message
        $env = getEnvContent();
        $dbConnection = getEnvValue('DB_CONNECTION', $env) ?: 'mysql';
        $dbHost = getEnvValue('DB_HOST', $env) ?: 'localhost';
        $dbPort = getEnvValue('DB_PORT', $env) ?: '3306';
        $dbName = getEnvValue('DB_DATABASE', $env) ?: 'hawki';

        // Display warning about data loss
        echo RED . BOLD . "⚠️  WARNING: You are about to run a fresh migration!" . RESET . PHP_EOL;
        echo RED . "This will delete ALL data in your database:" . RESET . PHP_EOL;
        echo "  - Connection: $dbConnection" . PHP_EOL;
        echo "  - Database: $dbName" . PHP_EOL;
        echo "  - Host: $dbHost:$dbPort" . PHP_EOL . PHP_EOL;
        echo YELLOW . "Are you sure you want to continue? Type 'yes' to confirm: " . RESET;

        $confirmation = trim(fgets(STDIN));

        if (strtolower($confirmation) !== 'yes') {
            echo YELLOW . "Migration cancelled by user." . RESET . PHP_EOL;
            return;
        }

        echo YELLOW . "Running migrate:fresh..." . RESET . PHP_EOL;
        passthru('php artisan migrate:fresh --ansi');
    } else {
        echo YELLOW . "Running migrate..." . RESET . PHP_EOL;
        passthru('php artisan migrate --ansi');
    }

    // Clear cache to ensure fresh database reflection
    echo YELLOW . "Clearing cache to refresh database schema..." . RESET . PHP_EOL;
    passthru('php artisan cache:clear --ansi');

    echo GREEN . "✓ Database migration completed!" . RESET . PHP_EOL;
}
