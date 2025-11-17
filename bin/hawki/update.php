<?php

/**
 * HAWKI Update System
 * Handles project updates from GitHub releases
 */

function updateSystem() {
    echo BOLD . "HAWKI Update System" . RESET . PHP_EOL;
    echo "===================" . PHP_EOL . PHP_EOL;
    
    // Step 1: Get current version
    $currentVersion = getCurrentVersion();
    if (!$currentVersion) {
        echo RED . "Error: Could not read current version from config/app.php" . RESET . PHP_EOL;
        return false;
    }
    
    echo "Current version: " . BOLD . $currentVersion . RESET . PHP_EOL;
    
    // Step 2: Check GitHub for latest release
    echo "Checking GitHub for latest release..." . PHP_EOL;
    $latestVersion = getLatestGitHubVersion();
    if (!$latestVersion) {
        echo RED . "Error: Could not fetch latest version from GitHub" . RESET . PHP_EOL;
        return false;
    }
    
    echo "Latest version: " . BOLD . $latestVersion . RESET . PHP_EOL . PHP_EOL;
    
    // Step 3: Compare versions
    if (version_compare($currentVersion, ltrim($latestVersion, 'v'), '>=')) {
        echo GREEN . "HAWKI version is up to date." . RESET . PHP_EOL;
        return true;
    }
    
    // Step 4: Prompt for update
    echo YELLOW . "New HAWKI version found! Do you want to update? (y/n): " . RESET;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Update cancelled." . PHP_EOL;
        return true;
    }
    
    // Step 5: Perform update
    echo PHP_EOL . BOLD . "Starting update process..." . RESET . PHP_EOL;
    return performUpdate($latestVersion);
}

function getCurrentVersion() {
    $configPath = __DIR__ . '/../../config/app.php';
    if (!file_exists($configPath)) {
        return false;
    }
    
    $content = file_get_contents($configPath);
    if (preg_match("/'version'\s*=>\s*[\"']([^\"']+)[\"']/", $content, $matches)) {
        return $matches[1];
    }
    
    return false;
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

function performUpdate($latestVersion) {
    $tempDir = __DIR__ . '/../../_temp';
    $backupNeeded = true;
    
    try {
        // Step 1: Create temp directory
        echo "Creating temporary directory..." . PHP_EOL;
        if (!createTempDirectory($tempDir)) {
            throw new Exception("Failed to create temporary directory");
        }
        
        // Step 2: Download release
        echo "Downloading release $latestVersion..." . PHP_EOL;
        $zipFile = downloadRelease($latestVersion, $tempDir);
        if (!$zipFile) {
            throw new Exception("Failed to download release");
        }
        
        // Step 3: Extract release
        echo "Extracting release..." . PHP_EOL;
        $extractDir = extractRelease($zipFile, $tempDir);
        if (!$extractDir) {
            throw new Exception("Failed to extract release");
        }
        
        // Step 4: Backup current installation
        echo "Creating backup..." . PHP_EOL;
        if (!createBackup()) {
            throw new Exception("Failed to create backup");
        }
        
        // Step 5: Replace files (except protected ones)
        echo "Replacing files..." . PHP_EOL;
        if (!replaceFiles($extractDir)) {
            throw new Exception("Failed to replace files");
        }
        
        // Step 6: Run migrations and updates
        echo "Running migrations..." . PHP_EOL;
        if (!runMigrations()) {
            throw new Exception("Migrations failed");
        }
        
        // Step 7: Update dependencies
        echo "Updating dependencies..." . PHP_EOL;
        if (!updateDependencies()) {
            throw new Exception("Dependency update failed");
        }
        
        // Step 8: Update .env file
        echo "Checking .env file for new settings..." . PHP_EOL;
        updateEnvFile($extractDir);
        
        // Step 9: Replace HAWKI CLI files
        echo "Updating HAWKI CLI..." . PHP_EOL;
        if (!updateHawkiCLI($extractDir)) {
            echo YELLOW . "Warning: Could not update HAWKI CLI files" . RESET . PHP_EOL;
        }
        
        // Step 10: Cleanup
        echo "Cleaning up..." . PHP_EOL;
        cleanupTempDirectory($tempDir);
        
        echo PHP_EOL . GREEN . "Update completed successfully!" . RESET . PHP_EOL;
        echo "HAWKI has been updated to version " . BOLD . $latestVersion . RESET . PHP_EOL;
        
        return true;
        
    } catch (Exception $e) {
        echo PHP_EOL . RED . "Update failed: " . $e->getMessage() . RESET . PHP_EOL;
        
        // Cleanup on failure
        if (is_dir($tempDir)) {
            cleanupTempDirectory($tempDir);
        }
        
        return false;
    }
}

function createTempDirectory($tempDir) {
    if (is_dir($tempDir)) {
        // Remove existing temp directory
        exec("rm -rf " . escapeshellarg($tempDir));
    }
    
    return mkdir($tempDir, 0755, true);
}

function downloadRelease($version, $tempDir) {
    $url = "https://github.com/hawk-digital-environments/hawki/archive/refs/tags/$version.zip";
    $zipFile = $tempDir . "/hawki-$version.zip";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
    curl_setopt($ch, CURLOPT_USERAGENT, 'HAWKI-CLI-Updater');
    
    // Progress callback
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) {
        if ($downloadSize > 0) {
            $percent = round(($downloaded / $downloadSize) * 100);
            echo "\rDownload progress: $percent%";
        }
    });
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo PHP_EOL; // New line after progress
    
    if ($httpCode !== 200 || !$data) {
        return false;
    }
    
    if (file_put_contents($zipFile, $data) === false) {
        return false;
    }
    
    return $zipFile;
}

