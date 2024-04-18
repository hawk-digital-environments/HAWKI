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
    header('Location: login');
    exit;
?>
