<?php
define('ROOT_PATH', __DIR__);
define('PRIVATE_PATH', ROOT_PATH . '/private');
define('BOOTSTRAP_PATH', PRIVATE_PATH . '/bootstrap.php');

require_once BOOTSTRAP_PATH;

// Get the requested URI from the query parameter
$request_uri = isset($_GET['url']) ? $_GET['url'] : $_SERVER['REQUEST_URI'] ?? '/';
$request_uri = str_replace('.php', '', $request_uri);
$request_path = parse_url($request_uri, PHP_URL_PATH);
if (file_exists(ENV_FILE_PATH)){
    $env = parse_ini_file(ENV_FILE_PATH);
}	

switch($request_path){
    case('/login'):
        include_once LOGIN_PAGE_PATH;
        exit();

    case('/interface'):
        include_once INTERFACE_PAGE_PATH;
        exit();

    case('/logout'):
        include_once LOGOUT_PAGE_PATH;
        exit();

    case('/oidc_login'):
        include_once OIDC_LOGIN_PAGE_PATH;
        exit();

    case('/impressum'):
        $imprintLocation = isset($env) ? $env["IMPRINT_LOCATION"] : getenv("IMPRINT_LOCATION");
        header("Location: $imprintLocation");
        exit;

    case('/dataprotection'):
        $dataProtectionLocation = isset($env) ? $env["PRIVACY_LOCATION"] : getenv("PRIVACY_LOCATION");
        header("Location: $dataProtectionLocation");
        exit;
        
    case('/api/feedback_send'):
        if($_SERVER["REQUEST_METHOD"] == "POST"){
            include_once( LIBRARY_PATH . "feedback_send.php");
        }
        exit;
        
    case('/api/submit_vote'):
        if($_SERVER["REQUEST_METHOD"] == "POST"){
            include_once( LIBRARY_PATH . "submit_vote.php");
        }
        exit;

    case('/api/stream-api'):
        if($_SERVER["REQUEST_METHOD"] == "POST"){
            include_once( LIBRARY_PATH . "stream-api.php");
        }
        exit;
    case('/api/GWDG-api'):
        if($_SERVER["REQUEST_METHOD"] == "POST"){
            include_once( LIBRARY_PATH . "GWDG-stream-api.php");
        }
        exit;

    default:
        header("Location: /login");
        exit();
}