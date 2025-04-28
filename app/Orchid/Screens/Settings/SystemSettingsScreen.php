<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

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

        return [
            'basic' => $basicSettings,
            'authentication' => $authSettings,
            'api' => $apiSettings,
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
        return [
            Layout::block($this->getBasicSettingsLayout())
                ->title('Basic Application Settings')
                ->description('Configure general application settings')
                ->commands(
                    Button::make('Save Basic Settings')
                        ->icon('save')
                        ->method('saveSettings')
                ),
                
            Layout::block($this->getAuthSettingsLayout())
                ->title('Authentication Settings')
                ->description('Configure authentication methods and options')
                ->commands(
                    Button::make('Save Authentication Settings')
                        ->icon('save')
                        ->method('saveSettings')
                ),
                
            Layout::block($this->getApiSettingsLayout())
                ->title('API Communication Settings')
                ->description('Configure API access and external communication')
                ->commands(
                    Button::make('Save API Settings')
                        ->icon('save')
                        ->method('saveSettings')
                ),
        ];
    }

    /**
     * Build layout for basic settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function getBasicSettingsLayout()
    {
        $fields = [];

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
    private function getAuthSettingsLayout()
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
    private function getApiSettingsLayout()
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
                ])
                ->vertical();
            
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
