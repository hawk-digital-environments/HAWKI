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

    /*
    |--------------------------------------------------------------------------
    | Imprint Location
    |--------------------------------------------------------------------------
    |
    | URL to the imprint page (Impressum). This will be displayed in the
    | footer of the login page.
    |
    */
    'imprint_location' => env('IMPRINT_LOCATION', '/imprint'),

    /*
    |--------------------------------------------------------------------------
    | AI System
    | This setting gets overwritten by the SettingsService with a value from the db
    |--------------------------------------------------------------------------
    */

    'ai_config_system' => false, // false = config files, true = database

    /*
    |--------------------------------------------------------------------------
    | Style System
    | This setting gets overwritten by the SettingsService with a value from the db
    |--------------------------------------------------------------------------
    */
    'style_config_system' => false,

    /*
    |--------------------------------------------------------------------------
    | Language Controller System
    | This setting gets overwritten by the SettingsService with a value from the db
    |--------------------------------------------------------------------------
    |
    | Controls how the LanguageController loads translations and AI prompts:
    |
    | false (default) = Load from JSON files + Database prompts
    |                  - System texts from resources/language/*.json
    |                  - Localization texts from resources/language/*.html
    |                  - AI prompts from ai_assistants_prompts table (fallback)
    |                  - Better separation between HAWKI and Orchid
    |
    | true           = Load from Database only  
    |                  - System texts from app_system_texts table
    |                  - Localization texts from app_localized_texts table
    |                  - AI prompts from ai_assistants_prompts table
    |                  - Full Orchid Admin Panel integration
    |
    | AI Prompts are available as translation.Default_Prompt, translation.Name_Prompt, etc.
    | in JavaScript regardless of the mode selected.
    |
    */
    'language_controller_system' => false,
];
