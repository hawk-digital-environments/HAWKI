<?php


return [
    /*
    |--------------------------------------------------------------------------
    | HAWKI Configuration Attributes
    |--------------------------------------------------------------------------
    |
    | HAWKI Attributes can be set in the .env file.
    |
    |
    |
    | !!! YOU CAN NOT CHANGE THE MIGRATION ATTRIBUTES AFTER MIGRATING THE DATABASE !!!
    */

    'spa_auth' => filter_var(env('HAWKI_SPA_AUTH', false), FILTER_VALIDATE_BOOLEAN),

    'migration' => [
        'name' => env('HAWKI_NAME', 'HAWKI'),
        'username' => env('HAWKI_USERNAME', 'HAWKI'),
        'email' => 'HAWKI@hawk.de',
        'employeetype' => 'system',
        'avatar_id' => env('HAWKI_AVATAR', 'hawkiAvatar.jpg'),
    ],

    'aiHandle' => '@'. env('AI_MENTION_HANDLE', 'hawki'),

    'accessibility' => [
        // Public URL of the accessibility statement (Barrierefreiheitserklärung). Empty = no link in the UI.
        'statement_url' => env('ACCESSIBILITY_STATEMENT_URL'),
    ],

    'security' => [
        'passkey' => [
            'auto_generate' => filter_var(env('APP_SECURITY_PASSKEY_AUTO_GENERATE', false), FILTER_VALIDATE_BOOLEAN),
            'allow_paste' => filter_var(env('APP_SECURITY_PASSKEY_ALLOW_PASTE', true), FILTER_VALIDATE_BOOLEAN),
            'char_limitation'=> filter_var(env('APP_SECURITY_PASSKEY_CHAR_LIMITATION', true), FILTER_VALIDATE_INT),
        ],
    ],
];
