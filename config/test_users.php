<?php

return [
    
    'active' => env('TEST_USER_LOGIN', false),

    'testers' => json_decode(file_get_contents(storage_path('app/test_users.json')), true),

];