<?php

/**
 * HAWKI Update System
 * Handles project updates from GitHub releases
 */

function checkVersion(bool $checkOnlineVersion = false) {
    // Step 1: Get current version
    $currentVersion = getCurrentVersion();
    if (!$currentVersion) {
        echo RED . "Error: Could not read current version from config/app.php" . RESET . PHP_EOL;
        return false;
    }

    if(!$checkOnlineVersion) {
        echo "Version: " . BOLD . $currentVersion . RESET . PHP_EOL;
        return false;
    }

    // Step 2: Check GitHub for latest release
    $latestVersion = getLatestGitHubVersion();
    if (!$latestVersion) {
        echo RED . "Error: Could not fetch latest version from GitHub" . RESET . PHP_EOL;
        return false;
    }


// Step 3: Compare versions
    $cleanLatest = ltrim($latestVersion, 'v');

    if (version_compare($currentVersion, $cleanLatest, '>=')) {
        echo GREEN . "HAWKI version " . $currentVersion . RESET . PHP_EOL;
        return true;
    }

    echo BOLD . "NEW VERSION AVAILABLE!" . RESET . PHP_EOL;
    echo "======================" . PHP_EOL . PHP_EOL;

    // Parse versions
    list($cMajor, $cMinor, $cPatch) = array_map('intval', explode('.', $currentVersion));
    list($lMajor, $lMinor, $lPatch) = array_map('intval', explode('.', $cleanLatest));

    $isMajorUpgrade = $lMajor > $cMajor;
    $isMinorUpgrade = !$isMajorUpgrade && $lMinor > $cMinor;

    $color = $isMajorUpgrade ? RED : YELLOW;

    echo $color . "Current version: " . BOLD . $currentVersion . RESET . PHP_EOL;
    echo "Latest version: " . BOLD . $latestVersion . RESET . PHP_EOL . RESET;
    echo $color . "https://github.com/hawk-digital-environments/HAWKI/releases/tag/" . $latestVersion . RESET . PHP_EOL . PHP_EOL;
    return false;
}

function getCurrentVersion() {
    $configPath = __DIR__ . '/../../config/hawki_version.json';
    if (!file_exists($configPath)) {
        return false;
    }

    $content = file_get_contents($configPath);
    $json = json_decode($content, true); // decode as associative array

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false; // or throw an exception
    }

    return $json['version'] ?? false;
}

function getLatestGitHubVersion() {
    $url = "https://api.github.com/repos/hawk-digital-environments/hawki/releases/latest";

    // Use curl to fetch data with proper headers
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HAWKI-CLI-Updater');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return false;
    }

    $data = json_decode($response, true);
    return $data['tag_name'] ?? false;
}
