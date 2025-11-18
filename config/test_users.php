<?php

$testUsersEnabled = env('TEST_USER_LOGIN', false);

return [

    'active' => $testUsersEnabled,

    'testers' => (static function () use ($testUsersEnabled) {
        if (!$testUsersEnabled) {
            return [];
        }

        $testUsersFile = storage_path('app/test_users.json');
        if (!is_file($testUsersFile)) {
            return [];
        }

        try {
            $testUsers = json_decode(file_get_contents($testUsersFile), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($testUsers)) {
                return [];
            }
            return $testUsers;
        } catch (\Throwable) {
            return [];
        }
    })(),
];
