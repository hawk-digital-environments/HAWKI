<?php

/**
 * System Health and dependency management
 * !!! BETA VERSION !!!
 */

function checkHealth(){
    $issues = [];

    echo PHP_EOL . BOLD . BLUE . "╔════════════════════════════════════════════════════════════════╗" . RESET . PHP_EOL;
    echo BOLD . BLUE . "║           HAWKI SYSTEM HEALTH CHECK                            ║" . RESET . PHP_EOL;
    echo BOLD . BLUE . "╚════════════════════════════════════════════════════════════════╝" . RESET . PHP_EOL . PHP_EOL;

    // Check Folder Permissions
    echo BOLD . "Step 0: Checking Laravel Folder Permissions..." . RESET . PHP_EOL;
    $permIssues = checkFolderPermissions();
    $issues = array_merge($issues, $permIssues);

    // Check Dependencies
    echo PHP_EOL . BOLD . "Step 1: Checking System Dependencies..." . RESET . PHP_EOL;
    $depIssues = checkDependencies();
    $issues = array_merge($issues, $depIssues);

    // Check Web Server
    echo PHP_EOL . BOLD . "Step 2: Checking Web Server..." . RESET . PHP_EOL;
    $webIssues = checkWebServer();
    $issues = array_merge($issues, $webIssues);

    // Check Model Providers
    echo PHP_EOL . BOLD . "Step 3: Checking AI Model Providers..." . RESET . PHP_EOL;
    $modelIssues = checkModelProviders();
    $issues = array_merge($issues, $modelIssues);

    // Check File Converter
    echo PHP_EOL . BOLD . "Step 4: Checking File Converter..." . RESET . PHP_EOL;
    $converterIssues = checkFileConverter();
    $issues = array_merge($issues, $converterIssues);

    // Check Storage Services
    echo PHP_EOL . BOLD . "Step 5: Checking Storage Services..." . RESET . PHP_EOL;
    $storageIssues = checkStorageServices();
    $issues = array_merge($issues, $storageIssues);

    // Check Broadcasting and Queue
    echo PHP_EOL . BOLD . "Step 6: Checking Broadcasting and Queue Workers..." . RESET . PHP_EOL;
    $queueIssues = checkBroadcastingAndQueue();
    $issues = array_merge($issues, $queueIssues);

    // Generate Final Report
    generateHealthReport($issues);
}

// Check folder permissions for Laravel (bootstrap and storage)
function checkFolderPermissions() {
    $issues = [];
    echo BOLD . "Checking Laravel folder permissions..." . RESET . PHP_EOL;

    $foldersToCheck = [
        'bootstrap/cache' => ['read' => true, 'write' => true, 'execute' => true],
        'storage' => ['read' => true, 'write' => true, 'execute' => true],
        'storage/app' => ['read' => true, 'write' => true, 'execute' => true],
        'storage/framework' => ['read' => true, 'write' => true, 'execute' => true],
        'storage/logs' => ['read' => true, 'write' => true, 'execute' => true],
    ];

    foreach ($foldersToCheck as $folder => $permissions) {
        if (!file_exists($folder)) {
            echo RED . "✗ Folder does not exist: $folder" . RESET . PHP_EOL;
            $issues[] = ['type' => 'critical', 'message' => "Required folder does not exist: $folder"];
            continue;
        }

        $readable = is_readable($folder);
        $writable = is_writable($folder);
        $executable = is_executable($folder);

        $hasAllPermissions = true;
        $missingPerms = [];

        if ($permissions['read'] && !$readable) {
            $hasAllPermissions = false;
            $missingPerms[] = 'read';
        }
        if ($permissions['write'] && !$writable) {
            $hasAllPermissions = false;
            $missingPerms[] = 'write';
        }
        if ($permissions['execute'] && !$executable) {
            $hasAllPermissions = false;
            $missingPerms[] = 'execute';
        }

        if ($hasAllPermissions) {
            echo GREEN . "✓ Folder permissions OK: $folder" . RESET . PHP_EOL;
        } else {
            $missingPermsStr = implode(', ', $missingPerms);
            echo RED . "✗ Folder missing permissions ($missingPermsStr): $folder" . RESET . PHP_EOL;
            $issues[] = ['type' => 'critical', 'message' => "Folder '$folder' missing permissions: $missingPermsStr"];

            // Display current permissions
            $perms = fileperms($folder);
            $octalPerms = substr(sprintf('%o', $perms), -4);
            echo YELLOW . "  Current permissions: $octalPerms" . RESET . PHP_EOL;
            echo YELLOW . "  Fix with: sudo chmod -R 775 $folder && sudo chown -R \$USER:www-data $folder" . RESET . PHP_EOL;
        }
    }

    if (empty($issues)) {
        echo GREEN . "All Laravel folder permissions are correct!" . RESET . PHP_EOL;
    }

    return $issues;
}

