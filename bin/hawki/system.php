<?php

/**
 * Clear all Laravel caches
 */
function clearCache() {
    echo BOLD . "Clearing Laravel caches..." . RESET . PHP_EOL;

    // Execute cache clearing commands
    echo YELLOW . "Running config:clear..." . RESET . PHP_EOL;
    passthru('php artisan config:clear --ansi');

    echo YELLOW . "Running cache:clear..." . RESET . PHP_EOL;
    passthru('php artisan cache:clear --ansi');

    echo YELLOW . "Running view:clear..." . RESET . PHP_EOL;
    passthru('php artisan view:clear --ansi');

    echo YELLOW . "Running route:clear..." . RESET . PHP_EOL;
    passthru('php artisan route:clear --ansi');

    echo YELLOW . "Running event:clear..." . RESET . PHP_EOL;
    passthru('php artisan event:clear --ansi');

    echo YELLOW . "Running compiled:clear..." . RESET . PHP_EOL;
    passthru('php artisan clear-compiled --ansi');

    echo YELLOW . "Running optimize:clear..." . RESET . PHP_EOL;
    passthru('php artisan optimize:clear --ansi');

    echo GREEN . "✓ All caches have been cleared" . RESET . PHP_EOL;
}
