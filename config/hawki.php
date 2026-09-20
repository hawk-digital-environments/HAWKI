<?php

declare(strict_types=1);

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

    'migration' => [
        'name' => env('HAWKI_NAME', 'HAWKI'),
        'username' => env('HAWKI_USERNAME', 'HAWKI'),
        'email' => 'HAWKI@hawk.de',
        'employeetype' => 'system',
        'avatar_id' => env('HAWKI_AVATAR', 'hawkiAvatar.jpg'),
    ],
    'aiHandle' => '@' . env('AI_MENTION_HANDLE', 'hawki'),
    'aiProxy' => [
        /*
        |----------------------------------------------------------------------
        | Custom (non-standard) wire events
        |----------------------------------------------------------------------
        |
        | HAWKI augments the standard wire formats with custom `hawki:` events/items
        | (e.g. streamed citations) whenever the format itself has no native slot for
        | them. Set AI_PROXY_EMIT_CUSTOM_EVENTS=false to emit strictly spec-shaped
        | output in every format — HAWKI-specific data is then dropped at the
        | formatter boundary (the IR keeps it either way).
        |
         */
        'emit_custom_events' => filter_var(env('AI_PROXY_EMIT_CUSTOM_EVENTS', true), \FILTER_VALIDATE_BOOLEAN),
    ],
    'security' => [
        'passkey' => [
            'allow_paste' => filter_var(env('APP_SECURITY_PASSKEY_ALLOW_PASTE', true), \FILTER_VALIDATE_BOOLEAN),
            'char_limitation' => filter_var(env('APP_SECURITY_PASSKEY_CHAR_LIMITATION', true), \FILTER_VALIDATE_INT),
        ],
    ],
];
