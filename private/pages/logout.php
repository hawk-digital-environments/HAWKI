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
    }

    $authentication = isset($env) ? $env["Authentication"] : getenv("Authentication");
    if($authentication === 'Shibboleth'){
        $redirect_uri = isset($env) ? $env["SHIBBOLETH_LOGOUT_URL"] : getenv("SHIBBOLETH_LOGOUT_URL");
    }
    elseif($authentication === 'OIDC'){
        $redirect_uri = isset($env) ? $env["OIDC_LOGOUT_URI"] : getenv("OIDC_LOGOUT_URI");
    }
    else{
        // Redirect to the login page
        $redirect_uri ='login';
    }

    header("Location: $redirect_uri");
    exit();

?>
