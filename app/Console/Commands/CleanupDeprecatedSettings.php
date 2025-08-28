<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanupDeprecatedSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:cleanup-deprecated 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompts}
                            {--target-version= : Only cleanup keys from specific version}
                            {--auto-detect : Also detect potentially deprecated keys by pattern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove deprecated configuration keys from the database';

    private array $deprecated;
    private array $rules;
    private array $deleted = [];
    private array $backed_up = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->deprecated = config('deprecated');
        $this->rules = $this->deprecated['cleanup_rules'] ?? [];

        $this->info('🧹 HAWKI Configuration Cleanup Tool');
        $this->newLine();

        // Load deprecated keys
        $keysToDelete = $this->getDeprecatedKeys();
        
        if ($this->option('auto-detect')) {
            $autoDetected = $this->autoDetectDeprecatedKeys();
            if (!empty($autoDetected)) {
                $this->warn('Auto-detected potentially deprecated keys:');
                foreach ($autoDetected as $key) {
                    $this->line("  - {$key}");
                }
                
                if ($this->confirm('Include auto-detected keys in cleanup?', false)) {
                    $keysToDelete = array_merge($keysToDelete, $autoDetected);
                }
                $this->newLine();
            }
        }

        if (empty($keysToDelete)) {
            $this->info('✅ No deprecated keys found in database.');
            return Command::SUCCESS;
        }

        // Show what will be deleted
        $this->displayKeysForDeletion($keysToDelete);

        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run completed. No changes were made.');
            return Command::SUCCESS;
        }

        // Confirmation
        if (!$this->option('force') && ($this->rules['require_confirmation'] ?? true)) {
            if (!$this->confirm('Proceed with deletion?', false)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Backup if configured
        if ($this->rules['backup_before_delete'] ?? true) {
            $this->createBackup($keysToDelete);
        }

        // Delete deprecated keys
        $this->deleteKeys($keysToDelete);

        // Summary
        $this->displaySummary();

        return Command::SUCCESS;
    }

    private function getDeprecatedKeys(): array
    {
        $keys = [];
        $targetVersion = $this->option('target-version');

        foreach ($this->deprecated as $versionKey => $versionKeys) {
            if ($versionKey === 'cleanup_rules' || $versionKey === 'auto_detect_patterns') {
                continue;
            }

            if ($targetVersion && $versionKey !== $targetVersion) {
                continue;
            }

            foreach ($versionKeys as $key => $reason) {
                if (AppSetting::where('key', $key)->exists()) {
                    $keys[$key] = [
                        'version' => $versionKey,
                        'reason' => $reason
                    ];
                }
            }
        }

        return $keys;
    }

    private function autoDetectDeprecatedKeys(): array
    {
        $patterns = $this->deprecated['auto_detect_patterns'] ?? [];
        $detected = [];

        if (empty($patterns)) {
            return $detected;
        }

        $allKeys = AppSetting::pluck('key')->toArray();

        foreach ($allKeys as $key) {
            // Check prefixes
            foreach ($patterns['prefixes'] ?? [] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $detected[] = $key;
                    continue 2;
                }
            }

            // Check contains
            foreach ($patterns['contains'] ?? [] as $contains) {
                if (str_contains($key, $contains)) {
                    $detected[] = $key;
                    continue 2;
                }
            }
        }

        return array_unique($detected);
    }

    private function displayKeysForDeletion(array $keys): void
    {
        $this->warn('📋 Keys scheduled for deletion:');
        $this->newLine();

        $table = [];
        foreach ($keys as $key => $info) {
            if (is_array($info)) {
                $table[] = [
                    $key,
                    $info['version'] ?? 'auto-detected',
                    $info['reason'] ?? 'Pattern-based detection'
                ];
            } else {
                $table[] = [$key, 'auto-detected', 'Pattern-based detection'];
            }
        }

        $this->table(['Key', 'Version', 'Reason'], $table);
        $this->newLine();
    }

    private function createBackup(array $keys): void
    {
        $backupPath = $this->rules['backup_path'] ?? storage_path('app/config_backups');
        
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "deprecated_settings_backup_{$timestamp}.json";
        $filepath = $backupPath . '/' . $filename;

        $backupData = [];
        foreach (array_keys($keys) as $key) {
            $setting = AppSetting::where('key', $key)->first();
            if ($setting) {
                $backupData[$key] = [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'group' => $setting->group,
                    'created_at' => $setting->created_at,
                    'updated_at' => $setting->updated_at,
                ];
            }
        }

        File::put($filepath, json_encode($backupData, JSON_PRETTY_PRINT));
        
        $this->info("💾 Backup created: {$filename}");
        $this->backed_up[] = $filepath;
    }

    private function deleteKeys(array $keys): void
    {
        $this->info('🗑️  Deleting deprecated keys...');

        $progressBar = $this->output->createProgressBar(count($keys));
        $progressBar->start();

        foreach (array_keys($keys) as $key) {
            $setting = AppSetting::where('key', $key)->first();
            if ($setting) {
                $setting->delete();
                $this->deleted[] = $key;

                if ($this->rules['log_operations'] ?? true) {
                    Log::info("Deleted deprecated config key: {$key}", [
                        'command' => 'config:cleanup-deprecated',
                        'value' => $setting->value,
                        'group' => $setting->group,
                    ]);
                }
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
    }

    private function displaySummary(): void
    {
        $this->info('📊 Cleanup Summary:');
        $this->newLine();

        $this->line("✅ Deleted keys: " . count($this->deleted));
        
        if (!empty($this->backed_up)) {
            $this->line("💾 Backup files: " . count($this->backed_up));
            foreach ($this->backed_up as $backup) {
                $this->line("   - " . basename($backup));
            }
        }

        if (!empty($this->deleted)) {
            $this->newLine();
            $this->info('Deleted keys:');
            foreach ($this->deleted as $key) {
                $this->line("  - {$key}");
            }
        }

        $this->newLine();
        $this->info('🎉 Cleanup completed successfully!');
    }
}
