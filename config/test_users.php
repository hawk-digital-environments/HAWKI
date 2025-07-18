<?php

return [
    
    'active' => env('TEST_USER_LOGIN', false),

    'testers' => file_exists(storage_path('app/test_users.json'))
        ? json_decode(file_get_contents(storage_path('app/test_users.json')), true)
        : [],

];