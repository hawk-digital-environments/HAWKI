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

];