// Check php, composer, npm, SSL, and database migration
function checkDependencies() {
    $issues = [];
    $missingDeps = [];
    echo BOLD . "Checking dependencies..." . RESET . PHP_EOL;

    // Check PHP version
    $phpVersion = phpversion();
    $phpRequired = '8.1.0';
    if (version_compare($phpVersion, $phpRequired, '>=')) {
        echo GREEN . "✓ PHP Version: $phpVersion" . RESET . PHP_EOL;
    } else {
        echo RED . "✗ PHP Version: $phpVersion (required: >= $phpRequired)" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "PHP version $phpVersion is below required $phpRequired"];
    }

    // Check Composer
    exec('composer --version 2>/dev/null', $composerOutput, $composerReturnVar);
    if ($composerReturnVar === 0) {
        echo GREEN . "✓ Composer: " . $composerOutput[0] . RESET . PHP_EOL;
    } else {
        echo RED . "✗ Composer not found" . RESET . PHP_EOL;
        $missingDeps[] = 'composer';
        $issues[] = ['type' => 'critical', 'message' => 'Composer is not installed'];
    }

    // Check Node.js
    exec('node --version 2>/dev/null', $nodeOutput, $nodeReturnVar);
    if ($nodeReturnVar === 0) {
        $nodeVersion = trim($nodeOutput[0]);
        echo GREEN . "✓ Node.js: $nodeVersion" . RESET . PHP_EOL;
    } else {
        echo RED . "✗ Node.js not found" . RESET . PHP_EOL;
        $missingDeps[] = 'nodejs';
        $issues[] = ['type' => 'critical', 'message' => 'Node.js is not installed'];
    }

    // Check npm
    exec('npm --version 2>/dev/null', $npmOutput, $npmReturnVar);
    if ($npmReturnVar === 0) {
        $npmVersion = trim($npmOutput[0]);
        echo GREEN . "✓ npm: $npmVersion" . RESET . PHP_EOL;
    } else {
        echo RED . "✗ npm not found" . RESET . PHP_EOL;
        $missingDeps[] = 'npm';
        $issues[] = ['type' => 'critical', 'message' => 'npm is not installed'];
    }

    // Check PHP extensions
    $requiredExtensions = ['mbstring', 'xml', 'pdo', 'curl', 'zip', 'json', 'fileinfo', 'openssl'];
    $missingExtensions = [];

    foreach ($requiredExtensions as $extension) {
        if (extension_loaded($extension)) {
            echo GREEN . "✓ PHP extension: $extension" . RESET . PHP_EOL;
        } else {
            echo RED . "✗ PHP extension: $extension (missing)" . RESET . PHP_EOL;
            $missingExtensions[] = $extension;
            $issues[] = ['type' => 'critical', 'message' => "PHP extension '$extension' is missing"];
        }
    }

    // Check SSL Certificate Configuration (skip for localhost)
    echo BOLD . "Checking SSL Certificate Configuration..." . RESET . PHP_EOL;
    $envContent = file_exists('.env') ? file_get_contents('.env') : '';
    $appUrl = getEnvValue('APP_URL', $envContent);

    // Skip SSL check for localhost
    $isLocalhost = (stripos($appUrl, 'localhost') !== false || stripos($appUrl, '127.0.0.1') !== false);

    if ($isLocalhost) {
        echo YELLOW . "⚠ Skipping SSL check for localhost environment" . RESET . PHP_EOL;
    } else {
        $sslCert = getEnvValue('SSL_CERTIFICATE', $envContent);
        $sslKey = getEnvValue('SSL_CERTIFICATE_KEY', $envContent);

        if (!empty($sslCert) && !empty($sslKey)) {
            if (file_exists($sslCert)) {
                echo GREEN . "✓ SSL Certificate file exists: $sslCert" . RESET . PHP_EOL;
            } else {
                echo RED . "✗ SSL Certificate file not found: $sslCert" . RESET . PHP_EOL;
                $issues[] = ['type' => 'warning', 'message' => "SSL Certificate file not found at: $sslCert"];
            }

            if (file_exists($sslKey)) {
                echo GREEN . "✓ SSL Certificate Key file exists: $sslKey" . RESET . PHP_EOL;
            } else {
                echo RED . "✗ SSL Certificate Key file not found: $sslKey" . RESET . PHP_EOL;
                $issues[] = ['type' => 'warning', 'message' => "SSL Certificate Key file not found at: $sslKey"];
            }
        } else {
            echo YELLOW . "⚠ SSL Certificate not configured in .env" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => 'SSL Certificate not configured (recommended for production)'];
        }
    }

    // Check Database Migration Status
    echo BOLD . "Checking Database Migration Status..." . RESET . PHP_EOL;
    exec('php artisan migrate:status 2>&1', $migrateOutput, $migrateReturnVar);

    if ($migrateReturnVar === 0) {
        $hasPendingMigrations = false;
        foreach ($migrateOutput as $line) {
            if (strpos($line, 'Pending') !== false) {
                $hasPendingMigrations = true;
                break;
            }
        }

        if ($hasPendingMigrations) {
            echo YELLOW . "⚠ Database has pending migrations" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => 'Database has pending migrations. Run: php artisan migrate'];
        } else {
            echo GREEN . "✓ Database migrations are up to date" . RESET . PHP_EOL;
        }
    } else {
        echo RED . "✗ Cannot check database migration status. Database might not be configured properly." . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => 'Cannot connect to database or check migration status'];
    }

    return $issues;
}