function extractRelease($zipFile, $tempDir) {
    $zip = new ZipArchive;
    if ($zip->open($zipFile) !== TRUE) {
        return false;
    }
    
    $extractPath = $tempDir . '/extracted';
    if (!$zip->extractTo($extractPath)) {
        $zip->close();
        return false;
    }
    
    $zip->close();
    
    // Find the extracted directory (usually hawki-{version})
    $items = scandir($extractPath);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($extractPath . '/' . $item)) {
            return $extractPath . '/' . $item;
        }
    }
    
    return false;
}

function createBackup() {
    // Use Laravel's backup command
    $output = [];
    $returnCode = 0;
    
    exec('cd ' . escapeshellarg(__DIR__ . '/../..') . ' && php artisan backup:run', $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo YELLOW . "Warning: Backup command failed, but continuing with update..." . RESET . PHP_EOL;
        return true; // Continue even if backup fails
    }
    
    // Wait a moment for backup to complete
    sleep(2);
    
    return true;
}

function replaceFiles($sourceDir) {
    $projectRoot = __DIR__ . '/../..';
    
    // Protected files and directories that should not be replaced
    $protectedItems = [
        'hawki',
        'storage',
        '.env',
        '.env.local',
        '.env.production',
        'node_modules',
        'vendor',
        '_temp',
        '.git',
        '_docker_production/storage'
    ];
    
    // Get list of items to copy
    $items = scandir($sourceDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        // Skip protected items
        if (in_array($item, $protectedItems)) {
            echo "  Skipping protected item: $item" . PHP_EOL;
            continue;
        }
        
        $sourcePath = $sourceDir . '/' . $item;
        $destPath = $projectRoot . '/' . $item;
        
        echo "  Replacing: $item" . PHP_EOL;
        
        // Remove existing item
        if (file_exists($destPath)) {
            if (is_dir($destPath)) {
                exec('rm -rf ' . escapeshellarg($destPath));
            } else {
                unlink($destPath);
            }
        }
        
        // Copy new item
        if (is_dir($sourcePath)) {
            exec('cp -R ' . escapeshellarg($sourcePath) . ' ' . escapeshellarg($destPath));
        } else {
            copy($sourcePath, $destPath);
        }
    }
    
    return true;
}

