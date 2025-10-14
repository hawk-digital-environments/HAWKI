<?php

/**
 * User management commands
 */

function handleTokenCommand($flags) {
    if (isset($flags[0]) && $flags[0] === '--revoke') {
        passthru('php artisan app:token --revoke --ansi');
    } else {
        passthru('php artisan app:token --ansi');
    }
}

function removeUser() {
    passthru('php artisan app:removeuser --ansi');
}


function updateHawkiAvatar($path){
    passthru("php artisan hawki:update-avatar $path --ansi");
}