// Check if web server is responding correctly
function checkWebServer() {
    $issues = [];
    echo BOLD . "Checking web server..." . RESET . PHP_EOL;

    $envContent = file_exists('.env') ? file_get_contents('.env') : '';
    $appUrl = getEnvValue('APP_URL', $envContent);

    if (empty($appUrl)) {
        echo RED . "✗ APP_URL not set in .env file" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => 'APP_URL is not configured in .env file'];
        return $issues;
    }

    echo "Testing connection to: " . $appUrl . PHP_EOL;

    $ch = curl_init($appUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo RED . "✗ Cannot connect to web server: $curlError" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "Cannot connect to web server at $appUrl: $curlError"];
        echo YELLOW . "  Make sure the server is running. Try: php artisan serve" . RESET . PHP_EOL;
        return $issues;
    }

    if ($httpCode == 200) {
        // Check for specific Laravel error page indicators (not just any occurrence of "error")
        $isLaravelError = false;

        // Check for Laravel's Whoops error page
        if (stripos($response, 'Whoops, looks like something went wrong') !== false) {
            $isLaravelError = true;
        }
        // Check for Laravel's Ignition error page
        if (stripos($response, '"ignition:') !== false || stripos($response, 'data-ignition') !== false) {
            $isLaravelError = true;
        }
        // Check for generic Laravel error page title
        if (preg_match('/<title>.*?(Server Error|500|Error 500).*?<\/title>/i', $response)) {
            $isLaravelError = true;
        }

        if ($isLaravelError) {
            echo YELLOW . "⚠ Server responded with HTTP 200 but displays a Laravel error page" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => "Web server at $appUrl returns a Laravel error page"];
        } else {
            echo GREEN . "✓ Web server is responding correctly (HTTP $httpCode)" . RESET . PHP_EOL;
        }
    } elseif ($httpCode == 404) {
        echo RED . "✗ Server returned 404 Not Found" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "Web server at $appUrl returns 404 Not Found"];
    } elseif ($httpCode >= 500) {
        echo RED . "✗ Server returned HTTP $httpCode (Server Error)" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "Web server at $appUrl returns HTTP $httpCode (Server Error)"];
    } else {
        echo YELLOW . "⚠ Server returned unexpected HTTP code: $httpCode" . RESET . PHP_EOL;
        $issues[] = ['type' => 'warning', 'message' => "Web server at $appUrl returned HTTP $httpCode"];
    }

    return $issues;
}

