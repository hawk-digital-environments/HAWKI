<?php

return [

    'versions' => [
        /*
         |----------------------------------------------------------------------
         | Debounce window (seconds)
         |----------------------------------------------------------------------
         | Consecutive assistant changes within this window collapse into a
         | single version entry. The window slides: each merged change
         | refreshes the version's updated_at, extending the deadline for the
         | next change. A change arriving after the window elapses starts a
         | new version.
         */
        'debounce_seconds' => env('ASSISTANT_VERSION_DEBOUNCE_SECONDS', 10),
    ],

];
