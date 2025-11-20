<?php

/**
 * Project setup and initialization commands
 */

function initializeAll(){
    initialize();

    if(askForNextStep('Variables Setup')){
        setupEnv(null);
    }
    if(askForNextStep('AI Model Providers Setup')){
        setupModelProviders();
    }
    if(askForNextStep('Database Migration')){
        migrate(null);
    }
}

function initialize() {
    echo BOLD . "Initializing HAWKI project..." . RESET . PHP_EOL;

    echo PHP_EOL . BLUE . LOGO . RESET . PHP_EOL . PHP_EOL;

    // Check for .env file
    if (!file_exists('.env')) {
        if (file_exists('.env.example')) {
            echo "Creating .env file from .env.example..." . PHP_EOL;
            copy('.env.example', '.env');
            echo GREEN . "✓ Created .env file" . RESET . PHP_EOL;
        } else {
            echo RED . "✗ .env.example not found" . RESET . PHP_EOL;
        }
    } else {
        echo GREEN . "✓ .env file already exists" . RESET . PHP_EOL;
    }

    // Check for test_users.json
    if (!file_exists('storage/app/test_users.json')) {
        if (file_exists('storage/app/test_users.json.example')) {
            echo "Creating test_users.json from example..." . PHP_EOL;
            copy('storage/app/test_users.json.example', 'storage/app/test_users.json');
            echo GREEN . "✓ Created test_users.json" . RESET . PHP_EOL;
        } else {
            echo YELLOW . "! test_users.json.example not found" . RESET . PHP_EOL;
        }
    } else {
        echo GREEN . "✓ test_users.json already exists" . RESET . PHP_EOL;
    }

    // Check for model_providers.php
    if (!file_exists('config/model_providers.php')) {
        if (file_exists('config/model_providers.php.example')) {
            echo "Creating model_providers.php from example..." . PHP_EOL;
            copy('config/model_providers.php.example', 'config/model_providers.php');
            echo GREEN . "✓ Created model_providers.php" . RESET . PHP_EOL;
        } else {
            echo YELLOW . "! model_providers.php.example not found" . RESET . PHP_EOL;
        }
    } else {
        echo GREEN . "✓ model_providers.php already exists" . RESET . PHP_EOL;
    }

    // Run initialization commands
    echo PHP_EOL . "Running initialization commands..." . PHP_EOL;

    // Composer install
    echo YELLOW . "Running composer install..." . RESET . PHP_EOL;
    passthru('composer install');

    // Check if we need to update composer
    echo YELLOW . "Checking for composer updates..." . RESET . PHP_EOL;
    exec('composer outdated --direct', $outdatedOutput);
    if (!empty($outdatedOutput) && count($outdatedOutput) > 1) { // First line is header
        echo "Outdated packages found. Running composer update..." . PHP_EOL;
        passthru('composer update');
    } else {
        echo GREEN . "✓ All composer packages are up to date" . RESET . PHP_EOL;
    }

    // npm install
    echo YELLOW . "Running npm install..." . RESET . PHP_EOL;
    passthru('npm install');

    // Create storage symlink
    echo YELLOW . "Creating storage link..." . RESET . PHP_EOL;
    passthru('php artisan storage:link --ansi');

    // Generate app key if not present
    $env = getEnvContent();
    if (strpos($env, 'APP_KEY=') === false || strpos($env, 'APP_KEY=base64:') === false) {
        echo YELLOW . "Generating application key..." . RESET . PHP_EOL;
        passthru('php artisan key:generate --ansi');
        $env = getEnvContent(); // Reload env content after key generation
    }

    // Generate security keys and salts if not present
    echo YELLOW . "Checking security keys and salts..." . RESET . PHP_EOL;

    // Reverb App Keys
    if (!getEnvValue('REVERB_APP_KEY', $env)) {
        $reverbAppKey = generateAlphanumeric(32);
        $env = setEnvValue('REVERB_APP_KEY', $reverbAppKey, $env);
        echo GREEN . "✓ Generated REVERB_APP_KEY" . RESET . PHP_EOL;
    }

    if (!getEnvValue('REVERB_APP_SECRET', $env)) {
        $reverbAppSecret = generateAlphanumeric(32);
        $env = setEnvValue('REVERB_APP_SECRET', $reverbAppSecret, $env);
        echo GREEN . "✓ Generated REVERB_APP_SECRET" . RESET . PHP_EOL;
    }

    // Security Salts - use secure random bytes for these
    $saltKeys = [
        'USERDATA_ENCRYPTION_SALT',
        'INVITATION_SALT',
        'AI_CRYPTO_SALT',
        'PASSKEY_SALT',
        'BACKUP_SALT'
    ];

    foreach ($saltKeys as $saltKey) {
        if (!getEnvValue($saltKey, $env)) {
            $salt = bin2hex(random_bytes(16)); // 32 character hex string
            $env = setEnvValue($saltKey, $salt, $env);
            echo GREEN . "✓ Generated $saltKey" . RESET . PHP_EOL;
        }
    }

    // Save environment changes
    saveEnv($env);

    // Clear all caches to ensure everything is fresh
    clearCache();

    echo PHP_EOL . GREEN . BOLD . "Initialization complete!" . RESET . PHP_EOL;
}