// Check Model Providers and API Keys
function checkModelProviders() {
    $issues = [];
    echo BOLD . "Checking AI Model Providers..." . RESET . PHP_EOL;

    $envContent = file_exists('.env') ? file_get_contents('.env') : '';

    // Parse the actual config file to get fallback values
    $configFile = 'config/model_providers.php';
    $configFallbacks = [];

    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);

        // Extract fallback values from env() calls in the config
        // Pattern: 'providerName' => [ ... 'active' => env('VAR_NAME', fallbackValue)
        $patterns = [
            'openAi' => "/openAi.*?'active'\s*=>\s*env\s*\(\s*['\"]OPENAI_ACTIVE['\"]\s*,\s*(true|false)\s*\)/s",
            'gwdg' => "/gwdg.*?'active'\s*=>\s*env\s*\(\s*['\"]GWDG_ACTIVE['\"]\s*,\s*(true|false)\s*\)/s",
            'google' => "/google.*?'active'\s*=>\s*env\s*\(\s*['\"]GOOGLE_ACTIVE['\"]\s*,\s*(true|false)\s*\)/s",
            'ollama' => "/ollama.*?'active'\s*=>\s*env\s*\(\s*['\"]OLLAMA_ACTIVE['\"]\s*,\s*(true|false)\s*\)/s",
            'openWebUi' => "/openWebUi.*?'active'\s*=>\s*env\s*\(\s*['\"]OPEN_WEB_UI_ACTIVE['\"]\s*,\s*(true|false)\s*\)/s",
        ];

        foreach ($patterns as $provider => $pattern) {
            if (preg_match($pattern, $configContent, $matches)) {
                $configFallbacks[$provider] = ($matches[1] === 'true');
            }
        }
    }

    // Debug: Show detected config fallbacks
    if (!empty($configFallbacks)) {
        echo YELLOW . "Config fallbacks detected: " . RESET;
        $fallbackStrs = [];
        foreach ($configFallbacks as $provider => $value) {
            $fallbackStrs[] = "$provider=" . ($value ? 'true' : 'false');
        }
        echo implode(', ', $fallbackStrs) . PHP_EOL . PHP_EOL;
    }

    // Helper function to determine provider active status with fallback
    $isProviderActive = function($envVarName, $defaultValue) use ($envContent) {
        $envValue = getEnvValue($envVarName, $envContent);

        // If env value is explicitly set
        if ($envValue !== '') {
            return strtolower($envValue) === 'true';
        }

        // Otherwise use the fallback from config
        return $defaultValue;
    };

    // Manually load config values since we're not in Laravel context
    // Parse model_providers.php manually with proper fallback values from config
    $providers = [
        'openAi' => [
            'active' => $isProviderActive('OPENAI_ACTIVE', $configFallbacks['openAi'] ?? true),
            'api_key' => getEnvValue('OPENAI_API_KEY', $envContent),
            'fallback' => $configFallbacks['openAi'] ?? true,
        ],
        'gwdg' => [
            'active' => $isProviderActive('GWDG_ACTIVE', $configFallbacks['gwdg'] ?? true),
            'api_key' => getEnvValue('GWDG_API_KEY', $envContent),
            'fallback' => $configFallbacks['gwdg'] ?? true,
        ],
        'google' => [
            'active' => $isProviderActive('GOOGLE_ACTIVE', $configFallbacks['google'] ?? false),
            'api_key' => getEnvValue('GOOGLE_API_KEY', $envContent),
            'fallback' => $configFallbacks['google'] ?? false,
        ],
        'ollama' => [
            'active' => $isProviderActive('OLLAMA_ACTIVE', $configFallbacks['ollama'] ?? false),
            'api_key' => null, // Ollama doesn't need API key
            'fallback' => $configFallbacks['ollama'] ?? false,
        ],
        'openWebUi' => [
            'active' => $isProviderActive('OPEN_WEB_UI_ACTIVE', $configFallbacks['openWebUi'] ?? false),
            'api_key' => getEnvValue('OPEN_WEB_UI_API_KEY', $envContent),
            'fallback' => $configFallbacks['openWebUi'] ?? false,
        ],
    ];

    $defaultModels = [
        'default_model' => getEnvValue('DEFAULT_MODEL', $envContent) ?: 'gpt-4.1-nano',
        'default_web_search_model' => getEnvValue('DEFAULT_WEBSEARCH_MODEL', $envContent) ?: 'gemini-2.0-flash',
        'default_file_upload_model' => getEnvValue('DEFAULT_FILEUPLOAD_MODEL', $envContent) ?: 'meta-llama-3.1-8b-instruct',
        'default_vision_model' => getEnvValue('DEFAULT_VISION_MODEL', $envContent) ?: 'qwen2.5-vl-72b-instruct',
    ];

    echo BOLD . "Checking provider API keys..." . RESET . PHP_EOL;

    foreach ($providers as $providerName => $config) {
        $isActive = $config['active'] ?? false;
        $apiKey = $config['api_key'] ?? null;
        $fallbackActive = $config['fallback'] ?? false;

        // Determine the API key environment variable name
        $apiKeyEnvVar = null;
        $activeEnvVar = null;

        if ($providerName === 'openAi') {
            $apiKeyEnvVar = 'OPENAI_API_KEY';
            $activeEnvVar = 'OPENAI_ACTIVE';
        } elseif ($providerName === 'gwdg') {
            $apiKeyEnvVar = 'GWDG_API_KEY';
            $activeEnvVar = 'GWDG_ACTIVE';
        } elseif ($providerName === 'google') {
            $apiKeyEnvVar = 'GOOGLE_API_KEY';
            $activeEnvVar = 'GOOGLE_ACTIVE';
        } elseif ($providerName === 'openWebUi') {
            $apiKeyEnvVar = 'OPEN_WEB_UI_API_KEY';
            $activeEnvVar = 'OPEN_WEB_UI_ACTIVE';
        } elseif ($providerName === 'ollama') {
            $apiKeyEnvVar = null; // Ollama doesn't require an API key
            $activeEnvVar = 'OLLAMA_ACTIVE';
        }

        echo "  Provider: $providerName" . PHP_EOL;

        // Show active status with fallback info
        $envActiveValue = getEnvValue($activeEnvVar, $envContent);
        if ($envActiveValue !== '') {
            echo "    Status: " . ($isActive ? GREEN . "active" : RED . "deactivated") . RESET . " (set in .env)" . PHP_EOL;
        } else {
            echo "    Status: " . ($isActive ? GREEN . "active" : YELLOW . "deactivated") . RESET . " (using config fallback: " . ($fallbackActive ? "true" : "false") . ")" . PHP_EOL;
        }

        if ($isActive) {
            if ($apiKeyEnvVar === null) {
                // Provider doesn't need an API key (like Ollama)
                echo GREEN . "    ✓ No API key required" . RESET . PHP_EOL;
            } elseif (!empty($apiKey)) {
                echo GREEN . "    ✓ API key is set" . RESET . PHP_EOL;
            } else {
                echo RED . "    ✗ API key ($apiKeyEnvVar) is MISSING!" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => "Provider '$providerName' is active but API key ($apiKeyEnvVar) is not set"];
                echo YELLOW . "      Action: Either set $apiKeyEnvVar in .env or set $activeEnvVar=false" . RESET . PHP_EOL;
            }
        }
    }

    echo PHP_EOL . BOLD . "Checking default models configuration..." . RESET . PHP_EOL;

    // Common model prefixes/patterns to identify provider
    $modelToProviderMap = [
        'gpt-' => 'openAi',
        'o1-' => 'openAi',
        'o3-' => 'openAi',
        'gemini-' => 'google',
        'meta-llama' => 'gwdg',
        'qwen' => 'gwdg',
        'mistral' => 'gwdg',
        'phi-' => 'gwdg',
    ];

    // Function to detect provider from model ID
    $detectProvider = function($modelId) use ($modelToProviderMap) {
        foreach ($modelToProviderMap as $pattern => $provider) {
            if (stripos($modelId, $pattern) !== false) {
                return $provider;
            }
        }
        return null; // Unknown provider
    };

    // Check each default model
    foreach ($defaultModels as $modelType => $modelId) {
        echo "  $modelType: $modelId" . PHP_EOL;

        if (empty($modelId)) {
            echo RED . "    ✗ Default model not set!" . RESET . PHP_EOL;
            $issues[] = ['type' => 'critical', 'message' => "Default model for '$modelType' is not set"];
            continue;
        }

        // Detect which provider this model belongs to
        $detectedProvider = $detectProvider($modelId);

        if ($detectedProvider === null) {
            echo YELLOW . "    ⚠ Cannot determine provider for model '$modelId'" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => "Cannot determine provider for default model '$modelId'"];
            continue;
        }

        $providerConfig = $providers[$detectedProvider];
        $isProviderActive = $providerConfig['active'];
        $apiKey = $providerConfig['api_key'];
        $needsApiKey = !in_array($detectedProvider, ['ollama']);

        echo "    Provider: $detectedProvider" . PHP_EOL;

        // Check if provider is active
        if (!$isProviderActive) {
            echo RED . "    ✗ Provider '$detectedProvider' is DEACTIVATED!" . RESET . PHP_EOL;
            $issues[] = ['type' => 'critical', 'message' => "Default model '$modelId' belongs to deactivated provider '$detectedProvider'"];
            continue;
        }

        // Check if provider has API key (if needed)
        if ($needsApiKey && empty($apiKey)) {
            echo RED . "    ✗ Provider '$detectedProvider' is active but API KEY IS MISSING!" . RESET . PHP_EOL;
            $issues[] = ['type' => 'critical', 'message' => "Default model '$modelId' belongs to provider '$detectedProvider' which has no API key"];
            continue;
        }

        // All checks passed
        echo GREEN . "    ✓ Model is properly configured (provider: $detectedProvider, active: yes, API key: " . ($needsApiKey ? 'present' : 'not required') . ")" . RESET . PHP_EOL;
    }

    // Check if at least one provider is active with valid API key
    $hasActiveProvider = false;
    foreach ($providers as $providerName => $config) {
        $isActive = $config['active'] ?? false;
        $apiKey = $config['api_key'] ?? null;
        $needsApiKey = !in_array($providerName, ['ollama']);

        if ($isActive && (!$needsApiKey || !empty($apiKey))) {
            $hasActiveProvider = true;
            break;
        }
    }

    if (!$hasActiveProvider) {
        echo PHP_EOL . RED . "✗ No active AI provider with valid API key found!" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => 'No active AI provider with valid API key. AI features will not work.'];
    }

    // Run the model status check command if no critical issues found
    $hasCriticalIssues = false;
    foreach ($issues as $issue) {
        if ($issue['type'] === 'critical') {
            $hasCriticalIssues = true;
            break;
        }
    }

    if (!$hasCriticalIssues) {
        echo PHP_EOL . BOLD . "Running model status check command..." . RESET . PHP_EOL;
        echo "Executing: php artisan check:model-status" . PHP_EOL;

        exec('php artisan check:model-status 2>&1', $output, $returnVar);

        if ($returnVar === 0) {
            echo GREEN . "✓ Model status check completed successfully" . RESET . PHP_EOL;
            foreach ($output as $line) {
                echo "  " . $line . PHP_EOL;
            }
        } else {
            echo RED . "✗ Model status check failed" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => 'Model status check command failed'];
            foreach ($output as $line) {
                echo "  " . $line . PHP_EOL;
            }
        }
    } else {
        echo YELLOW . "⚠ Skipping model status check due to configuration issues" . RESET . PHP_EOL;
    }

    return $issues;
}

