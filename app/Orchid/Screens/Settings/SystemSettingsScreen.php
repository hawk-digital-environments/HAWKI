<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use App\Http\Controllers\TestConfigValueController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Screen\Layouts\Table;

use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\App;

class SystemSettingsScreen extends Screen
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
     * Query data.
     *
     * @return array
     */
    public function query(): iterable
    {
        $basicSettings = AppSetting::where('group', 'basic')->get();
        $authSettings = AppSetting::where('group', 'authentication')->get();
        $apiSettings = AppSetting::where('group', 'api')->get();

        // Fetch test configuration data
        $configData = [];
        $rawResponse = '';
        try {
            // Direct invocation of the controller instead of an HTTP request
            $controller = new TestConfigValueController();
            $response = $controller->__invoke();
            $responseData = $response->getData(true);
            $rawResponse = json_encode($responseData, JSON_PRETTY_PRINT);
            
            // Extract config_values
            $configValues = $responseData['config_values'] ?? [];
            
            // Prepare data for table display
            foreach ($configValues as $key => $values) {
                $configData[] = [
                    'key'           => $key,
                    'config_value'  => $values['config_value'] ?? 'N/A',
                    'env_value'     => $values['env_value'] ?? 'N/A',
                    'db_value'      => $values['db_value'] ?? 'N/A',
                    'config_source' => $values['config_source'] ?? 'N/A',
                ];
            }
        } catch (\Exception $e) {
            $configData[] = [
                'key'           => 'Error',
                'config_value'  => 'Failed to retrieve configuration data: ' . $e->getMessage(),
                'env_value'     => 'N/A',
                'db_value'      => 'N/A',
                'config_source' => 'N/A',
            ];
            $rawResponse = "Error fetching data: " . $e->getMessage();
        }

        return [
            'basic' => $basicSettings,
            'authentication' => $authSettings,
            'api' => $apiSettings,
            'config_data' => $configData,
            'raw_response' => $rawResponse,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'System Settings';
    }
    public function description(): ?string
    {
        return 'Customize the system settings.';
    }
    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make($this->isMaintenanceModeActive() ? 'Unlock System' : 'Lock System')
                ->icon($this->isMaintenanceModeActive() ? 'bs.lock' : 'bs.unlock')
                ->confirm($this->isMaintenanceModeActive() 
                    ? 'This will disable the maintenance mode. The website will be available to all users.' 
                    : 'This will put the application into maintenance mode. Only users with bypass access will be able to access the site.')
                ->method('toggleMaintenanceMode'),
                   
            Button::make('Save')
                ->icon('save')
                ->method('saveSettings'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        // Build the layouts for each tab
        $basicSettings = $this->buildBasicSettingsLayout();
        $authSettings = $this->buildAuthSettingsLayout();
        $apiSettings = $this->buildApiSettingsLayout();
        $test = $this->buildTestSettingsLayout();
        
        return [
            Layout::tabs([
                'System' => $basicSettings,
                'Authentication' => $authSettings,
                'API' => $apiSettings,
                'Testing' => $test,
            ]),
        ];
        
    }
    /**
     * Build layout for test settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildTestSettingsLayout()
    {
        // Get system information
        Artisan::call('about');
        $aboutOutput = Artisan::output();
        
        return Layout::block([
    
        
            Layout::rows([
                Label::make('system_info_label')
                    ->title('System Information')
                    ->popover('<Artisan about> outputs a mixed collection of config and .env values, so not all config values are represented correctly.'),
                Code::make('system_info')
                    ->language('shell')
                    ->readonly()
                    ->value($aboutOutput)
                    ->height('550px'),
            ]),
            
            Layout::rows([
                Label::make('config_test_label')
                    ->title('Configuration Source Testing')
                    ->help('This shows where each configuration value is coming from: database, environment variables, or defaults')
                    ->addclass('fw-bold'),
            ]),
            

            
            // Use table layout with data from query - add render methods to handle array data
            Layout::table('config_data', [
                TD::make('key', 'Configuration Key')
                    ->sort()
                    ->filter(Input::make())
                    ->width('200px')
                    ->render(function ($row) {
                        return $row['key'];
                    }),
                    
                TD::make('env_value', 'Environment Value')
                    ->width('250px')
                    ->render(function ($row) {
                        return $this->truncateValue($row['env_value']);
                    }),
                    
                TD::make('db_value', 'Database Value')
                    ->width('250px')
                    ->render(function ($row) {
                        return $this->truncateValue($row['db_value']);
                    }),
                
                TD::make('config_value', 'Config Value')
                    ->width('250px')
                    ->render(function ($row) {
                        return $this->truncateValue($row['config_value']);
                    }),    
            
                TD::make('config_source', 'Source')
                    ->width('120px')
                    ->align(TD::ALIGN_CENTER)
                    ->render(function ($row) {
                        // Highlight the source with a badge
                        $sourceClass = match($row['config_source']) {
                            'database' => 'bg-primary',
                            'environment' => 'bg-success',
                            'default' => 'bg-info',
                            default => 'bg-secondary'
                        };
                        
                        return "<span class='badge {$sourceClass}'>{$row['config_source']}</span>";
                    }),
            ]),
            
        
        ])->vertical();
    }
    
    /**
     * Helper function to truncate long values for display
     *
     * @param string $value
     * @param int $length
     * @return string
     */
    private function truncateValue($value, $length = 30)
    {
        if (is_string($value) && strlen($value) > $length) {
            return substr($value, 0, $length) . '...';
        }
        
        return $value;
    }

    /**
     * Build layout for basic settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildBasicSettingsLayout()
    {
        $generalSettings = [];
        $systemSettings = [];
        $chatSettings = [];

        // Gruppiere die Einstellungen nach Kategorien
        foreach ($this->query()['basic'] as $setting) {
            $key = $setting->key;
            
            // Gruppierung der Settings basierend auf ihrem Key
            if (in_array($key, ['app_name'])) {
                $generalSettings[] = $this->getFieldForSetting($setting);
            } else if (in_array($key, ['app_url', 'app_env', 'app_timezone', 'app_locale', 'app_debug'])) {
                $systemSettings[] = $this->getFieldForSetting($setting);
            } else if (str_contains($key, 'groupchat') || str_contains($key, 'ai_handle')) {
                $chatSettings[] = $this->getFieldForSetting($setting);
            } else {
                // Fallback für neue/unbekannte Einstellungen
                $generalSettings[] = $this->getFieldForSetting($setting);
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];
        
        // Allgemeine Anwendungseinstellungen
        if (!empty($generalSettings)) {
            $layouts[] = Layout::rows($generalSettings)
                ->title('Base Settings');
        }
        
        // System-Einstellungen
        if (!empty($systemSettings)) {
            $layouts[] = Layout::rows($systemSettings)
                ->title('System Settings');
        }
        
        // Chat-Einstellungen
        if (!empty($chatSettings)) {
            $layouts[] = Layout::rows($chatSettings)
                ->title('App Feature Settings');
        }
        
        return $layouts;
    }

    /**
     * Build layout for authentication settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildAuthSettingsLayout()
    {
        $authMethodSetting = null;
        $testUserSettings = [];
        $ldapSettings = [];
        $oidcSettings = [];
        $shibbolethSettings = [];
        $passkeySettings = [];
        $otherAuthSettings = []; // Fallback für nicht kategorisierte Settings

        // Alle Authentifizierungseinstellungen nach Typ sortieren
        foreach ($this->query()['authentication'] as $setting) {
            if ($setting->key === 'auth_authentication_method') {
                $authMethodSetting = $setting;
            } else if (str_starts_with($setting->key, 'test_users_')) {
                $testUserSettings[] = $this->getFieldForSetting($setting);
            } else if (str_starts_with($setting->key, 'ldap_')) {
                $ldapSettings[] = $this->getFieldForSetting($setting);
            } else if (str_starts_with($setting->key, 'open_id_connect_')) {
                $oidcSettings[] = $this->getFieldForSetting($setting);
            } else if (str_starts_with($setting->key, 'shibboleth_')) {
                $shibbolethSettings[] = $this->getFieldForSetting($setting);
            } else if (str_starts_with($setting->key, 'auth_passkey_')) {
                $passkeySettings[] = $this->getFieldForSetting($setting);
            } else {
                // Fallback für alle anderen Authentication-Settings
                $otherAuthSettings[] = $this->getFieldForSetting($setting);
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];
        
        // Test User Einstellungen als eigenes Layout
        if (!empty($testUserSettings)) {
            $layouts[] = Layout::rows($testUserSettings)
            ->title('Testusers');;
        }
        // Fallback-Layout für nicht kategorisierte Authentication-Settings
        if (!empty($otherAuthSettings)) {
            $layouts[] = Layout::rows($otherAuthSettings)
                ->title('Authentication Settings');
        }
        // Passkey-Einstellungen als eigenes Layout
        if (!empty($passkeySettings)) {
            $layouts[] = Layout::rows($passkeySettings)
                ->title('Passkey Settings');
        }
        // Authentifizierungsmethode in separatem Layout, falls vorhanden
        if ($authMethodSetting) {
            $layouts[] = Layout::rows([
                $this->getFieldForSetting($authMethodSetting)
            ])->title('Change Authentication Method');
            
            // Aktuelle Authentifizierungsmethode ermitteln
            $currentMethod = config('auth.authentication_method', '');
            
            // Alternative Lösung: Anstatt canSee() zu verwenden, die Layouts bedingt hinzufügen
            $upperCurrentMethod = strtoupper($currentMethod);
            
            if (!empty($ldapSettings) && $upperCurrentMethod === 'LDAP') {
                $layouts[] = Layout::rows($ldapSettings)
                    ->title('LDAP Settings');
            }
            
            if (!empty($oidcSettings) && $upperCurrentMethod === 'OIDC') {
                $layouts[] = Layout::rows($oidcSettings)
                    ->title('OpenID Connect Settings');
            }
            
            if (!empty($shibbolethSettings) && $upperCurrentMethod === 'SHIBBOLETH') {
                $layouts[] = Layout::rows($shibbolethSettings)
                    ->title('Shibboleth Settings');
            }
        }
        
        return $layouts;
    }

    /**
     * Build layout for API settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildApiSettingsLayout()
    {
        $fields = [];

        foreach ($this->query()['api'] as $setting) {
            $fields[] = $this->getFieldForSetting($setting);
        }

        return Layout::rows($fields);
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

        //Log::debug('determineType ' . $key);
        // Display certain json values as string
        if ($key === 'ldap_connections_default_hosts' || $key === 'open_id_connect_oidc_scopes') {
            $setting->type = 'string';
        }
        
        // Generiere den korrekten Laravel Config-Namen
        $displayKey = $this->getConfigKeyFromDbKey($key);

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
                
            case 'integer':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Input::make("settings.{$key}")
                        ->type('number')
                        ->value($setting->value)
                        ->horizontal(),
                ])
                ->widthColumns('1fr 1fr');
                
            case 'json':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    TextArea::make("settings.{$key}")
                        ->rows(10)
                        ->value(json_encode($setting->typed_value, JSON_PRETTY_PRINT))
                        ->style('min-width: 100%; resize: vertical;'),  
                ])
                ->widthColumns('1fr 1fr');
            
            case 'string':
                // Special handling for AUTHENTICATION_METHOD which should be a dropdown
                if ($key === 'auth_authentication_method') {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Select::make("settings.{$key}")
                            ->options([
                                'LDAP' => 'LDAP',
                                'OIDC' => 'OpenID Connect',
                                'Shibboleth' => 'Shibboleth',
                            ])
                            ->value($setting->value),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                } 
                // Special handling for PASSKEY_METHOD dropdown
                else if ($key === 'auth_passkey_method') {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Select::make("settings.{$key}")
                            ->options([
                                'user' => 'User (Manual passkey entry)',
                                'auto' => 'Auto (Automatic passkey generation)',
                            ])
                            ->value($setting->value),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                // Special handling for PASSKEY_SECRET dropdown
                else if ($key === 'auth_passkey_secret') {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey),
                        Select::make("settings.{$key}")
                            ->options([
                                'username' => 'Username',
                                'time' => 'Time of registration',
                                'mixed' => 'Name and Time of registration',
                                'publicKey' => 'Public Key',
                                'default' => 'Default',
                            ])
                            ->value($setting->value),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                // Special handling for password fields - especially LDAP bind password
                else if (str_contains($key, 'bind_pw') || str_contains($key, 'password') || str_contains($key, 'secret')) {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Input::make("settings.{$key}")
                            ->type('password')
                            ->value(''),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Input::make("settings.{$key}")
                        ->value($setting->value),
                ])
                ->alignCenter()
                ->widthColumns('1fr 1fr');
        }
    }

    /**
     * Convert database key format to Laravel config key format
     * 
     * @param string $dbKey Database key with underscore notation (e.g., 'app_name')
     * @return string Config key in dot notation (e.g., 'app.name')
     */
    private function getConfigKeyFromDbKey(string $dbKey): string
    {
        // Finde die Position des ersten Unterstriches
        $pos = strpos($dbKey, '_');
        
        if ($pos === false) {
            // Falls kein Unterstrich vorhanden ist, gib den Key unverändert zurück
            return $dbKey;
        }
        
        // Trenne den Key in Konfigurationsdatei und eigentlichen Schlüssel
        $configFile = substr($dbKey, 0, $pos);
        $realKey = substr($dbKey, $pos + 1);
        
        // Erstelle den Config-Key im Format "configFile.realKey"
        return $configFile . '.' . $realKey;
    }

    /**
     * Save settings to the database
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        $count = 0;
        
        // Debug: Log all received settings for troubleshooting
        Log::info('Received settings in saveSettings:', $settings);
        
        // Debug: Check current auth method
        $currentAuthMethod = config('auth.authentication_method', '');
        Log::info('Current auth method: ' . $currentAuthMethod);
        
        // Debug: Check which LDAP settings should be visible
        $ldapSettingsInDb = AppSetting::where('group', 'authentication')
            ->where('key', 'like', 'ldap_%')
            ->pluck('key')
            ->toArray();
        Log::info('LDAP settings in DB: ', $ldapSettingsInDb);
        
        // Debug: Check ALL authentication settings in DB
        $allAuthSettingsInDb = AppSetting::where('group', 'authentication')
            ->pluck('key')
            ->toArray();
        Log::info('All authentication settings in DB: ', $allAuthSettingsInDb);
        
        if ($settings) {
            // Collect only entries that have actually changed
            $changedSettings = [];
            
            foreach ($settings as $key => $value) {
                // Debug: Log each setting being processed
                Log::info("Processing setting: {$key} = " . json_encode($value));
                
                // Special handling for nested LDAP and OIDC settings that come as JSON objects
                if (is_array($value) && (str_starts_with($key, 'ldap_') || str_starts_with($key, 'open_id_connect_'))) {
                    Log::info("Processing nested setting: {$key}");
                    
                    // Handle nested settings - flatten them with dot notation
                    $this->processNestedSettings($key, $value, $changedSettings);
                    continue; // Skip the main key processing since we handled the nested ones
                }
                
                // Only save password fields if they are not empty
                if ((str_contains($key, 'bind_pw') || str_contains($key, 'password') || str_contains($key, 'secret')) && empty($value)) {
                    //Log::info("Skipping empty password field: {$key}");
                    continue;
                }
                
                // Since the keys are already stored with underscores in the database, we can search directly
                $setting = AppSetting::where('key', $key)->first();
                
                if ($setting) {
                    // Transform the value based on its type for proper comparison
                    $normalizedNewValue = $this->normalizeValue($value, $setting->type);
                    $normalizedExistingValue = $this->normalizeValue($setting->value, $setting->type);
                    
                    //Log::info("Comparing {$key}: old='" . json_encode($normalizedExistingValue) . "' new='" . json_encode($normalizedNewValue) . "'");
                    
                    // Comparison of normalized values for change detection
                    if ($normalizedExistingValue !== $normalizedNewValue) {
                        $changedSettings[] = [
                            'key' => $key,
                            'value' => $normalizedNewValue,
                            'type' => $setting->type,
                            'model' => $setting
                        ];
                        //Log::info("Change detected for: {$key}");
                    } else {
                        //Log::info("No change detected for: {$key}");
                    }
                } else {
                    Log::warning("Setting nicht gefunden: {$key}");
                    Toast::warning("Setting nicht gefunden: {$key}");
                }
            }
            
            // Perform updates only for changed settings
            foreach ($changedSettings as $changed) {
                $setting = $changed['model'];
                $formattedValue = $this->formatValueForStorage($changed['value'], $changed['type']);
                
                // Update the setting
                $setting->value = $formattedValue;
                $setting->save();
                
                // Cache für diese Einstellung löschen
                Cache::forget('app_settings_' . $setting->key);
                
                // Also clear the config cache for this source, if it exists
                if ($setting->source) {
                    Cache::forget('config_' . $setting->source);
                }
                
                $count++;
            }
            
            // User feedback based on the changes
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
     * Run the AppSettingsSeeder to import settings from configuration
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function runSettingsSeeder()
    {
        try {
            // Run the AppSettingsSeeder and capture the output
            \Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\AppSettingsSeeder'
            ]);
            
            // Capture the output directly
            $output = \Artisan::output();
            
            // Clear the configuration cache to activate new settings
            Artisan::call('config:clear');
            
            // Clear the specific config override cache
            \App\Providers\ConfigServiceProvider::clearConfigCache();
            
            // Display the unmodified output as a success message
            Toast::success('Settings Seeder Output: ' . PHP_EOL . $output);
            
            // Logging for diagnostic purposes
            Log::info('AppSettingsSeeder Output: ' . $output);
            
        } catch (\Exception $e) {
            Log::error('Error running AppSettingsSeeder: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Toast::error('Failed to run settings seeder: ' . $e->getMessage());
        }
        
        return;
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

    /**
     * Check if the application is in maintenance mode
     * 
     * @return bool
     */
    private function isMaintenanceModeActive(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * Toggle maintenance mode
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleMaintenanceMode()
    {
        if ($this->isMaintenanceModeActive()) {
            Artisan::call('up');
            Toast::success('Maintenance mode has been disabled.');
        } else {
            // Generate a secret bypass path
            $secret = 'admin-bypass-' . md5(now());
            
            Artisan::call('down', [
                '--refresh' => '60',  // Refresh the page every 60 seconds
                '--secret' => $secret,  // Secret URL path to bypass maintenance mode
            ]);
            
            // Generate the full URL for the admin bypass
            $bypassUrl = url($secret);
            
            Toast::info('Maintenance mode has been enabled. Admin bypass URL: ' . $bypassUrl)
                ->persistent();
            
            // Log the link in case of any issues
            Log::info('Maintenance mode enabled with bypass URL: ' . $bypassUrl);
        }
        
        return;
    }

    /**
     * Process nested settings (like LDAP and OIDC settings) that come as JSON objects
     * but are stored in the database with dot notation
     *
     * @param string $parentKey The parent key (e.g., 'ldap_cache', 'ldap_custom_connection')
     * @param array $nestedData The nested data array
     * @param array &$changedSettings Reference to the changed settings array
     */
    private function processNestedSettings(string $parentKey, array $nestedData, array &$changedSettings)
    {
        foreach ($nestedData as $nestedKey => $nestedValue) {
            // Create the dot notation key
            $dotNotationKey = $parentKey . '.' . $nestedKey;
            
            Log::info("Looking for nested setting: {$dotNotationKey} = " . json_encode($nestedValue));
            
            // Handle deeply nested arrays (like attribute_map)
            if (is_array($nestedValue)) {
                $this->processNestedSettings($dotNotationKey, $nestedValue, $changedSettings);
                continue;
            }
            
            // Skip empty password fields
            if ((str_contains($dotNotationKey, 'bind_pw') || str_contains($dotNotationKey, 'password') || str_contains($dotNotationKey, 'secret')) && empty($nestedValue)) {
                Log::info("Skipping empty password field: {$dotNotationKey}");
                continue;
            }
            
            // Look for the setting in the database
            $setting = AppSetting::where('key', $dotNotationKey)->first();
            
            if ($setting) {
                Log::info("Found nested setting in DB: {$dotNotationKey}");
                
                // Process this nested setting
                $normalizedNewValue = $this->normalizeValue($nestedValue, $setting->type);
                $normalizedExistingValue = $this->normalizeValue($setting->value, $setting->type);
                
                Log::info("Comparing nested {$dotNotationKey}: old='" . json_encode($normalizedExistingValue) . "' new='" . json_encode($normalizedNewValue) . "'");
                
                if ($normalizedExistingValue !== $normalizedNewValue) {
                    $changedSettings[] = [
                        'key' => $dotNotationKey,
                        'value' => $normalizedNewValue,
                        'type' => $setting->type,
                        'model' => $setting
                    ];
                    Log::info("Change detected for nested: {$dotNotationKey}");
                } else {
                    Log::info("No change detected for nested: {$dotNotationKey}");
                }
            } else {
                Log::warning("Nested setting not found in DB: {$dotNotationKey}");
                Toast::warning("Nested setting not found in DB: {$dotNotationKey}");
            }
        }
    }
}
