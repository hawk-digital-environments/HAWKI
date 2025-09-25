<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Layouts\System\ReverbClientLayout;
use App\Orchid\Layouts\System\ReverbServerLayout;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

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
        $settings = AppSetting::where('group', 'websockets')->get();
        $settingsData = [];

        // Convert database settings to flat input format for form fields
        foreach ($settings as $setting) {
            $flatKey = $this->convertDbKeyToFlatInputName($setting->key);
            // Remove the 'settings.' prefix from the flat key for the array key
            $arrayKey = str_replace('settings.', '', $flatKey);
            $settingsData[$arrayKey] = $setting->value;
        }

        return [
            'settings' => $settingsData,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'WebSocket Settings';
    }

    /**
     * Display header description.
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
            Button::make('Save')
                ->icon('bs.check-circle')
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
        return [
            SystemSettingsTabMenu::class,

            Layout::block([
                ReverbClientLayout::class,
            ])
                ->title('Client Configuration')
                ->description('Settings for WebSocket client connections from the frontend.'),

            Layout::block([
                ReverbServerLayout::class,
            ])
                ->title('Server Configuration')
                ->description('Settings for the Reverb WebSocket server instance.'),
        ];
    }

    /**
     * Check if user has permission to modify settings
     */
    protected function hasPermission(): bool
    {
        return auth()->user()->hasAccess('platform.systems.settings');
    }

    /**
     * The permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return ['platform.systems.settings'];
    }
}