// Check File Converter
function checkFileConverter() {
    $issues = [];
    echo BOLD . "Checking File Converter..." . RESET . PHP_EOL;

    $envContent = file_exists('.env') ? file_get_contents('.env') : '';
    $fileConverter = getEnvValue('FILE_CONVERTER', $envContent);

    if (empty($fileConverter)) {
        echo RED . "✗ FILE_CONVERTER not set in .env file" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => 'FILE_CONVERTER is not configured in .env file'];
        return $issues;
    }

    echo "Active file converter: $fileConverter" . PHP_EOL;

    // Get the appropriate API URL and key
    if ($fileConverter === 'hawki_converter') {
        $apiUrl = getEnvValue('HAWKI_FILE_CONVERTER_API_URL', $envContent);
        $apiKey = getEnvValue('HAWKI_FILE_CONVERTER_API_KEY', $envContent);
    } elseif ($fileConverter === 'gwdg_docling') {
        $apiUrl = getEnvValue('GWDG_FILE_CONVERTER_API_URL', $envContent);
        $apiKey = getEnvValue('GWDG_API_KEY', $envContent);
    } else {
        echo RED . "✗ Unknown file converter: $fileConverter" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "Unknown file converter type: $fileConverter"];
        return $issues;
    }

    if (empty($apiUrl)) {
        echo RED . "✗ File converter API URL not configured" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => 'File converter API URL is not set'];
        return $issues;
    }

    echo "API URL: $apiUrl" . PHP_EOL;

    // Create a test directory
    $testDir = sys_get_temp_dir() . '/hawki_health_check_' . uniqid();
    mkdir($testDir, 0755, true);

    // Generate a simple test string
    $testContent = 'HAWKI Health Check Test';
    echo "Test content: $testContent" . PHP_EOL;

    // Create a minimal valid PDF file
    $pdfFile = $testDir . '/test.pdf';
    $pdfContent = "%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj
