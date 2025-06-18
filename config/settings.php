<?php

return [


    /*
    |--------------------------------------------------------------------------
    | Overridable configuration keys by configuration files
    |--------------------------------------------------------------------------
    |
    | Here, the configuration files and their overridable keys are defined.
    | The configuration file (without .php) is the primary key, followed by an array
    | of overridable keys within that file.
    |
    | Format:
    | - Simple key: 'key' (Description comes from the config file or ENV)
    | - Key with description: 'key' => 'Description text'
    |
    | The database key is formed by combining the configuration file name
    | and key with an underscore: e.g., 'app_name' for 'app.name'
    |
    */
    'app' => [
        'name' => 'Application name',
        'url' => 'Base URL',
        'env' => 'Environment (local, production, testing)',
        'timezone' => 'Default timezone',
        'locale' => 'Default locale',
        'debug' => 'Enable debug mode (true/false)',
        'groupchat_active' => 'Enable group chat (true/false)', 
        'ai_handle' => 'AI assistant handle for group chat',

    ],
    'sanctum' => [
        'allow_external_communication' => 'Allow HAWKI API',
        'allow_user_token' => 'Allow generation of user API tokens'
    ],
    'test_users' => [
        'active' => 'Enable test user login',
        'testers' => 'List of test users',
    ],
    'auth' => [
        'authentication_method' => 'Authentication method (LDAP, OIDC, Shibboleth)',
        'passkey_method' => 'Method for generating the PassKey (cannot be changed later)',
        'passkey_secret' => 'Secret for PassKey generation (cannot be changed later)',
        'passkey_otp' => 'Send Log-in codes when user tries to login with new device',
        'passkey_otp_timeout' => 'Set Timeout for Log-in code verification (seconds)'
    ],
    'ldap' => [
        //'default' => 'Configure the LDAP connection. Currently only "custom" is supported through hardcoding in ldapservice.php',
        //'connections.default.hosts' => 'Hostname of the LDAP server',
        //'connections.default.port' => 'Port number of the LDAP server',
        //'connections.default.username' => 'Distinguished Name (DN) used for bind operation',
        //'connections.default.password' => 'Password to access the LDAP server',
        //'connections.default.base_dn' => 'Base DN for the LDAP search',
        //'connections.default.timeout' => 'Timeout for LDAP queries in seconds',
        //'connections.default.use_ssl' => 'Use SSL to connect to the LDAP server (not recommended)',
        //'connections.default.use_tls' => 'Use TLS to connect to the LDAP server (recommended)',
        //'connections.default.use_sasl' => 'Use SASL to connect to the LDAP server',
        'logging.enabled' => 'Lgging of LDAP queries',
        'cache.enabled' => 'Caching of LDAP queries',
        'custom_connection.ldap_host' => 'Hostname of the LDAP server',
        'custom_connection.ldap_port' => 'Port number of the LDAP server',
        'custom_connection.ldap_base_dn' => 'Distinguished Name (DN) used for bind operation',
        'custom_connection.ldap_bind_pw' => 'Password to access the LDAP server',
        'custom_connection.ldap_search_dn' => 'Base DN for the LDAP search',
        'custom_connection.ldap_filter' => 'Filter required for authentication based on Username',
        'custom_connection.attribute_map.username' => 'Username Key Name Override',
        'custom_connection.attribute_map.email' => 'Email Key Name Override',
        'custom_connection.attribute_map.employeetype' => 'Employeetype Key Name Overridebut',
        'custom_connection.attribute_map.name' => 'Displayname Key Name Override',
    ],
    'open_id_connect' => [
        'oidc_idp' => 'OpenID Connect Identity Provider (z. B. https://idp.example.com)',
        'oidc_client_id' => 'Client ID for OpenID Connect authentication',
        'oidc_client_secret' => 'Client Secret for OpenID Connect authentication',
        'oidc_logout_path' => 'Logout path for the OpenID Connect Identity Provider',
        'oidc_scopes' => 'Scopes for OpenID Connect authentication (e.g., profile,email)',
        'attribute_map.firstname' => 'Firstname Key Name Override',
        'attribute_map.lastname' => 'Lastname Key Name Override',
        'attribute_map.email' => 'E-Mail Key Name Override',
        'attribute_map.employeetype' => 'Employeetype Key Name Override',

    ],
    'shibboleth' => [
        'login_path' => 'Login Path',
        'logout_path' => 'Logout Path',
        'attribute_map.email' => 'E-Mail Key Name Override,',
        'attribute_map.employeetype' => 'Employeetype Key Name Override,',
        'attribute_map.name' => 'Displayname Key Name Override,',
    ],
    'logging' => [],

        /*
    |--------------------------------------------------------------------------
    | Mapping of configuration files to UI groups
    |--------------------------------------------------------------------------
    |
    | This mapping defines which UI group a configuration file belongs to.
    | It is used to group settings in the administration UI.
    |
    */
    'group_mapping' => [
        'app' => 'basic',
        'sanctum' => 'api',
        'auth' => 'authentication',
        'test_users' => 'authentication',
        'ldap' => 'authentication',
        'open_id_connect' => 'authentication',
        'shibboleth' => 'authentication',
        'session' => 'authentication',
    ],
];
