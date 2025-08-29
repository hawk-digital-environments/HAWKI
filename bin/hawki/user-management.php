<?php

/**
 * User management commands
 */

function handleTokenCommand($flags) {
    if (isset($flags[0]) && $flags[0] === '--revoke') {
        passthru('php artisan app:token --revoke');
    } else {
        passthru('php artisan app:token');
    }
}

function removeUser() {
    passthru('php artisan app:removeuser');
}