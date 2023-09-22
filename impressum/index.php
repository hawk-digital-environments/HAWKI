<?php
if (file_exists(".env")){
    $env = parse_ini_file('.env');
}	
$imprintLocation = isset($env) ? $env["IMPRINT_LOCATION"] : getenv("IMPRINT_LOCATION");

header("Location: $imprintLocation");
exit;



