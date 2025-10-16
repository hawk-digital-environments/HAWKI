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
    | File Upload
    |--------------------------------------------------------------------------
    |
    | Enable or disable file upload functionality in the application.
    |
    */
    'file_upload' => env('HAWKI_FILE_UPLOAD', true),

    /*
    |--------------------------------------------------------------------------
    | Web Search
    |--------------------------------------------------------------------------
    |
    | Enable or disable web search functionality in the application.
    |
    */
    'websearch' => env('HAWKI_WEBSEARCH', true),

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
    | Data Protection Location
    |--------------------------------------------------------------------------
    |
    | URL to the data protection page (Datenschutz). This will be displayed
    | in the footer of the login page. Supports both internal routes and
    | external URLs.
    |
    */
    'dataprotection_location' => env('DATAPROTECTION_LOCATION', '/dataprotection'),

    /*
    |--------------------------------------------------------------------------
    | Accessibility Location
    |--------------------------------------------------------------------------
    |
    | URL to the accessibility statement page (Barrierefreiheit). This will be
    | displayed in the footer of the login page.
    |
    */
    'accessibility_location' => env('ACCESSIBILITY_LOCATION', '/accessibility'),

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

    /*
    |--------------------------------------------------------------------------
    | Send Registration Mails
    |--------------------------------------------------------------------------
    |
    | Enable or disable sending registration and approval emails to new users.
    | When enabled, users will receive welcome emails and approval notifications.
    |
    */
    'send_registration_mails' => true,

    /*
    |--------------------------------------------------------------------------
    | Send Group Chat Invitation Mails
    |--------------------------------------------------------------------------
    |
    | Enable or disable sending email notifications for group chat invitations.
    | When enabled, users will receive emails when invited to group chats.
    |
    */
    'send_groupchat_invitation_mails' => true,
];
