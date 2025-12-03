<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PerformanceSettingsScreen extends Screen
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
        $systemSettings = AppSetting::where('group', 'system')->get();

        return [
            'system' => $systemSettings,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Performance Settings';
    }

    public function description(): ?string
    {
        return 'Configure AI streaming performance optimizations. These settings control response latency for real-time AI interactions.';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('bs.check-circle')
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
            SystemSettingsTabMenu::class,
            ...$this->buildPerformanceSettingsLayout(),
        ];
    }

    /**
     * Build layout for performance settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildPerformanceSettingsLayout()
    {
        $bufferSettings = [];
        $streamOptimizations = [];

        // Group system settings
        foreach ($this->query()['system'] as $setting) {
            $key = $setting->key;
            
            // Output buffer clearing setting
            if ($key === 'system_disable_stream_buffering') {
                $bufferSettings[] = $this->generateFieldForSetting($setting);
            } 
            // Stream header/config optimizations
            elseif (str_starts_with($key, 'system_stream_')) {
                $streamOptimizations[] = $this->generateFieldForSetting($setting);
            }
        }

        $layouts = [];

        // Output Buffer Control
        if (! empty($bufferSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($bufferSettings),
            ])
                ->title('Output Buffer Control');
        }

        // Stream Performance Optimizations Block
        if (! empty($streamOptimizations)) {
            $layouts[] = Layout::block([
                Layout::rows($streamOptimizations),
            ])
                ->title('Stream Header Optimizations');
        }

        return $layouts;
    }

    /**
     * Reset stream optimization settings to recommended defaults
     */
    public function resetToRecommended()
    {
        $defaults = [
            'system_stream_disable_nginx_buffering' => 'true',
            'system_stream_disable_apache_gzip' => 'true',
            'system_stream_disable_php_output_buffering' => 'false',
            'system_stream_disable_zlib_compression' => 'true',
        ];

        foreach ($defaults as $key => $value) {
            AppSetting::where('key', $key)->update(['value' => $value]);
        }

        // Clear settings cache
        cache()->forget('app_settings');

        toast()->success('Stream optimization settings have been reset to recommended defaults.');
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
