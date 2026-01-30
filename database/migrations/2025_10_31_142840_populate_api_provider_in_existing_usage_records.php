<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration populates the api_provider field for existing usage_records
     * by calling an interactive Artisan command.
     */
    public function up(): void
    {
        \Artisan::call('usage-records:populate-api-provider', ['--force' => true], new ConsoleOutput());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally reset all api_provider values to NULL
        // DB::table('usage_records')->update(['api_provider' => null]);
    }
};
