<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration {
    public function up(): void
    {
        \Artisan::call('migrate:avatars', ['--cleanup' => true, '--force' => true], new ConsoleOutput());
    }
    
    public function down(): void
    {
    }
};
