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
    | !!! YOU CAN NOT CHANGE THE MIGRATION ATTRIBUTES AFTER MIGRATING THE DATABSE !!!
    */

    'migration' => [
        'name' => env('HAWKI_NAME', 'HAWKI'),
        'username' => env('HAWKI_USERNAME', 'HAWKI'),
        'email' => 'HAWKI@hawk.de',
        'employeetype' => 'system',
        'avatar_id' => env('HAWKI_AVATAR', 'hawkiAvatar.jpg'),
    ],

    'aiHandle' => '@'.ltrim(env('AI_MENTION_HANDLE', 'hawki'), '@'),

    // use false (JSON files) or true (database)
    'groupchat_active' => true,
    'ai_config_system' => 'default',
    'language_controller_system' => (bool) env('HAWKI_LANGUAGE_CONTROLLER_DB', false),
];
