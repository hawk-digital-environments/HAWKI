<?php

namespace App\Console\Commands;

use App\Services\TextImport\TextImportService;
use Illuminate\Console\Command;

class SeedTextsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'texts:seed {--force : Force update existing texts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import system and localized texts from resource files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $forceUpdate = $this->option('force');
        $textImportService = new TextImportService;

        $this->info('Importing texts from resource files...');

        if ($forceUpdate) {
            $this->warn('Force update mode: existing texts will be overwritten');
        } else {
            $this->info('Safe mode: only new texts will be added');
        }

        // Import system texts
        $systemCount = $textImportService->importSystemTexts($forceUpdate);
        $this->info("System texts: {$systemCount} processed");

        // Import localized texts
        $localizedCount = $textImportService->importLocalizedTexts($forceUpdate);
        $this->info("Localized texts: {$localizedCount} processed");

        $total = $systemCount + $localizedCount;
        $action = $forceUpdate ? 'imported/updated' : 'imported (no updates)';

        $this->newLine();
        $this->info("✅ Text import completed: {$total} texts {$action}");

        return Command::SUCCESS;
    }
}
