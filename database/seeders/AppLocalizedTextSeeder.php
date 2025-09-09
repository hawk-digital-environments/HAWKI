<?php

namespace Database\Seeders;

use App\Models\AppLocalizedText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AppLocalizedTextSeeder extends Seeder
{
    /**
     * Seed the application's localized texts.
     *
     * @return void
     */
    public function run()
    {
        // Default to safe mode for regular seeders (Docker deployment)
        $forceUpdate = false;
        
        $textImportService = new \App\Services\TextImport\TextImportService();
        $count = $textImportService->importLocalizedTexts($forceUpdate);
        
        $action = $forceUpdate ? 'updated' : 'created (no updates)';
        Log::info("AppLocalizedText seeder completed: {$count} contents {$action}");
        $this->command->info("AppLocalizedText seeder completed: {$count} contents {$action}");
    }

        // Remove the old buildDynamicFileMapping method since it's now in the service
}