2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj
3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
>>
>>
>>
endobj
4 0 obj
<<
/Length 44
>>
stream
BT
/F1 12 Tf
100 700 Td
($testContent) Tj
ET
endstream
endobj
xref
0 5
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000317 00000 n
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
410
%%EOF";

    file_put_contents($pdfFile, $pdfContent);

    echo "Testing file converter with PDF file (this may take up to 4 minutes)..." . PHP_EOL;

    // Prepare the file for upload
    $ch = curl_init($apiUrl);

    // Different parameter names for different converters
    $parameterName = ($fileConverter === 'gwdg_docling') ? 'document' : 'file';

    $postFields = [
        $parameterName => new CURLFile($pdfFile, 'application/pdf', 'test.pdf')
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds to establish connection
    curl_setopt($ch, CURLOPT_TIMEOUT, 240); // 4 minutes total for document processing (HAWKI converter needs more time)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // Add headers based on converter type
    $headers = [];
    if ($fileConverter === 'gwdg_docling') {
        if (!empty($apiKey)) {
            $headers[] = "Authorization: Bearer $apiKey";
        }
        $headers[] = "Accept: application/json";
    } elseif ($fileConverter === 'hawki_converter') {
        if (!empty($apiKey)) {
            $headers[] = "Authorization: Bearer $apiKey";
        }
        $headers[] = "Accept: application/json";
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Cleanup
    unlink($pdfFile);
    rmdir($testDir);

    if ($curlError) {
        echo RED . "✗ Cannot connect to file converter: $curlError" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "Cannot connect to file converter: $curlError"];

        // Provide helpful hints
        if (strpos($curlError, 'timeout') !== false || strpos($curlError, 'timed out') !== false) {
            echo YELLOW . "  Hint: The file converter service may not be running or is taking too long to respond." . RESET . PHP_EOL;
            if ($fileConverter === 'hawki_converter') {
                echo YELLOW . "  Make sure the HAWKI File Converter service is running at: $apiUrl" . RESET . PHP_EOL;
            }
        } elseif (strpos($curlError, 'Connection refused') !== false) {
            echo YELLOW . "  Hint: The file converter service is not running or not accessible at: $apiUrl" . RESET . PHP_EOL;
        }

        return $issues;
    }

    if ($httpCode == 200) {
        // Parse response based on converter type
        if ($fileConverter === 'gwdg_docling') {
            $jsonResponse = json_decode($response, true);
            if (isset($jsonResponse['markdown'])) {
                $convertedContent = $jsonResponse['markdown'];
                if (stripos($convertedContent, $testContent) !== false || stripos($convertedContent, 'HAWKI') !== false) {
                    echo GREEN . "✓ File converter is working correctly" . RESET . PHP_EOL;
                    echo GREEN . "✓ Converted markdown content matches test input" . RESET . PHP_EOL;
                } else {
                    echo YELLOW . "⚠ File converter responded but content verification failed" . RESET . PHP_EOL;
                    $issues[] = ['type' => 'warning', 'message' => 'File converter responded but converted content does not match expected output'];
                }
            } else {
                echo RED . "✗ File converter response missing 'markdown' field" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => 'File converter response is invalid (missing markdown field)'];
            }
        } elseif ($fileConverter === 'hawki_converter') {
            // HAWKI converter returns a ZIP file
            // Check if response starts with ZIP signature (PK)
            if (substr($response, 0, 2) === 'PK') {
                echo GREEN . "✓ File converter is working correctly" . RESET . PHP_EOL;
                echo GREEN . "✓ Received valid ZIP archive response" . RESET . PHP_EOL;
            } else {
                // Maybe it's a JSON error response
                $jsonResponse = json_decode($response, true);
                if ($jsonResponse !== null) {
                    echo RED . "✗ File converter returned error: " . ($jsonResponse['error'] ?? 'Unknown error') . RESET . PHP_EOL;
                    $issues[] = ['type' => 'critical', 'message' => 'File converter returned error'];
                } else {
                    echo YELLOW . "⚠ File converter responded but format is unexpected" . RESET . PHP_EOL;
                    $issues[] = ['type' => 'warning', 'message' => 'File converter response format is unexpected'];
                }
            }
        } else {
            // Unknown converter type
            echo GREEN . "✓ File converter responded with HTTP 200" . RESET . PHP_EOL;
        }
    } else {
        echo RED . "✗ File converter returned HTTP $httpCode" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "File converter returned HTTP $httpCode"];
        echo "Response: " . substr($response, 0, 500) . PHP_EOL;
    }

    return $issues;
}

