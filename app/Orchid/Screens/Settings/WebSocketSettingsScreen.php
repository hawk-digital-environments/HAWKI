<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use App\Orchid\Traits\OrchidSettingsManagementTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;

use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class WebSocketSettingsScreen extends Screen
{
    use OrchidSettingsManagementTrait;

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
        return [
            'settings' => AppSetting::where('group', 'websockets')->get(),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'WebSocket Settings';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Configure Laravel Reverb WebSocket server settings for real-time communication.';
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
                ->icon('check')
                ->method('saveSettings')
                ->canSee($this->hasPermission()),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $settings = AppSetting::where('group', 'websockets')->get();
        $fields = [];

        // Server Configuration Section
        $fields[] = Layout::rows([
            Group::make([
                Input::make('settings.reverb_servers_reverb_host')
                    ->title('Server Host')
                    ->help('IP address the Reverb server should bind to (0.0.0.0 for all interfaces)')
                    ->value($this->getSettingValue($settings, 'reverb_servers_reverb_host'))
                    ->placeholder('0.0.0.0'),

                Input::make('settings.reverb_servers_reverb_port')
                    ->title('Server Port')
                    ->type('number')
                    ->help('Port the Reverb server should listen on')
                    ->value($this->getSettingValue($settings, 'reverb_servers_reverb_port'))
                    ->placeholder('8080'),
            ]),

            Input::make('settings.reverb_servers_reverb_hostname')
                ->title('Server Hostname')
                ->help('Hostname for internal server operations')
                ->value($this->getSettingValue($settings, 'reverb_servers_reverb_hostname'))
                ->placeholder('localhost'),
        ])->title('Server Configuration');

        // Client Configuration Section
        $fields[] = Layout::rows([
            Group::make([
                Input::make('settings.reverb_client_host')
                    ->title('Client Host')
                    ->help('Hostname clients should connect to for WebSocket connections')
                    ->value($this->getSettingValue($settings, 'reverb_client_host'))
                    ->placeholder('your-domain.com'),

                Input::make('settings.reverb_client_port')
                    ->title('Client Port')
                    ->type('number')
                    ->help('Port clients should connect to (443 for HTTPS, 80 for HTTP)')
                    ->value($this->getSettingValue($settings, 'reverb_client_port'))
                    ->placeholder('443'),
            ]),

            Select::make('settings.reverb_client_scheme')
                ->title('Protocol Scheme')
                ->help('Protocol scheme for client connections')
                ->options([
                    'https' => 'HTTPS (Secure)',
                    'http' => 'HTTP (Insecure)',
                ])
                ->value($this->getSettingValue($settings, 'reverb_client_scheme'))
                ->empty('Select scheme'),
        ])->title('Client Configuration');

        return $fields;
    }

    /**
     * Get the value of a setting by key
     *
     * @param \Illuminate\Support\Collection $settings
     * @param string $key
     * @return mixed
     */
    private function getSettingValue($settings, string $key)
    {
        $setting = $settings->firstWhere('key', $key);
        return $setting ? $setting->value : null;
    }

    /**
     * Save settings
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveSettings(Request $request)
    {
        if (!$this->hasPermission()) {
            Toast::error('You do not have permission to modify these settings.');
            return back();
        }

        try {
            $this->saveSettingsFromRequest($request);
            
            // Clear relevant caches
            Cache::forget('config.reverb');
            Cache::forget('app_settings_cache');
            
            Toast::success('WebSocket settings saved successfully.');
            
            return back();
        } catch (\Exception $e) {
            Log::error('Error saving WebSocket settings: ' . $e->getMessage());
            Toast::error('Error saving settings: ' . $e->getMessage());
            return back();
        }
    }

    /**
     * Check if user has permission to modify settings
     *
     * @return bool
     */
    protected function hasPermission(): bool
    {
        return auth()->user()->hasAccess('platform.systems.settings');
    }

    /**
     * The permission required to access this screen.
     *
     * @return iterable|null
     */
    public function permission(): ?iterable
    {
        return ['platform.systems.settings'];
    }
}
