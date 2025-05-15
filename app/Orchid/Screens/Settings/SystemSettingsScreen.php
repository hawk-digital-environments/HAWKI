<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;  // Fehlender Import hinzugefügt
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
            $response = Http::get(url('/test-config-value'));
            $responseData = $response->json();
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
            Button::make('Run Settings Seeder')
                ->icon('refresh')
                ->confirm('This will reload settings from config files using the AppSettingsSeeder. Any manual changes may be overwritten. Continue?')
                ->method('runSettingsSeeder'),
                
            Button::make('Save Settings')
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
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];
        // Test User Einstellungen als eigenes Layout
        if (!empty($testUserSettings)) {
            $layouts[] = Layout::rows($testUserSettings)
            ->title('Testusers');;
        }
        // Authentifizierungsmethode in separatem Layout, falls vorhanden
        if ($authMethodSetting) {
            $layouts[] = Layout::rows([
                $this->getFieldForSetting($authMethodSetting)
            ])->title('Change Authentication Method');
            
            // Aktuelle Authentifizierungsmethode ermitteln
            $currentMethod = config('auth.authentication_method', '');
            
            // Je nach Authentifizierungsmethode die entsprechenden Einstellungen anzeigen
            switch (strtoupper($currentMethod)) {
                case 'LDAP':
                    if (!empty($ldapSettings)) {
                        $layouts[] = Layout::rows($ldapSettings)
                            ->title('LDAP Settings')
                            ->canSee($currentMethod === 'LDAP');
                    }
                    break;
                    
                case 'OIDC':
                    if (!empty($oidcSettings)) {
                        $layouts[] = Layout::rows($oidcSettings)
                            ->title('OpenID Connect Settings')
                            ->canSee($currentMethod === 'OIDC');
                    }
                    break;
                    
                case 'SHIBBOLETH':
                    if (!empty($shibbolethSettings)) {
                        $layouts[] = Layout::rows($shibbolethSettings)
                            ->title('Shibboleth Settings')
                            ->canSee($currentMethod === 'Shibboleth');
                    }
                    break;
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
                ->alignCenter()
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
                // Special handling for password fields - especially LDAP bind password
                else if (str_contains($key, 'bind_pw') || str_contains($key, 'password') || str_contains($key, 'secret')) {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Input::make("settings.{$key}")
                            ->type('password')
                            ->placeholder('••••••••')
                            ->value(''),  // Leerer Wert - Passwort wird nicht angezeigt, aber kann neu gesetzt werden
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
        
        if ($settings) {
            // Sammle nur Einträge, die sich tatsächlich geändert haben
            $changedSettings = [];
            
            foreach ($settings as $key => $value) {
                // Passwortfelder nur speichern, wenn sie nicht leer sind
                if ((str_contains($key, 'bind_pw') || str_contains($key, 'password') || str_contains($key, 'secret')) && empty($value)) {
                    continue;
                }
                
                // Da die Keys bereits mit Unterstrichen in der DB sind, können wir direkt suchen
                $setting = AppSetting::where('key', $key)->first();
                
                if ($setting) {
                    // Umwandlung des Werts basierend auf dem Typ für einen korrekten Vergleich
                    $normalizedNewValue = $this->normalizeValue($value, $setting->type);
                    $normalizedExistingValue = $this->normalizeValue($setting->value, $setting->type);
                    
                    // Vergleich der normalisierten Werte für Änderungserkennung
                    if ($normalizedExistingValue !== $normalizedNewValue) {
                        $changedSettings[] = [
                            'key' => $key,
                            'value' => $normalizedNewValue,
                            'type' => $setting->type,
                            'model' => $setting
                        ];
                    }
                } else {
                    Toast::warning("Setting nicht gefunden: {$key}");
                }
            }
            
            // Führe Updates nur für geänderte Einstellungen durch
            foreach ($changedSettings as $changed) {
                $setting = $changed['model'];
                $formattedValue = $this->formatValueForStorage($changed['value'], $changed['type']);
                
                // Aktualisiere die Einstellung
                $setting->value = $formattedValue;
                $setting->save();
                
                // Cache für diese Einstellung löschen
                Cache::forget('app_settings_' . $setting->key);
                
                // Auch den Config-Cache für diese Quelle löschen, falls vorhanden
                if ($setting->source) {
                    Cache::forget('config_' . $setting->source);
                }
                
                $count++;
            }
            
            // Benutzer-Feedback basierend auf den Änderungen
            if ($count > 0) {
                // Gesamten Konfigurationscache löschen
                try {
                    Artisan::call('config:clear');
                    
                    // Löschen des spezifischen Config-Override-Caches
                    \App\Providers\ConfigServiceProvider::clearConfigCache();
                    
                    Toast::success("{$count} System-Einstellungen wurden aktualisiert und der Konfigurationscache wurde geleert.");
                } catch (\Exception $e) {
                    Toast::warning("Einstellungen gespeichert, aber Cache-Leeren fehlgeschlagen: " . $e->getMessage());
                }
            } else {
                Toast::info("Keine Änderungen erkannt");
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
            // Führe den AppSettingsSeeder aus und erfasse die Ausgabe
            \Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\AppSettingsSeeder'
            ]);
            
            // Erfasse die Ausgabe direkt
            $output = \Artisan::output();
            
            // Konfigurationscache leeren, um neue Einstellungen zu aktivieren
            Artisan::call('config:clear');
            
            // Löschen des spezifischen Config-Override-Caches
            \App\Providers\ConfigServiceProvider::clearConfigCache();
            
            // Zeige die unveränderte Ausgabe als Erfolgsmeldung an
            Toast::success('Settings Seeder Output: ' . PHP_EOL . $output);
            
            // Logging für Diagnosezwecke
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
                // Konvertiere in einen booleschen Wert für konsistenten Vergleich
                if (is_string($value)) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                return (bool) $value;
                
            case 'integer':
                // Konvertiere in eine Ganzzahl für konsistenten Vergleich
                return (int) $value;
                
            case 'json':
                // Dekodiere JSON-Strings zu Arrays/Objekten für einen strukturellen Vergleich
                if (is_string($value)) {
                    try {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $decoded;
                        }
                    } catch (\Exception $e) {
                        // Bei Fehler den Originalwert zurückgeben
                    }
                }
                // Wenn es bereits ein Array ist oder die Dekodierung fehlschlägt
                return $value;
                
            default:
                // Für Strings und andere Typen als String zurückgeben
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
                // Speichere als 'true' oder 'false' String
                return (filter_var($value, FILTER_VALIDATE_BOOLEAN)) ? 'true' : 'false';
                
            case 'integer':
                // Konvertiere zu String für Speicherung
                return (string) (int) $value;
                
            case 'json':
                // Konvertiere Array/Objekt zu JSON-String
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                } elseif (is_string($value)) {
                    try {
                        // Versuche zu dekodieren und wieder zu kodieren, um ein konsistentes Format zu gewährleisten
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return json_encode($decoded);
                        }
                    } catch (\Exception $e) {
                        // Bei Fehler: Original zurückgeben, wenn es ein gültiger JSON-String ist
                    }
                }
                return $value;
                
            default:
                // Für alle anderen Typen als String zurückgeben
                return (string) $value;
        }
    }
}
