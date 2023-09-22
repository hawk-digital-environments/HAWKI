<?php
session_start();
session_destroy();
if (file_exists(".env")){
    $env = parse_ini_file('.env');
}
$logout_uri = isset($env) ? $env['OIDC_LOGOUT_URI'] : getenv('OIDC_LOGOUT_URI');
header("Location: $logout_uri");
exit;
?>