// Check Storage Services
function checkStorageServices() {
    $issues = [];
    echo BOLD . "Checking Storage Services..." . RESET . PHP_EOL;

    $envContent = file_exists('.env') ? file_get_contents('.env') : '';
    $storageDisk = getEnvValue('STORAGE_DISK', $envContent);
    $avatarStorage = getEnvValue('AVATAR_STORAGE', $envContent);

    if (empty($storageDisk)) {
        $storageDisk = 'local_file_storage'; // Default
    }

    echo "Active storage disk: $storageDisk" . PHP_EOL;
    echo "Avatar storage: $avatarStorage" . PHP_EOL;

    // Map storage disk names to their actual directories/configs
    $storageConfigs = [
        'local' => ['type' => 'local', 'path' => 'storage/app'],
        'local_file_storage' => ['type' => 'local', 'path' => 'storage/app/data_repo'],
        'public' => ['type' => 'local', 'path' => 'storage/app/public'],
        's3' => ['type' => 's3'],
        'nextcloud' => ['type' => 'nextcloud'],
        'sftp' => ['type' => 'sftp'],
    ];

    // Check main storage disk
    if (isset($storageConfigs[$storageDisk])) {
        $config = $storageConfigs[$storageDisk];

        if ($config['type'] === 'local') {
            echo BOLD . "Testing local storage..." . RESET . PHP_EOL;
            $testFile = $config['path'] . '/health_check_test_' . uniqid() . '.txt';
            $testContent = 'HAWKI Health Check - ' . date('Y-m-d H:i:s');

            // Test write
            if (!file_exists($config['path'])) {
                echo RED . "✗ Storage directory does not exist: {$config['path']}" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => "Storage directory does not exist: {$config['path']}"];
            } elseif (!is_writable($config['path'])) {
                echo RED . "✗ Storage directory is not writable: {$config['path']}" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => "Storage directory is not writable: {$config['path']}"];
            } else {
                // Try to write
                if (file_put_contents($testFile, $testContent) !== false) {
                    echo GREEN . "✓ Write test successful" . RESET . PHP_EOL;

                    // Try to read
                    $readContent = file_get_contents($testFile);
                    if ($readContent === $testContent) {
                        echo GREEN . "✓ Read test successful" . RESET . PHP_EOL;
                    } else {
                        echo RED . "✗ Read test failed - content mismatch" . RESET . PHP_EOL;
                        $issues[] = ['type' => 'critical', 'message' => "Read test failed for storage: {$config['path']}"];
                    }

                    // Cleanup
                    unlink($testFile);
                } else {
                    echo RED . "✗ Write test failed" . RESET . PHP_EOL;
                    $issues[] = ['type' => 'critical', 'message' => "Cannot write to storage directory: {$config['path']}"];
                }
            }
        } elseif ($config['type'] === 's3') {
            echo BOLD . "Testing S3 storage..." . RESET . PHP_EOL;
            $s3Key = getEnvValue('S3_ACCESS_KEY', $envContent);
            $s3Secret = getEnvValue('S3_SECRET_KEY', $envContent);
            $s3Endpoint = getEnvValue('S3_ENDPOINT', $envContent);
            $s3Bucket = getEnvValue('S3_DEFAULT_BUCKET', $envContent);

            if (empty($s3Key) || empty($s3Secret) || empty($s3Endpoint) || empty($s3Bucket)) {
                echo RED . "✗ S3 configuration incomplete" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => 'S3 storage is selected but configuration is incomplete'];
            } else {
                echo GREEN . "✓ S3 configuration appears complete" . RESET . PHP_EOL;
                echo YELLOW . "⚠ Full S3 connectivity test requires AWS SDK (skipping)" . RESET . PHP_EOL;
            }
        } elseif ($config['type'] === 'nextcloud') {
            echo BOLD . "Testing Nextcloud storage..." . RESET . PHP_EOL;
            $ncUrl = getEnvValue('NEXTCLOUD_BASE_URL', $envContent);
            $ncUser = getEnvValue('NEXTCLOUD_USERNAME', $envContent);
            $ncPass = getEnvValue('NEXTCLOUD_PASSWORD', $envContent);

            if (empty($ncUrl) || empty($ncUser) || empty($ncPass)) {
                echo RED . "✗ Nextcloud configuration incomplete" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => 'Nextcloud storage is selected but configuration is incomplete'];
            } else {
                echo GREEN . "✓ Nextcloud configuration appears complete" . RESET . PHP_EOL;
                echo YELLOW . "⚠ Full Nextcloud connectivity test requires WebDAV client (skipping)" . RESET . PHP_EOL;
            }
        } elseif ($config['type'] === 'sftp') {
            echo BOLD . "Testing SFTP storage..." . RESET . PHP_EOL;
            $sftpHost = getEnvValue('SFTP_HOST', $envContent);
            $sftpUser = getEnvValue('SFTP_USERNAME', $envContent);
            $sftpPass = getEnvValue('SFTP_PASSWORD', $envContent);

            if (empty($sftpHost) || empty($sftpUser) || empty($sftpPass)) {
                echo RED . "✗ SFTP configuration incomplete" . RESET . PHP_EOL;
                $issues[] = ['type' => 'critical', 'message' => 'SFTP storage is selected but configuration is incomplete'];
            } else {
                echo GREEN . "✓ SFTP configuration appears complete" . RESET . PHP_EOL;
                echo YELLOW . "⚠ Full SFTP connectivity test requires SSH2 extension (skipping)" . RESET . PHP_EOL;
            }
        }
    } else {
        echo RED . "✗ Unknown storage disk: $storageDisk" . RESET . PHP_EOL;
        $issues[] = ['type' => 'critical', 'message' => "Unknown storage disk type: $storageDisk"];
    }

    return $issues;
}

// Check Broadcasting and Queue Workers
function checkBroadcastingAndQueue() {
    $issues = [];
    echo BOLD . "Checking Broadcasting and Queue Workers..." . RESET . PHP_EOL;

    $envContent = file_exists('.env') ? file_get_contents('.env') : '';

    // Check Queue Configuration
    echo BOLD . "Checking Queue Configuration..." . RESET . PHP_EOL;
    $queueConnection = getEnvValue('QUEUE_CONNECTION', $envContent);

    if (empty($queueConnection)) {
        $queueConnection = 'sync'; // Default
    }

    echo "Queue connection: $queueConnection" . PHP_EOL;

    // Check if queue worker is running (only for async queues)
    if ($queueConnection !== 'sync') {
        exec('ps aux | grep "queue:work" | grep -v grep', $queueProcesses);

        if (count($queueProcesses) > 0) {
            echo GREEN . "✓ Queue worker is running (" . count($queueProcesses) . " process(es))" . RESET . PHP_EOL;
        } else {
            echo YELLOW . "⚠ Queue worker is not running" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => 'Queue worker is not running. Start it with: php artisan queue:work'];
            echo YELLOW . "  Start with: php artisan queue:work" . RESET . PHP_EOL;
        }

        // Check queue status
        exec('php artisan queue:failed --json 2>&1', $failedJobsOutput, $failedJobsReturnVar);
        if ($failedJobsReturnVar === 0) {
            $failedJobs = json_decode(implode('', $failedJobsOutput), true);
            if (is_array($failedJobs) && count($failedJobs) > 0) {
                echo YELLOW . "⚠ Found " . count($failedJobs) . " failed job(s)" . RESET . PHP_EOL;
                $issues[] = ['type' => 'warning', 'message' => 'There are failed jobs in the queue. Check with: php artisan queue:failed'];
            } else {
                echo GREEN . "✓ No failed jobs in queue" . RESET . PHP_EOL;
            }
        }
    } else {
        echo YELLOW . "⚠ Queue is set to 'sync' (synchronous) - jobs will run immediately" . RESET . PHP_EOL;
    }

    // Check Broadcasting Configuration
    echo PHP_EOL . BOLD . "Checking Broadcasting Configuration..." . RESET . PHP_EOL;
    $broadcastDriver = getEnvValue('BROADCAST_DRIVER', $envContent);
    $reverbHost = getEnvValue('REVERB_HOST', $envContent);
    $reverbPort = getEnvValue('REVERB_PORT', $envContent);

    if (empty($broadcastDriver)) {
        $broadcastDriver = 'null';
    }

    echo "Broadcast driver: $broadcastDriver" . PHP_EOL;

    if ($broadcastDriver === 'reverb') {
        echo "Reverb host: $reverbHost" . PHP_EOL;
        echo "Reverb port: $reverbPort" . PHP_EOL;

        // Check if Reverb server is running
        exec('ps aux | grep "reverb:start" | grep -v grep', $reverbProcesses);

        if (count($reverbProcesses) > 0) {
            echo GREEN . "✓ Reverb server is running" . RESET . PHP_EOL;
        } else {
            echo YELLOW . "⚠ Reverb server is not running" . RESET . PHP_EOL;
            $issues[] = ['type' => 'warning', 'message' => 'Reverb server is not running. Start it with: php artisan reverb:start'];
            echo YELLOW . "  Start with: php artisan reverb:start" . RESET . PHP_EOL;
        }

        // Try to connect to Reverb
        if (!empty($reverbHost) && !empty($reverbPort)) {
            $reverbUrl = "http://$reverbHost:$reverbPort";
            echo "Testing connection to Reverb at: $reverbUrl" . PHP_EOL;

            $ch = curl_init($reverbUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$curlError && $httpCode > 0) {
                echo GREEN . "✓ Successfully connected to Reverb (HTTP $httpCode)" . RESET . PHP_EOL;
            } else {
                echo YELLOW . "⚠ Cannot connect to Reverb: " . ($curlError ?: "HTTP $httpCode") . RESET . PHP_EOL;
                $issues[] = ['type' => 'warning', 'message' => "Cannot connect to Reverb at $reverbUrl"];
            }
        }
    } elseif ($broadcastDriver === 'null') {
        echo YELLOW . "⚠ Broadcasting is disabled (driver: null)" . RESET . PHP_EOL;
    } else {
        echo GREEN . "✓ Broadcasting driver: $broadcastDriver" . RESET . PHP_EOL;
    }

    return $issues;
}

