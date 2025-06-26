<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Support\Facades\Toast;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;

use Illuminate\Support\Facades\Log;
use Orchid\Support\Color;
use Orchid\Screen\Fields\Code;

use Orchid\Support\Facades\Alert;

class LogScreen extends Screen
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Construct the screen
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $log = file_exists(storage_path('logs/laravel.log'))
            ? file_get_contents(storage_path('logs/laravel.log'))
            : 'Log-Datei nicht gefunden.';
        
        // Fetch logging settings from database - search for the correct key format
        $loggingSettings = AppSetting::where('key', 'LIKE', 'logging_%')->get();
        
        return [
            'logs' => $log,
            'logging_settings' => $loggingSettings,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Laravel Log';
    }

    public function description(): ?string
    {
        return 'Configure what gets logged to the Laravel log file.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Clear Log')
                ->icon('trash')
                ->method('clearLog'),
            Button::make('Test Log')
                ->icon('umbrella')
                ->method('testLog'),  
            Button::make('Refresh Log')
                ->icon('arrow-clockwise')
                ->method('refreshLog'),
            Button::make('Save Settings')
                ->icon('save')
                ->method('saveSettings'),
        ];
    }

    /**
     * Clear the log file.
     */
    public function clearLog()
    {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            Toast::success('Log cleared.');
        } else {
            Toast::error('Log file not found.');
        }
    }

    public function testLog()
    {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            //file_put_contents($logFile, PHP_EOL . 'Testing!' . PHP_EOL, FILE_APPEND);
        } else {
            Toast::error('Log file not found.');
        }
        Log::info("message");
        Log::error("message");
    }

    public function refreshLog()
    {
    
    }

    public function buttonClickProcessing(): void
    {
        Toast::warning('Click Processing');
        Log::info("message");
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            $this->buildLoggingSettingsLayout(),
            
            Layout::rows([
                Code::make('logs')
                    ->title('Laravel Log')
                    ->readonly(true)
                    ->height("70dvh"),
            ]),
        ];
    }

    /**
     * Build layout for logging settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildLoggingSettingsLayout()
    {
        $fields = [];

        foreach ($this->query()['logging_settings'] as $setting) {
            $fields[] = $this->getFieldForSetting($setting);
        }

        return Layout::rows($fields)->title('Log Triggers');
    }

    /**
     * Updates the settings.
     */
    public function updateSettings()
    {
        $localInfo  = request('localInfo');
        $localError = request('localError');
        
        // Save filters in Session:
        session()->put('localInfo', $localInfo);
        session()->put('localError', $localError);
        
        Toast::info('Filter settings updated.');
    }

    /**
     * Create the appropriate form field based on setting type
     *
     * @param AppSetting $setting
     * @return \Orchid\Screen\Field|\Orchid\Screen\Fields\Group
     */
    private function getFieldForSetting(AppSetting $setting)
    {
        $key = $setting->key;
        $displayKey = $this->getConfigKeyFromDbKey($key);

        // Follow the same pattern as SystemSettingsScreen
        switch ($setting->type) {
            case 'boolean':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Switcher::make("settings.{$key}")
                        ->sendTrueOrFalse()
                        ->value($setting->typed_value),
                ])
                ->alignCenter()
                ->widthColumns('1fr max-content');
                
            default:
                // For any non-boolean types, still use Switcher but convert appropriately
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Switcher::make("settings.{$key}")
                        ->sendTrueOrFalse()
                        ->value($setting->typed_value),
                ])
                ->alignCenter()
                ->widthColumns('1fr max-content');
        }
    }

    /**
     * Convert database key format to Laravel config key format
     * 
     * @param string $dbKey Database key with underscore notation
     * @return string Config key in dot notation
     */
    private function getConfigKeyFromDbKey(string $dbKey): string
    {
        // Handle keys that already contain dots (like logging_triggers.return_object)
        if (str_contains($dbKey, '.')) {
            // Replace the first underscore with a dot
            $pos = strpos($dbKey, '_');
            if ($pos !== false) {
                $configFile = substr($dbKey, 0, $pos);
                $realKey = substr($dbKey, $pos + 1);
                return $configFile . '.' . $realKey;
            }
        }
        
        // Original logic for simple underscore keys
        $pos = strpos($dbKey, '_');
        
        if ($pos === false) {
            return $dbKey;
        }
        
        $configFile = substr($dbKey, 0, $pos);
        $realKey = substr($dbKey, $pos + 1);
        
        return $configFile . '.' . $realKey;
    }

    /**
     * Save logging settings to the database
     *
     * @param Request $request
     * @return void
     */
    public function saveSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        $count = 0;
        
        // Debug: Log what we received
        //Log::info('LogScreen received settings:', $settings);
        
        // Flatten the nested array structure
        $flattenedSettings = $this->flattenSettings($settings);
        //Log::info('LogScreen flattened settings:', $flattenedSettings);
        
        if ($flattenedSettings) {
            // Collect only entries that have actually changed
            $changedSettings = [];
            
            foreach ($flattenedSettings as $key => $value) {
                // Only save password fields if they are not empty (from SystemSettingsScreen pattern)
                if ((str_contains($key, 'bind_pw') || str_contains($key, 'password') || str_contains($key, 'secret')) && empty($value)) {
                    continue;
                }
                
                // Since the keys are already stored with underscores in the database, we can search directly
                $setting = AppSetting::where('key', $key)->first();
                
                if ($setting) {
                    // Transform the value based on its type for proper comparison
                    $normalizedNewValue = $this->normalizeValue($value, $setting->type);
                    $normalizedExistingValue = $this->normalizeValue($setting->value, $setting->type);
                    
                    // Debug logging
                    //Log::info("Key: {$key}, New: " . json_encode($normalizedNewValue) . ", Existing: " . json_encode($normalizedExistingValue));
                    
                    // Comparison of normalized values for change detection
                    if ($normalizedExistingValue !== $normalizedNewValue) {
                        $changedSettings[] = [
                            'key' => $key,
                            'value' => $normalizedNewValue,
                            'type' => $setting->type,
                            'model' => $setting
                        ];
                    }
                } else {
                    Toast::warning("Setting not found: {$key}");
                }
            }
            
            // Debug: Log what changed
            //Log::info('Changed settings count:', ['count' => count($changedSettings)]);
            
            // Perform updates only for changed settings
            foreach ($changedSettings as $changed) {
                $setting = $changed['model'];
                $formattedValue = $this->formatValueForStorage($changed['value'], $changed['type']);
                
                // Update the setting
                $setting->value = $formattedValue;
                $setting->save();
                
                // Clear cache for this setting
                Cache::forget('app_settings_' . $setting->key);
                
                // Also clear the config cache for this source, if it exists
                if ($setting->source) {
                    Cache::forget('config_' . $setting->source);
                }
                
                $count++;
            }
            
            // User feedback based on the changes (exactly like SystemSettingsScreen)
            if ($count > 0) {
                // Clear the entire configuration cache
                try {
                    Artisan::call('config:clear');
                    
                    // Clear the specific config override cache
                    \App\Providers\ConfigServiceProvider::clearConfigCache();
                    
                    Toast::success("{$count} system settings have been updated, and the configuration cache has been cleared.");
                } catch (\Exception $e) {
                    Toast::warning("Settings saved, but clearing cache failed: " . $e->getMessage());
                }
            } else {
                Toast::info("No changes detected");
            }
        }
        
        return;
    }

    /**
     * Flatten nested settings array to dot notation
     *
     * @param array $settings
     * @param string $prefix
     * @return array
     */
    private function flattenSettings(array $settings, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($settings as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenSettings($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Normalize a value for comparison based on its type
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private function normalizeValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                // Convert to a boolean value for consistent comparison
                if (is_string($value)) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                return (bool) $value;
                
            case 'integer':
                // Convert to an integer for consistent comparison
                return (int) $value;
                
            case 'json':
                // Decode JSON strings into arrays/objects for structural comparison
                if (is_string($value)) {
                    try {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $decoded;
                        }
                    } catch (\Exception $e) {
                        // In case of an error, return the original value
                    }
                }
                // If it's already an array or decoding fails
                return $value;
                
            default:
                // Return as string for strings and other types
                return (string) $value;
        }
    }

    /**
     * Format a value for database storage based on its type
     *
     * @param mixed $value
     * @param string $type
     * @return string
     */
    private function formatValueForStorage($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                // Store as 'true' or 'false' string
                return (filter_var($value, FILTER_VALIDATE_BOOLEAN)) ? 'true' : 'false';
                
            case 'integer':
                // Convert to string for storage
                return (string) (int) $value;
                
            case 'json':
                // Convert array/object to JSON string
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                } elseif (is_string($value)) {
                    try {
                        // Attempt to decode and re-encode to ensure a consistent format
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return json_encode($decoded);
                        }
                    } catch (\Exception $e) {
                        // In case of an error: Return the original value if it is a valid JSON string
                    }
                }
                return $value;
                
            default:
                // Return as string for all other types
                return (string) $value;
        }
    }
}