function setupEnv($flags) {
    // If no flags, run all sections
    if (empty($flags)) {
        setupGeneralEnv();
        setupDatabaseEnv();
        setupAuthEnv();
        setupReverbEnv();
        return;
    }

    // Process specific flags
    switch ($flags[0]) {
        case '-g':
            setupGeneralEnv();
            break;
        case '-db':
            setupDatabaseEnv();
            break;
        case '-auth':
            setupAuthEnv();
            break;
        case '-reverb':
            setupReverbEnv();
            break;
        default:
            echo RED . "Unknown flag: {$flags[0]}" . RESET . PHP_EOL;
            echo "Available flags: -g (General), -db (Database), -auth (Authentication), -reverb (Reverb)" . PHP_EOL;
    }
}

function setupGeneralEnv() {
    echo BOLD . "Setting up General Environment Variables" . RESET . PHP_EOL;

    $env = getEnvContent();

    // APP_NAME
    $current = getEnvValue('APP_NAME', $env);
    $appName = promptWithDefault("APP_NAME", $current);
    $env = setEnvValue('APP_NAME', $appName, $env);

    // APP_URL
    $current = getEnvValue('APP_URL', $env);
    $appUrl = promptWithDefault("APP_URL", $current ?: 'http://localhost:8000');
    $env = setEnvValue('APP_URL', $appUrl, $env);

    // APP_TIMEZONE
    $current = getEnvValue('APP_TIMEZONE', $env);
    $appTimezone = promptWithDefault("APP_TIMEZONE", $current ?: 'UTC');
    $env = setEnvValue('APP_TIMEZONE', $appTimezone, $env);

    // APP_LOCALE
    $current = getEnvValue('APP_LOCALE', $env);
    $appLocale = promptWithDefault("APP_LOCALE (de_DE or en_US)", $current ?: 'en_US');
    $env = setEnvValue('APP_LOCALE', $appLocale, $env);

    // ALLOW_EXTERNAL_COMMUNICATION
    $current = getEnvValue('ALLOW_EXTERNAL_COMMUNICATION', $env);
    $allowExtComm = promptWithDefault("ALLOW_EXTERNAL_COMMUNICATION (true/false)", $current ?: 'false');
    $env = setEnvValue('ALLOW_EXTERNAL_COMMUNICATION', $allowExtComm, $env);

    saveEnv($env);
    echo GREEN . "✓ General environment variables updated" . RESET . PHP_EOL;
}

