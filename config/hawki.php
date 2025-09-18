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

    'aiHandle' => '@'. env('AI_MENTION_HANDLE', 'hawki'),




];
