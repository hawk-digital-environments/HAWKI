<?php

namespace App\Console\Commands;

use App\Models\AppCss;
use Illuminate\Console\Command;

class CleanupAppCss extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-css';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove non-custom CSS entries from database (only keep custom-styles)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 Cleaning up AppCss database...');
        $this->newLine();

        // CSS names that should NOT be in the database (loaded from filesystem instead)
        $fileSystemCss = [
            'style',
            'login_style',
            'settings_style',
            'handshake_style',
            'chat_modules',
            'home-style',
        ];

        $deleted = 0;
        foreach ($fileSystemCss as $name) {
            $count = AppCss::where('name', $name)->delete();
            if ($count > 0) {
                $this->line("✓ Deleted CSS: {$name}");
                $deleted += $count;
            }
        }

        // Verify custom-styles is still there (or will be created by seeder)
        $customStylesExists = AppCss::where('name', 'custom-styles')->exists();
        if ($customStylesExists) {
            $this->line('✓ Kept custom-styles in database');
        } else {
            $this->line('ℹ custom-styles not in database (will be created by seeder)');
        }

        $this->newLine();
        $this->info("✅ Cleanup complete! Deleted {$deleted} CSS entries.");
        $this->info('   CSS is now loaded from:');
        $this->line('   - Filesystem: /public/css/{name}.css');
        $this->line('   - Database: only custom-styles.css');

        return Command::SUCCESS;
    }
}
