<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get deprecated keys for v2.0.1
        $deprecated = config('deprecated.v2.0.1', []);
        
        if (empty($deprecated)) {
            return;
        }

        // Create backup before deletion
        $this->createBackup($deprecated);
        
        // Delete deprecated keys
        foreach (array_keys($deprecated) as $key) {
            $setting = AppSetting::where('key', $key)->first();
            if ($setting) {
                Log::info("Migration cleanup: Deleted deprecated config key: {$key}", [
                    'migration' => '2025_08_28_151412_cleanup_deprecated_settings_v2_0_1',
                    'value' => $setting->value,
                    'group' => $setting->group,
                    'reason' => $deprecated[$key],
                ]);
                
                $setting->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // In case we need to rollback, we could restore from backup
        // but typically deprecated settings shouldn't be restored
        
        Log::warning('Rollback of deprecated settings cleanup attempted', [
            'migration' => '2025_08_28_151412_cleanup_deprecated_settings_v2_0_1',
            'note' => 'Deprecated settings were not restored. Check backup files if needed.',
        ]);
    }

    /**
     * Create a backup of deprecated settings before deletion
     */
    private function createBackup(array $deprecated): void
    {
        $backupPath = storage_path('app/config_backups');
        
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "migration_backup_v2_0_1_{$timestamp}.json";
        $filepath = $backupPath . '/' . $filename;

        $backupData = [
            'migration' => '2025_08_28_151412_cleanup_deprecated_settings_v2_0_1',
            'timestamp' => $timestamp,
            'deprecated_keys' => [],
        ];

        foreach (array_keys($deprecated) as $key) {
            $setting = AppSetting::where('key', $key)->first();
            if ($setting) {
                $backupData['deprecated_keys'][$key] = [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'group' => $setting->group,
                    'reason' => $deprecated[$key],
                    'created_at' => $setting->created_at,
                    'updated_at' => $setting->updated_at,
                ];
            }
        }

        file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT));
        
        Log::info("Migration backup created: {$filename}", [
            'migration' => '2025_08_28_151412_cleanup_deprecated_settings_v2_0_1',
            'backup_path' => $filepath,
            'keys_backed_up' => count($backupData['deprecated_keys']),
        ]);
    }
};