function setupDatabaseEnv() {
    echo BOLD . "Setting up Database Environment Variables" . RESET . PHP_EOL;

    $env = getEnvContent();

    // DB_CONNECTION
    $current = getEnvValue('DB_CONNECTION', $env);
    $dbConnection = promptWithDefault("DB_CONNECTION (mysql, sqlite, pgsql)", $current ?: 'mysql');
    $env = setEnvValue('DB_CONNECTION', $dbConnection, $env);

    // DB_HOST
    $current = getEnvValue('DB_HOST', $env);
    $dbHost = promptWithDefault("DB_HOST", $current ?: '127.0.0.1');
    $env = setEnvValue('DB_HOST', $dbHost, $env);

    // DB_PORT
    $current = getEnvValue('DB_PORT', $env);
    $defaultPort = $dbConnection === 'mysql' ? '3306' : ($dbConnection === 'pgsql' ? '5432' : '');
    $dbPort = promptWithDefault("DB_PORT", $current ?: $defaultPort);
    $env = setEnvValue('DB_PORT', $dbPort, $env);

    // DB_DATABASE
    $current = getEnvValue('DB_DATABASE', $env);
    $dbDatabase = promptWithDefault("DB_DATABASE", $current ?: 'hawki');
    $env = setEnvValue('DB_DATABASE', $dbDatabase, $env);

    // DB_USERNAME
    $current = getEnvValue('DB_USERNAME', $env);
    $dbUsername = promptWithDefault("DB_USERNAME", $current ?: 'root');
    $env = setEnvValue('DB_USERNAME', $dbUsername, $env);

    // DB_PASSWORD
    $current = getEnvValue('DB_PASSWORD', $env);
    $dbPassword = promptWithDefault("DB_PASSWORD", $current ?: '');
    $env = setEnvValue('DB_PASSWORD', $dbPassword, $env);

    saveEnv($env);
    echo GREEN . "✓ Database environment variables updated" . RESET . PHP_EOL;
}

function setupAuthEnv() {
    echo BOLD . "Setting up Authentication Environment Variables" . RESET . PHP_EOL;

    $env = getEnvContent();

    // AUTHENTICATION_METHOD
    $current = getEnvValue('AUTHENTICATION_METHOD', $env);
    $authMethod = promptWithDefault("AUTHENTICATION_METHOD (LDAP, Shibboleth, OIDC)", $current ?: 'LDAP');
    $env = setEnvValue('AUTHENTICATION_METHOD', $authMethod, $env);

    // Setup specific auth method variables
    switch (strtoupper($authMethod)) {
        case 'LDAP':
            setupLdapEnv($env);
            break;
        case 'SHIBBOLETH':
            setupShibbolethEnv($env);
            break;
        case 'OIDC':
            setupOidcEnv($env);
            break;
        default:
            echo YELLOW . "Unknown authentication method: $authMethod" . RESET . PHP_EOL;
    }
}

function setupLdapEnv(&$env) {
    // LDAP_CONNECTION
    $current = getEnvValue('LDAP_CONNECTION', $env);
    $ldapConnection = promptWithDefault("LDAP_CONNECTION", $current ?: 'default');
    $env = setEnvValue('LDAP_CONNECTION', $ldapConnection, $env);

    // LDAP_HOST
    $current = getEnvValue('LDAP_HOST', $env);
    $ldapHost = promptWithDefault("LDAP_HOST", $current ?: 'ldap.example.com');
    $env = setEnvValue('LDAP_HOST', $ldapHost, $env);

    // LDAP_PORT
    $current = getEnvValue('LDAP_PORT', $env);
    $ldapPort = promptWithDefault("LDAP_PORT", $current ?: '389');
    $env = setEnvValue('LDAP_PORT', $ldapPort, $env);

    // LDAP_USERNAME
    $current = getEnvValue('LDAP_USERNAME', $env);
    $ldapUsername = promptWithDefault("LDAP_USERNAME", $current ?: 'cn=admin,dc=example,dc=com');
    $env = setEnvValue('LDAP_USERNAME', $ldapUsername, $env);

    // LDAP_BIND_PW
    $current = getEnvValue('LDAP_BIND_PW', $env);
    $ldapBindPw = promptWithDefault("LDAP_BIND_PW", $current ?: '');
    $env = setEnvValue('LDAP_BIND_PW', $ldapBindPw, $env);

    // LDAP_BASE_DN
    $current = getEnvValue('LDAP_BASE_DN', $env);
    $ldapBaseDn = promptWithDefault("LDAP_BASE_DN", $current ?: 'dc=example,dc=com');
    $env = setEnvValue('LDAP_BASE_DN', $ldapBaseDn, $env);

    // LDAP_ATTRIBUTES
    $current = getEnvValue('LDAP_ATTRIBUTES', $env);
    $ldapAttributes = promptWithDefault("LDAP_ATTRIBUTES", $current ?: 'cn,mail,displayname');
    $env = setEnvValue('LDAP_ATTRIBUTES', $ldapAttributes, $env);

    // TEST_USER_LOGIN
    $current = getEnvValue('TEST_USER_LOGIN', $env);
    $testUserLogin = promptWithDefault("TEST_USER_LOGIN (true/false)", $current ?: 'false');
    $env = setEnvValue('TEST_USER_LOGIN', $testUserLogin, $env);

    saveEnv($env);
    echo GREEN . "✓ LDAP environment variables updated" . RESET . PHP_EOL;
}

