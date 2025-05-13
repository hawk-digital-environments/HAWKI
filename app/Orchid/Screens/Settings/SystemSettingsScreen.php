<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
        return Layout::block([
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
                        return $row['env_value'];
                    }),
                    
                TD::make('db_value', 'Database Value')
                    ->width('250px')
                    ->render(function ($row) {
                        return $row['db_value'];
                    }),
                
                TD::make('config_value', 'Config Value')
                    ->width('250px')
                    ->render(function ($row) {
                        return $row['config_value'];
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
     * Build layout for basic settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildBasicSettingsLayout()
    {
        // Get system information
        Artisan::call('about');
        $aboutOutput = Artisan::output();
        
        $fields = [
            Label::make('system_info_label')
                ->title('System Information')
                ->popover('<Artisan about> outputs a mixed collection of config and .env values, so not all config values are represented correctly.'),
            Code::make('system_info')
                ->language('shell')
                ->readonly()
                ->value($aboutOutput)
                ->height('550px')
        ];

        foreach ($this->query()['basic'] as $setting) {
            $fields[] = $this->getFieldForSetting($setting);
        }

        return Layout::rows($fields);
    }

    /**
     * Build layout for authentication settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildAuthSettingsLayout()
    {
        $fields = [];

        foreach ($this->query()['authentication'] as $setting) {
            $fields[] = $this->getFieldForSetting($setting);
        }

        return Layout::rows($fields);
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
        
        switch ($setting->type) {
            case 'boolean':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($key)
                        ->help($setting->description)
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
                        ->title($key)
                        ->help($setting->description)
                        ->addclass('fw-bold'),
                    Input::make("settings.{$key}")
                        ->type('number')
                        ->value($setting->value)
                        ->horizontal(),
                ])
                ->alignCenter()
                ->widthColumns('1fr max-content');
                
            case 'json':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($key)
                        ->help($setting->description)
                        ->addclass('fw-bold'),
                    TextArea::make("settings.{$key}")
                        ->rows(5)
                        ->value(json_encode($setting->typed_value, JSON_PRETTY_PRINT)),
                ]);
            
            case 'string':
                // Special handling for AUTHENTICATION_METHOD which should be a dropdown
                if ($key === 'AUTHENTICATION_METHOD') {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($key)
                            ->help($setting->description)
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
                    ->widthColumns('1fr max-content');
                }
                
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($key)
                        ->help($setting->description)
                        ->addclass('fw-bold'),
                    Input::make("settings.{$key}")
                        ->value($setting->value),
                ])
                ->alignCenter()
                ->widthColumns('1fr max-content');
        }
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
        
        foreach ($settings as $key => $value) {
            $setting = AppSetting::where('key', $key)->first();
            
            if ($setting) {
                // Handle JSON values
                if ($setting->type === 'json' && is_string($value)) {
                    try {
                        $value = json_decode($value, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Toast::warning("Invalid JSON format for {$key}. Using default value.");
                            continue;
                        }
                    } catch (\Exception $e) {
                        Toast::warning("Error parsing JSON for {$key}: " . $e->getMessage());
                        continue;
                    }
                }
                
                $setting->value = $value;
                $setting->save();
                
                // Also clear cache for this setting
                Cache::forget('app_settings_' . $key);
            }
        }
        
        Toast::success('System settings have been saved.');
        
        return redirect()->route('platform.settings.system');
    }
}
