<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Assistant Versions
    |--------------------------------------------------------------------------
    |
    | Configuration for the assistant version-history listener. When an
    | assistant is updated, consecutive changes within the debounce window
    | are merged into the most recent version record instead of creating
    | a new one.
    |
     */

    'assistant_versions' => [
        'debounce_seconds' => env('ASSISTANT_VERSIONS_DEBOUNCE_SECONDS', 10),
    ],
    /*
    |--------------------------------------------------------------------------
    | Assistant Builder
    |--------------------------------------------------------------------------
    |
    | The builder's own knobs. The guide chat's default model seeds the
    | `assistant_guide` system model during config sync (the Assistant
    | service provider carries it into the system-model registry); admins
    | can still override it per instance through the system-models UI.
    |
     */

    'builder' => [
        'guide_model' => env('ASSISTANT_GUIDE_MODEL', env('DEFAULT_MODEL', 'gpt-6.1-terra')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Share Links
    |--------------------------------------------------------------------------
    |
    | How long a generated share link stays redeemable. Every copy mints a
    | new link; expired links are removed by the scheduled model:prune.
    |
     */

    'share_links' => [
        'ttl_hours' => (int) env('ASSISTANT_SHARE_LINK_TTL_HOURS', 24),
    ],
];
