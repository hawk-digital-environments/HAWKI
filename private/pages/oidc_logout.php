<?php
session_start();
session_destroy();

define('BOOTSTRAP_PATH',  '../bootstrap.php');
require_once BOOTSTRAP_PATH;
if (file_exists(ENV_FILE_PATH)){
    $env = parse_ini_file(ENV_FILE_PATH);
}
$logout_uri = isset($env) ? $env['OIDC_LOGOUT_URI'] : getenv('OIDC_LOGOUT_URI');
header("Location: $logout_uri");
exit;
?>