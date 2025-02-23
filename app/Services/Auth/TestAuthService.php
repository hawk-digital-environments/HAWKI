<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TestAuthService
{
    protected $users;

    public function __construct()
    {
        $this->users = config('test_users')['testers'];
    }

    public function authenticate($username, $password)
    {
        if($this->users === null){
            return null;
        }

        $user = collect($this->users)->first(function ($user) use ($username, $password) {
            return $user['username'] === $username && $user['password'] === $password;
        });

        if ($user) {
            return [
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
                'employeetype' => 'tester',
            ];
        }

        return null;
    }
}