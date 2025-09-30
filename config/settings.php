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
        'debug' => 'Enable debug mode',

    ],
    'hawki' => [
        'aiHandle' => 'AI assistant handle for group chat (@ will be added automatically)',
        'groupchat_active' => 'Enable group chat',
        'ai_config_system' => 'DB-based AI configuration system',
        'language_controller_system' => 'Use database for translations (true) or JSON files (false)',

    ],
    'sanctum' => [
        'allow_external_communication' => 'Allow HAWKI API',
        'allow_user_token' => 'Allow generation of user API tokens',
    ],
    'auth' => [
        'local_authentication' => 'Activate login form for local users ',
        'local_selfservice' => 'Activate registration form for local user account creation',
        'local_needapproval' => 'New local users need admin approval before given access',
        'authentication_method' => 'Authentication method',
        'passkey_method' => 'Method for generating the PassKey (cannot be changed later)',
        'passkey_secret' => 'Secret for PassKey generation (cannot be changed later)',
        'passkey_otp' => 'Send Log-in codes when user tries to login with new device',
        'passkey_otp_timeout' => 'Set Timeout for Log-in code verification (seconds)',
    ],
    'ldap' => [
        'logging.enabled' => 'Logging of LDAP queries',
        'cache.enabled' => 'Caching of LDAP queries',
        'connections.default.ldap_host' => 'Hostname of the LDAP server',
        'connections.default.ldap_port' => 'Port number of the LDAP server',
        'connections.default.ldap_base_dn' => 'Distinguished Name (DN) used for bind operation',
        'connections.default.ldap_bind_pw' => 'Password to access the LDAP server',
        'connections.default.ldap_search_dn' => 'Base DN for the LDAP search',
        'connections.default.ldap_filter' => 'Filter required for authentication based on Username',
        'connections.default.attribute_map.username' => 'Username Key Name Override',
        'connections.default.attribute_map.email' => 'Email Key Name Override',
        'connections.default.attribute_map.employeetype' => 'Employeetype Key Name Override',
        'connections.default.attribute_map.name' => 'Displayname Key Name Override',
        'connections.default.invert_name' => 'Invert name format for display',
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
    'logging' => [
        'default' => 'Default log channel (stack, single, daily, database, stack_with_database, etc.)',
        'channels.stack.channels' => 'Comma-separated list of channels for stack driver',
        'channels.database.level' => 'Minimum log level for database logging (debug, info, warning, error, critical)',
        'triggers.curl_request_object' => '0. Log raw cURL response data from AI providers (BaseAIModelProvider level)',
        'triggers.curl_return_object' => '1. Log raw cURL response data from AI providers (BaseAIModelProvider level)',
        'triggers.normalized_return_object' => '2. Log SSE stream data after normalization in StreamController',
        'triggers.formatted_stream_chunk' => '3. Log AI provider formatted StreamChunk output',
        'triggers.translated_return_object' => '4. Log final StreamMessages output (last point before frontend)',
        'triggers.default_model' => 'Log default model selection and fallback behavior',
        'triggers.usage' => 'Log token usage data from AI provider responses',

    ],
    'mail' => [
        'default' => 'Default mailer (smtp, herd, sendmail, log, array, etc.)',
        'from.address' => 'Global "From" email address',
        'from.name' => 'Global "From" name',

        // SMTP Mailer Configuration
        'mailers.smtp.transport' => 'SMTP transport type (smtp)',
        'mailers.smtp.url' => 'SMTP URL (alternative to individual settings)',
        'mailers.smtp.host' => 'SMTP server hostname',
        'mailers.smtp.port' => 'SMTP server port (usually 587 for TLS, 465 for SSL)',
        'mailers.smtp.encryption' => 'SMTP encryption method (tls, ssl, or none)',
        'mailers.smtp.username' => 'SMTP authentication username',
        'mailers.smtp.password' => 'SMTP authentication password',
        'mailers.smtp.timeout' => 'SMTP connection timeout in seconds',

        // Herd Mailer Configuration (Laravel Herd local development)
        'mailers.herd.transport' => 'Herd transport type (smtp)',
        // 'mailers.herd.url' => 'Herd URL (alternative to individual settings)',
        'mailers.herd.host' => 'Herd SMTP hostname (usually localhost)',
        'mailers.herd.port' => 'Herd SMTP port (usually 2525)',
        'mailers.herd.encryption' => 'Herd encryption method (usually tls)',
        // 'mailers.herd.username' => 'Herd username (usually empty for local)',
        // 'mailers.herd.password' => 'Herd password (usually empty for local)',
        // 'mailers.herd.timeout' => 'Herd connection timeout in seconds',

        // Sendmail Configuration
        // 'mailers.sendmail.transport' => 'Sendmail transport type (sendmail)',
        // 'mailers.sendmail.url' => 'Sendmail URL (usually not used)',
        // 'mailers.sendmail.host' => 'Sendmail host (usually not applicable)',
        // 'mailers.sendmail.port' => 'Sendmail port (usually not applicable)',
        // 'mailers.sendmail.encryption' => 'Sendmail encryption (usually not applicable)',
        // 'mailers.sendmail.username' => 'Sendmail username (usually not applicable)',
        // 'mailers.sendmail.password' => 'Sendmail password (usually not applicable)',
        // 'mailers.sendmail.timeout' => 'Sendmail timeout in seconds',
        // 'mailers.sendmail.path' => 'Path to sendmail binary (e.g., /usr/sbin/sendmail -bs -i)',

    ],
    'reverb' => [
        'servers.reverb.host' => 'Reverb server host (usually 0.0.0.0 for all interfaces)',
        'servers.reverb.port' => 'Reverb server port (default: 8080)',
        'servers.reverb.hostname' => 'Reverb hostname for client connections',
        'apps.apps.0.options.host' => 'Reverb client host (for WebSocket connections)',
        'apps.apps.0.options.port' => 'Reverb client port (default: 443 for HTTPS)',
        'apps.apps.0.options.scheme' => 'Reverb scheme (http or https)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapping of configuration files to UI groups
    |--------------------------------------------------------------------------
    |
    | This mapping defines which UI group a configuration file belongs to.
    | It is used to group settings in the administration UI. Also the value
    | set here will define the 'group' value for the given key during db
    | migration.
    |
    */
    'group_mapping' => [
        'app' => 'basic',
        'hawki' => 'basic',
        'sanctum' => 'api',
        'auth' => 'authentication',
        'ldap' => 'authentication',
        'open_id_connect' => 'authentication',
        'shibboleth' => 'authentication',
        'session' => 'authentication',
        'logging' => 'logging',
        'mail' => 'mail',
        'reverb' => 'websockets',
    ],
];