function setupShibbolethEnv(&$env) {
    // SHIBBOLETH_LOGIN_URL
    $current = getEnvValue('SHIBBOLETH_LOGIN_URL', $env);
    $shibLoginUrl = promptWithDefault("SHIBBOLETH_LOGIN_URL", $current ?: '/Shibboleth.sso/Login');
    $env = setEnvValue('SHIBBOLETH_LOGIN_URL', $shibLoginUrl, $env);

    // SHIBBOLETH_LOGOUT_URL
    $current = getEnvValue('SHIBBOLETH_LOGOUT_URL', $env);
    $shibLogoutUrl = promptWithDefault("SHIBBOLETH_LOGOUT_URL", $current ?: '/Shibboleth.sso/Logout');
    $env = setEnvValue('SHIBBOLETH_LOGOUT_URL', $shibLogoutUrl, $env);

    // SHIBBOLETH_NAME_VAR
    $current = getEnvValue('SHIBBOLETH_NAME_VAR', $env);
    $shibNameVar = promptWithDefault("SHIBBOLETH_NAME_VAR", $current ?: 'HTTP_DISPLAYNAME');
    $env = setEnvValue('SHIBBOLETH_NAME_VAR', $shibNameVar, $env);

    // SHIBBOLETH_EMAIL_VAR
    $current = getEnvValue('SHIBBOLETH_EMAIL_VAR', $env);
    $shibEmailVar = promptWithDefault("SHIBBOLETH_EMAIL_VAR", $current ?: 'HTTP_MAIL');
    $env = setEnvValue('SHIBBOLETH_EMAIL_VAR', $shibEmailVar, $env);

    // SHIBBOLETH_EMPLOYEETYPE_VAR
    $current = getEnvValue('SHIBBOLETH_EMPLOYEETYPE_VAR', $env);
    $shibEmployeeVar = promptWithDefault("SHIBBOLETH_EMPLOYEETYPE_VAR", $current ?: 'HTTP_EMPLOYEETYPE');
    $env = setEnvValue('SHIBBOLETH_EMPLOYEETYPE_VAR', $shibEmployeeVar, $env);

    saveEnv($env);
    echo GREEN . "✓ Shibboleth environment variables updated" . RESET . PHP_EOL;
}