function runMigrations() {
    $projectRoot = __DIR__ . '/../..';
    $commands = [
        'php artisan migrate:avatars',
        'php artisan migrate --force'
    ];
    
    foreach ($commands as $command) {
        echo "  Running: $command" . PHP_EOL;
        $output = [];
        $returnCode = 0;
        
        exec('cd ' . escapeshellarg($projectRoot) . ' && ' . $command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            echo RED . "  Failed: $command" . RESET . PHP_EOL;
            foreach ($output as $line) {
                echo "    $line" . PHP_EOL;
            }
            return false;
        }
    }
    
    return true;
}

function updateDependencies() {
    $projectRoot = __DIR__ . '/../..';
    $commands = [
        'composer install --no-dev --optimize-autoloader',
        'npm install'
    ];
    
    foreach ($commands as $command) {
        echo "  Running: $command" . PHP_EOL;
        $output = [];
        $returnCode = 0;
        
        exec('cd ' . escapeshellarg($projectRoot) . ' && ' . $command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            echo YELLOW . "  Warning: $command returned code $returnCode" . RESET . PHP_EOL;
            // Continue even if these fail
        }
    }
    
    return true;
}

function updateEnvFile($sourceDir) {
    $projectRoot = __DIR__ . '/../..';
    $currentEnvPath = $projectRoot . '/.env';
    $newEnvExamplePath = $sourceDir . '/.env.example';
    
    if (!file_exists($currentEnvPath) || !file_exists($newEnvExamplePath)) {
        echo "  .env files not found, skipping..." . PHP_EOL;
        return;
    }
    
    // Parse both files
    $currentEnv = parseEnvFile($currentEnvPath);
    $newEnvExample = parseEnvFile($newEnvExamplePath);
    
    $newKeys = [];
    $envContent = file_get_contents($currentEnvPath);
    
    foreach ($newEnvExample as $key => $value) {
        if (!array_key_exists($key, $currentEnv)) {
            $newKeys[] = $key;
            $envContent .= PHP_EOL . "$key=$value";
        }
    }
    
    if (!empty($newKeys)) {
        file_put_contents($currentEnvPath, $envContent);
        
        echo "  Added new .env variables:" . PHP_EOL;
        foreach ($newKeys as $key) {
            echo "    " . GREEN . "$key" . RESET . PHP_EOL;
        }
    } else {
        echo "  No new .env variables found." . PHP_EOL;
    }
}

function parseEnvFile($filepath) {
    $env = [];
    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $env[$key] = $value;
        }
    }
    
    return $env;
}

function updateHawkiCLI($sourceDir) {
    $projectRoot = __DIR__ . '/../..';
    $sourceHawki = $sourceDir . '/hawki';
    $sourceBinDir = $sourceDir . '/bin';
    $destHawki = $projectRoot . '/hawki';
    $destBinDir = $projectRoot . '/bin';
    
    try {
        // Update main hawki file
        if (file_exists($sourceHawki)) {
            copy($sourceHawki, $destHawki);
            chmod($destHawki, 0755);
            echo "  Updated hawki CLI script" . PHP_EOL;
        }
        
        // Update bin directory (but preserve this update.php file)
        if (is_dir($sourceBinDir)) {
            // Copy all files except update.php
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceBinDir),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($items as $item) {
                $sourcePath = $item->getRealPath();
                $relativePath = substr($sourcePath, strlen($sourceBinDir));
                $destPath = $destBinDir . $relativePath;
                
                // Skip update.php to preserve our new command
                if (basename($sourcePath) === 'update.php') {
                    continue;
                }
                
                if ($item->isDir()) {
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0755, true);
                    }
                } else {
                    copy($sourcePath, $destPath);
                }
            }
            echo "  Updated bin directory" . PHP_EOL;
        }
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

function cleanupTempDirectory($tempDir) {
    if (is_dir($tempDir)) {
        exec('rm -rf ' . escapeshellarg($tempDir));
    }
}