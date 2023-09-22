<?php
if (file_exists(".env")){
    $env = parse_ini_file('.env');
}	
$privacyLocation = isset($env) ? $env["PRIVACY_LOCATION"] : getenv("PRIVACY_LOCATION");

header("Location: $privacyLocation");
exit;
