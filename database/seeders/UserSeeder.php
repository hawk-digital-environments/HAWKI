<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate([
            'name' => 'AI',
            'username' => 'HAWKI',
            'email' => 'HAWKI@hawk.de',
            'employeetype' => 'AI',
            'publicKey' => '0',
            'avatar_id' => 'hawkiAvatar.jpg'
        ]);
    }
}
