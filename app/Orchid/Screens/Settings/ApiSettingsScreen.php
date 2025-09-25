<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class ApiSettingsScreen extends Screen
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
        $apiSettings = AppSetting::where('group', 'api')->get();

        return [
            'api' => $apiSettings,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'API Settings';
    }

    public function description(): ?string
    {
        return 'Configure API endpoints, rate limiting, and external service integrations.';
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
            ...$this->buildApiSettingsLayout(),
        ];
    }

    /**
     * Build layout for API settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildApiSettingsLayout()
    {
        $fields = [];

        foreach ($this->query()['api'] as $setting) {
            $fields[] = $this->generateFieldForSetting($setting);
        }

        return [
            Layout::block([
                Layout::rows($fields),
            ])
                ->title('API Configuration')
                ->description('Configure API endpoints, rate limiting, and external service integrations.'),
        ];
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
