<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\AI\Config\AiConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Command to switch between config-based and database-based AI configuration
 *
 * Usage:
 *   php artisan ai:switch-config database  # Switch to database mode
 *   php artisan ai:switch-config config    # Switch to config mode
 *   php artisan ai:switch-config status    # Show current mode
 */
class AiSwitchConfigCommand extends Command
{
    protected $signature = 'ai:switch-config {mode : Mode to switch to (database|config|status)}';

    protected $description = 'Switch between config-based and database-based AI configuration';

    public function __construct(private readonly AiConfigService $aiConfigService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = $this->argument('mode');

        switch ($mode) {
            case 'database':
                return $this->switchToDatabase();
            case 'config':
                return $this->switchToConfig();
            case 'status':
                return $this->showStatus();
            default:
                $this->error("Invalid mode '{$mode}'. Use 'database', 'config', or 'status'.");
                return Command::FAILURE;
        }
    }

    private function switchToDatabase(): int
    {
        $this->info('Switching to database-based AI configuration...');

        $setting = AppSetting::where('key', 'hawki_ai_config_system')->first();
        if ($setting) {
            $setting->update(['value' => 'true']);
        } else {
            AppSetting::create([
                'key' => 'hawki_ai_config_system',
                'value' => 'true',
                'source' => 'hawki',
                'type' => 'boolean',
                'description' => 'DB-based AI configuration system',
                'group' => 'basic'
            ]);
        }

        $this->clearCaches();
        $this->showCurrentConfiguration();
        $this->info('✅ Successfully switched to database mode.');

        return Command::SUCCESS;
    }

    private function switchToConfig(): int
    {
        $this->info('Switching to config-based AI configuration...');

        $setting = AppSetting::where('key', 'hawki_ai_config_system')->first();
        if ($setting) {
            $setting->update(['value' => 'false']);
        }

        $this->clearCaches();
        $this->showCurrentConfiguration();
        $this->info('✅ Successfully switched to config mode.');

        return Command::SUCCESS;
    }

    private function showStatus(): int
    {
        $this->info('Current AI Configuration Status:');
        $this->showCurrentConfiguration();

        return Command::SUCCESS;
    }

    private function clearCaches(): void
    {
        $this->info('Clearing caches...');
        Cache::flush();
        $this->aiConfigService->clearCache();
        $this->call('config:clear');
    }

    private function showCurrentConfiguration(): void
    {
        $isDatabaseMode = config('hawki.ai_config_system');
        $mode = $isDatabaseMode ? 'database' : 'config';
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['AI Config Mode', $mode],
                ['Source', $isDatabaseMode ? 'ai_assistants table' : 'model_providers.php'],
                ['Switch via', 'hawki.ai_config_system config']
            ]
        );

        // Show sample configuration
        try {
            $defaultModels = $this->aiConfigService->getDefaultModels();
            $systemModels = $this->aiConfigService->getSystemModels();

            $this->info("\nCurrent Configuration:");
            $this->line("Default Models: " . count($defaultModels) . " configured");
            foreach ($defaultModels as $type => $modelId) {
                $this->line("  - {$type}: {$modelId}");
            }

            $this->line("System Models: " . count($systemModels) . " configured");
            foreach ($systemModels as $type => $modelId) {
                $this->line("  - {$type}: {$modelId}");
            }

        } catch (\Exception $e) {
            $this->error("Error loading configuration: " . $e->getMessage());
        }
    }
}