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

    'branding' => [
        // Brand color as #RRGGBB. Its hue and saturation drive the accent color ramp. The default is
        // the HAWKI blue the built-in ramp is made of (oklch 45% 0.24 265), so it changes nothing.
        'accent_color' => env('BRAND_ACCENT_COLOR') ?: '#1139d7',
        // Corner rounding of the interface: round (default), soft or square.
        'corner_style' => env('BRAND_CORNER_STYLE', 'round'),
        // SVG markup of the logo and the browser tab icon. Uploaded in the
        // administration (Settings > Branding), which validates the files; there is no .env equivalent.
        'logo' => null,
        'favicon' => null,
        // Height of the logo in the sidebar, in pixels (12-36). The sign-in page scales with it.
        // Empty = the logo's own size: 14 px for the HAWKI wordmark, 28 px for an uploaded logo.
        'logo_height' => env('BRAND_LOGO_HEIGHT') ? (int) env('BRAND_LOGO_HEIGHT') : null,
        // Operating institution, shown on the sign-in page. Empty = not shown.
        'organization_name' => env('BRAND_ORGANIZATION_NAME'),
        'organization_url' => env('BRAND_ORGANIZATION_URL'),
    ],

    'legal' => [
        // Legal links shown on the sign-in page and in the profile menu. Empty = no link in the UI.
        'imprint_url' => env('IMPRINT_LOCATION'),
        'privacy_url' => env('PRIVACY_POLICY_URL', '/dataprotection'),
        'terms_url' => env('TERMS_OF_USE_URL'),
    ],

    'security' => [
        'passkey' => [
            'auto_generate' => filter_var(env('APP_SECURITY_PASSKEY_AUTO_GENERATE', false), FILTER_VALIDATE_BOOLEAN),
            'allow_paste' => filter_var(env('APP_SECURITY_PASSKEY_ALLOW_PASTE', true), FILTER_VALIDATE_BOOLEAN),
            'char_limitation'=> filter_var(env('APP_SECURITY_PASSKEY_CHAR_LIMITATION', true), FILTER_VALIDATE_INT),
        ],
    ],
];
