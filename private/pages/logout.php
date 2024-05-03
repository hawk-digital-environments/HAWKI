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


    if (file_exists(ENV_FILE_PATH)){
        $env = parse_ini_file(ENV_FILE_PATH);
        if($env['Authentication'] === 'Shibboleth'){
            $redirect_uri = $env['SHIBBOLETH_LOGOUT_URL'];
        }
        elseif($env['Authentication'] === 'OIDC'){
            $redirect_uri = $env['OIDC_LOGOUT_URI'];
        }
        else{
            // Redirect to the login page
            $redirect_uri ='/login';
        }
    }
    header("Location: $redirect_uri");
    exit();


?>
