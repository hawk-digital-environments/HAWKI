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
        'news_active' => 'Enable news page',
        'file_upload' => 'Enable file upload functionality',
        'websearch' => 'Enable web search functionality',
        'websearch_auto_enable' => 'Automatically enable web search when selecting compatible models',
        'force_default_model' => 'Always reset to default model when opening a new chat',
        'dataprotection_location' => 'Data protection URL',
        'imprint_location' => 'Imprint page URL',
        'accessibility_location' => 'Accessibility statement URL',
        'ai_config_system' => 'DB-based AI configuration system ',
        'language_controller_system' => 'Use database for translations (true) or JSON files (false)',
        'send_registration_mails' => 'Send registration and approval emails to new users',
        'send_groupchat_invitation_mails' => 'Send email notifications for group chat invitations',

    ],
    'system' => [
        'disable_stream_buffering' => 'Clear all output buffers before streaming (enables real-time SSE streaming)',
        'stream_disable_nginx_buffering' => 'Disable Nginx proxy buffering via X-Accel-Buffering header (Impact: High)',
        'stream_disable_apache_gzip' => 'Disable Apache mod_deflate compression for streaming (Impact: Medium)',
        'stream_disable_php_output_buffering' => 'Disable PHP internal output buffering (WARNING: May cause 4s lag, test first!)',
        'stream_disable_zlib_compression' => 'Disable PHP zlib.output_compression for streaming (Impact: Medium)',
    ],
    'scheduler' => [
        'model_status_check.enabled' => 'Enable automatic model status checking',
        'filestorage_cleanup.enabled' => 'Enable automatic file storage cleanup',
        'backup.enabled' => 'Enable automatic backups',
        'backup.schedule_interval' => 'Backup schedule interval (daily, weekly, monthly)',
        'backup.schedule_time' => 'Time when backups should run (HH:MM format, e.g., 02:00)',
        'backup.include_files' => 'Include user files in backup (avatars, attachments). When disabled, only database is backed up.',
        'backup.destination.filename_prefix' => 'Backup filename prefix (e.g., prod-, staging-)',
        'cleanup.enabled' => 'Enable automatic backup cleanup (WARNING: will delete old backups!)',
    ],
    'sanctum' => [
        'allow_external_communication' => 'Allow HAWKI API',
        'allow_user_token' => 'Allow generation of user API tokens',
    ],
    'auth' => [
        'local_authentication' => 'Activate local user authentication ',
        'local_selfservice' => 'Allow local users to request a guest account',
        'local_needapproval' => 'New local users need admin approval before given access',
        'authentication_method' => 'Authentication method',
        'passkey_method' => 'Method for generating the PassKey',
        'passkey_webauthn' => 'Enable WebAuthn cross-device passkeys',
    ],
    'ldap' => [
        'logging.enabled' => 'Logging of LDAP queries',
        'cache.enabled' => 'Caching of LDAP queries',
        'connections.default.ldap_host' => 'Hostname of the LDAP server',
        'connections.default.ldap_port' => 'Port number of the LDAP server',
        'connections.default.ldap_bind_dn' => 'Distinguished Name (DN) used for bind operation (authentication)',
        'connections.default.ldap_bind_pw' => 'Password to access the LDAP server',
        'connections.default.ldap_base_dn' => 'Base DN for LDAP searches (search base)',
        'connections.default.ldap_filter' => 'Filter required for authentication based on Username',
        'connections.default.attribute_map.username' => 'Username Key Name Override',
        'connections.default.attribute_map.email' => 'Email Key Name Override',
        'connections.default.attribute_map.employeeType' => 'EmployeeType Key Name Override',
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
        'triggers.raw_curl_chunk' => '0. Log raw cURL chunks before StreamChunkHandler processing (streaming requests only)',
        'triggers.curl_return_object' => '1. Log cURL response after StreamChunkHandler',
    ],
    'mail' => [
        'default' => 'Default mailer (smtp, herd, sendmail, log, array, etc.)',
        'from.address' => 'Global "From" email address',
        'from.name' => 'Global "From" name',

        // SMTP Mailer Configuration
        'mailers.smtp.transport' => 'SMTP transport type (smtp)',
        'mailers.smtp.host' => 'SMTP server hostname',
        'mailers.smtp.port' => 'SMTP server port (usually 587 for TLS, 465 for SSL, 25 for unencrypted)',
        'mailers.smtp.encryption' => 'SMTP encryption method (tls, ssl, or leave empty for none)',
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

    'backup' => [
        'cleanup.default_strategy.keep_all_backups_for_days' => 'Keep all backups for this many days',
        'cleanup.default_strategy.keep_daily_backups_for_days' => 'Keep daily backups for this many days',
        'cleanup.default_strategy.keep_weekly_backups_for_weeks' => 'Keep weekly backups for this many weeks',
        'cleanup.default_strategy.keep_monthly_backups_for_months' => 'Keep monthly backups for this many months',
        'cleanup.default_strategy.keep_yearly_backups_for_years' => 'Keep yearly backups for this many years',
        'cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => 'Delete oldest backups when using more than this many megabytes',
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
        'system' => 'system',
        'sanctum' => 'api',
        'auth' => 'authentication',
        'ldap' => 'authentication',
        'open_id_connect' => 'authentication',
        'shibboleth' => 'authentication',
        'session' => 'authentication',
        'logging' => 'logging',
        'mail' => 'mail',
        'backup' => 'backup',
        'scheduler' => 'system',
    ],
];
