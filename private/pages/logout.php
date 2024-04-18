<?php
    session_start();
    // Unset all session variables
    $_SESSION = array();
    // If it's desired, regenerate session ID (good practice for login but optional on logout)
    session_regenerate_id(true);

    //Remove PHPSESSID
    if (isset($_COOKIE['PHPSESSID'])){
        setcookie ("PHPSESSID", "", time() - 3600);
    }

    // Finally, destroy the session.
    session_destroy();
    // Redirect to the login page
    if ((isset($env) ? $env["Authentication"] : getenv("Authentication")) == "OIDC") {
        // Open ID Connect
        header('Location: oidc_logout');
    } else {
        header('Location: login');
    }
    exit;
?>