// Generate Health Report
function generateHealthReport($issues) {
    echo PHP_EOL . PHP_EOL;
    echo BOLD . BLUE . "╔════════════════════════════════════════════════════════════════╗" . RESET . PHP_EOL;
    echo BOLD . BLUE . "║                    HEALTH CHECK REPORT                         ║" . RESET . PHP_EOL;
    echo BOLD . BLUE . "╚════════════════════════════════════════════════════════════════╝" . RESET . PHP_EOL . PHP_EOL;

    if (empty($issues)) {
        echo GREEN . BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;
        echo GREEN . BOLD . "✓ ALL SYSTEMS OPERATIONAL" . RESET . PHP_EOL;
        echo GREEN . BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;
        echo PHP_EOL . GREEN . "Congratulations! Your HAWKI installation passed all health checks." . RESET . PHP_EOL;
        echo GREEN . "The system is ready for use." . RESET . PHP_EOL . PHP_EOL;
        return;
    }

    // Count issues by type
    $criticalCount = 0;
    $warningCount = 0;

    foreach ($issues as $issue) {
        if ($issue['type'] === 'critical') {
            $criticalCount++;
        } elseif ($issue['type'] === 'warning') {
            $warningCount++;
        }
    }

    echo BOLD . "Summary:" . RESET . PHP_EOL;
    echo "  Total issues found: " . count($issues) . PHP_EOL;

    if ($criticalCount > 0) {
        echo RED . "  Critical issues: $criticalCount" . RESET . PHP_EOL;
    }
    if ($warningCount > 0) {
        echo YELLOW . "  Warnings: $warningCount" . RESET . PHP_EOL;
    }

    echo PHP_EOL;

    // Display critical issues
    if ($criticalCount > 0) {
        echo RED . BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;
        echo RED . BOLD . "CRITICAL ISSUES (must be fixed):" . RESET . PHP_EOL;
        echo RED . BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;

        $criticalNum = 1;
        foreach ($issues as $issue) {
            if ($issue['type'] === 'critical') {
                echo RED . "$criticalNum. " . $issue['message'] . RESET . PHP_EOL;
                $criticalNum++;
            }
        }
        echo PHP_EOL;
    }

    // Display warnings
    if ($warningCount > 0) {
        echo YELLOW . BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;
        echo YELLOW . BOLD . "WARNINGS (recommended to fix):" . RESET . PHP_EOL;
        echo YELLOW . BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;

        $warningNum = 1;
        foreach ($issues as $issue) {
            if ($issue['type'] === 'warning') {
                echo YELLOW . "$warningNum. " . $issue['message'] . RESET . PHP_EOL;
                $warningNum++;
            }
        }
        echo PHP_EOL;
    }

    // Recommendations
    echo BOLD . BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;
    echo BOLD . BLUE . "RECOMMENDATIONS:" . RESET . PHP_EOL;
    echo BOLD . BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . PHP_EOL;

    if ($criticalCount > 0) {
        echo RED . "1. Fix all critical issues before using HAWKI in production" . RESET . PHP_EOL;
        echo RED . "2. Review the error messages above for specific solutions" . RESET . PHP_EOL;
    }

    if ($warningCount > 0) {
        echo YELLOW . "3. Address warnings to ensure optimal system performance" . RESET . PHP_EOL;
    }

    echo "4. Re-run health check after fixing issues: " . BOLD . "php hawki check" . RESET . PHP_EOL;
    echo "5. Check HAWKI documentation for detailed configuration guides" . RESET . PHP_EOL;
    echo PHP_EOL;

    if ($criticalCount > 0) {
        echo RED . BOLD . "⚠ WARNING: HAWKI may not function correctly with critical issues present." . RESET . PHP_EOL;
    } else {
        echo GREEN . "✓ No critical issues found. HAWKI should be functional." . RESET . PHP_EOL;
    }

    echo PHP_EOL;
}
