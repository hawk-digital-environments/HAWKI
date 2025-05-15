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
        'name' => 'Name der Anwendung',
        'url' => 'Basis-URL',
        'env' => 'Umgebung (local, production, testing)',
        'timezone' => 'Standard-Zeitzone',
        'locale' => 'Standard-Spracheinstellung',
        'debug' => 'Debug-Modus aktivieren (true/false)',
        'groupchat_active' => 'Gruppenchat aktivieren (true/false)', 
        'ai_handle' => 'KI-Assistenten Handle für Gruppenchat', 

    ],
    'sanctum' => [
        'allow_external_communication' => 'HAWKI-API erlauben',
        'allow_user_token' => 'Generieren von User API-Token erlauben'
    ],
    'test_users' => [
        'active' => 'Test-Benutzeranmeldung aktivieren',
        'testers' => 'Liste der Test-User',
    ],
    'auth' => [
        'authentication_method' => 'Die Authentifizierungsmethode (LDAP, OIDC, Shibboleth)',
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
        'oidc_client_id' => 'Client-ID für die OpenID Connect-Authentifizierung',
        'oidc_client_secret' => 'Client-Secret für die OpenID Connect-Authentifizierung',
        'oidc_logout_path' => 'Logout-Pfad für den OpenID Connect Identity Provider',
        'oidc_scopes' => 'Scopes für die OpenID Connect-Authentifizierung (z. B. profile,email)',
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