function setupOidcEnv(&$env) {
    // OIDC_IDP
    $current = getEnvValue('OIDC_IDP', $env);
    $oidcIdp = promptWithDefault("OIDC_IDP", $current ?: 'https://oidc.example.com');
    $env = setEnvValue('OIDC_IDP', $oidcIdp, $env);

    // OIDC_CLIENT_ID
    $current = getEnvValue('OIDC_CLIENT_ID', $env);
    $oidcClientId = promptWithDefault("OIDC_CLIENT_ID", $current ?: '');
    $env = setEnvValue('OIDC_CLIENT_ID', $oidcClientId, $env);

    // OIDC_CLIENT_SECRET
    $current = getEnvValue('OIDC_CLIENT_SECRET', $env);
    $oidcClientSecret = promptWithDefault("OIDC_CLIENT_SECRET", $current ?: '');
    $env = setEnvValue('OIDC_CLIENT_SECRET', $oidcClientSecret, $env);

    // OIDC_LOGOUT_URI
    $current = getEnvValue('OIDC_LOGOUT_URI', $env);
    $oidcLogoutUri = promptWithDefault("OIDC_LOGOUT_URI", $current ?: 'https://oidc.example.com/logout');
    $env = setEnvValue('OIDC_LOGOUT_URI', $oidcLogoutUri, $env);

    // OIDC_SCOPES
    $current = getEnvValue('OIDC_SCOPES', $env);
    $oidcScopes = promptWithDefault("OIDC_SCOPES", $current ?: 'openid profile email');
    $env = setEnvValue('OIDC_SCOPES', $oidcScopes, $env);

    // OIDC_FIRSTNAME_VAR
    $current = getEnvValue('OIDC_FIRSTNAME_VAR', $env);
    $oidcFirstnameVar = promptWithDefault("OIDC_FIRSTNAME_VAR", $current ?: 'given_name');
    $env = setEnvValue('OIDC_FIRSTNAME_VAR', $oidcFirstnameVar, $env);

    // OIDC_LASTNAME_VAR
    $current = getEnvValue('OIDC_LASTNAME_VAR', $env);
    $oidcLastnameVar = promptWithDefault("OIDC_LASTNAME_VAR", $current ?: 'family_name');
    $env = setEnvValue('OIDC_LASTNAME_VAR', $oidcLastnameVar, $env);

    // OIDC_EMAIL_VAR
    $current = getEnvValue('OIDC_EMAIL_VAR', $env);
    $oidcEmailVar = promptWithDefault("OIDC_EMAIL_VAR", $current ?: 'email');
    $env = setEnvValue('OIDC_EMAIL_VAR', $oidcEmailVar, $env);

    // OIDC_EMPLOYEETYPE_VAR
    $current = getEnvValue('OIDC_EMPLOYEETYPE_VAR', $env);
    $oidcEmployeetypeVar = promptWithDefault("OIDC_EMPLOYEETYPE_VAR", $current ?: 'employee_type');
    $env = setEnvValue('OIDC_EMPLOYEETYPE_VAR', $oidcEmployeetypeVar, $env);

    saveEnv($env);
    echo GREEN . "✓ OIDC environment variables updated" . RESET . PHP_EOL;
}

function setupReverbEnv() {
    echo BOLD . "Setting up Reverb Environment Variables" . RESET . PHP_EOL;

    $env = getEnvContent();

    // REVERB_HOST
    $current = getEnvValue('REVERB_HOST', $env);
    $reverbHost = promptWithDefault("REVERB_HOST", $current ?: '127.0.0.1');
    $env = setEnvValue('REVERB_HOST', $reverbHost, $env);

    // REVERB_PORT
    $current = getEnvValue('REVERB_PORT', $env);
    $reverbPort = promptWithDefault("REVERB_PORT", $current ?: '8080');
    $env = setEnvValue('REVERB_PORT', $reverbPort, $env);

    // REVERB_SERVER_HOST
    $current = getEnvValue('REVERB_SERVER_HOST', $env);
    $reverbServerHost = promptWithDefault("REVERB_SERVER_HOST", $current ?: '127.0.0.1');
    $env = setEnvValue('REVERB_SERVER_HOST', $reverbServerHost, $env);

    // REVERB_SERVER_PORT
    $current = getEnvValue('REVERB_SERVER_PORT', $env);
    $reverbServerPort = promptWithDefault("REVERB_SERVER_PORT", $current ?: '8085');
    $env = setEnvValue('REVERB_SERVER_PORT', $reverbServerPort, $env);

    // REVERB_SCHEME
    $current = getEnvValue('REVERB_SCHEME', $env);
    $reverbScheme = promptWithDefault("REVERB_SCHEME (http, https)", $current ?: 'http');
    $env = setEnvValue('REVERB_SCHEME', $reverbScheme, $env);

    saveEnv($env);
    echo GREEN . "✓ Reverb environment variables updated" . RESET . PHP_EOL;
}

/**
 * Setup model providers configuration
 * @deprecated will be removed in v3.0.0.
 */
function setupModelProviders() {
    echo BOLD . YELLOW. "⚠️  DEPRECATION NOTICE". RESET . PHP_EOL;
    echo BOLD . "This command is deprecated since v2.3.1 and will be removed in v3.0.0.". RESET . PHP_EOL;
    echo "The new structure of the model_providers and model_lists allow optional settings from the .env file. For more information please refer to HAWKI Documentation.". RESET . PHP_EOL. PHP_EOL;
}
