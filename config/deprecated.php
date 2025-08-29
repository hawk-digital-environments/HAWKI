<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deprecated Configuration Keys
    |--------------------------------------------------------------------------
    |
    | This file contains configuration keys that are no longer used and should
    | be removed from the database during cleanup operations. Keys are organized
    | by version when they were deprecated for better tracking.
    |
    | Format: 'database_key' => 'reason for deprecation'
    |
    */

    'v2.0.1' => [
        // Legacy test users system - replaced by ManageUser command
        'test_users_active' => 'Replaced by ManageUser artisan command',
        'test_users_testers' => 'Replaced by ManageUser artisan command',
        'test_users_import_path' => 'Replaced by ManageUser artisan command',
        
        // Old authentication structure that was refactored
        'auth_old_ldap_config' => 'LDAP configuration structure was updated',
        'legacy_user_roles' => 'User role system was refactored with UserObserver',
    ],

    'v2.1.0' => [
        // Add future deprecated keys here when needed
        // 'example_old_key' => 'Reason for deprecation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Rules
    |--------------------------------------------------------------------------
    |
    | Define rules for how deprecated keys should be handled during cleanup.
    |
    */
    
    'cleanup_rules' => [
        // Should we backup deprecated keys before deletion?
        'backup_before_delete' => true,
        
        // Where to store backup file
        'backup_path' => storage_path('app/config_backups'),
        
        // Should we prompt for confirmation before deletion?
        'require_confirmation' => true,
        
        // Should we log cleanup operations?
        'log_operations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Pattern Matching
    |--------------------------------------------------------------------------
    |
    | Define patterns for automatically detecting potentially deprecated keys
    | that might not be explicitly listed above.
    |
    */
    
    'auto_detect_patterns' => [
        // Keys starting with these prefixes are likely deprecated
        'prefixes' => [
            'old_',
            'legacy_',
            'deprecated_',
            'temp_',
        ],
        
        // Keys containing these strings are likely deprecated  
        'contains' => [
            '_old_',
            '_legacy_',
            '_deprecated_',
            '_temp_',
        ],
    ],

];
