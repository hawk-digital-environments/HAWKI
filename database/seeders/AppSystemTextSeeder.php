<?php

namespace Database\Seeders;

use App\Models\AppSystemText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class AppSystemTextSeeder extends Seeder
{
    /**
     * Seed the application's system texts.
     *
     * @return void
     */
    public function run()
    {
        // Default to safe mode for regular seeders (Docker deployment)
        $forceUpdate = false;
        
        $textImportService = new \App\Services\TextImport\TextImportService();
        $count = $textImportService->importSystemTexts($forceUpdate);
        
        $action = $forceUpdate ? 'updated' : 'created (no updates)';
        Log::info("AppSystemText seeder completed: {$count} texts {$action}");
        $this->command->info("AppSystemText seeder completed: {$count} texts {$action}");
    }
}
