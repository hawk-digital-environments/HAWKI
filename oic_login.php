<?php

// use library for dealing with OpenID connect
$env = parse_ini_file('.env');
$composerpath = $env["COMPOSER_PATH"];

require($composerpath . '/vendor/autoload.php');

use Jumbojett\OpenIDConnectClient;

// Create OpenID connect client
$env = parse_ini_file('.env');

$oidc = new OpenIDConnectClient(
    $env["OIC_IDP"],
    $env["OIC_CLIENT_ID"],
    $env["OIC_CLIENT_SECRET"]
);

# Demo is dealing with HTTP rather than HTTPS
$testuser = $env["TESTUSER"];
if ($testuser) {
    $oidc->setHttpUpgradeInsecureRequests(false);
}

$oidc->authenticate();

$_SESSION['oidcClient'] = $oidc;

// Set session variable username
$firstname = $oidc->requestUserInfo('given_name');
$surname = $oidc->requestUserInfo('family_name');
$initials = substr($firstname, 0, 1) . substr($surname, 0, 1);

$_SESSION['username'] = $initials;


header("Location: interface.php");
exit();

?>

