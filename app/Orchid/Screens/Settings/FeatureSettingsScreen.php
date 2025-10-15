<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class FeatureSettingsScreen extends Screen
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
        $hawkiSettings = AppSetting::where('source', 'hawki')->get();

        return [
            'api' => $apiSettings,
            'hawki' => $hawkiSettings,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Features';
    }

    public function description(): ?string
    {
        return 'Configure application features, API endpoints, and external service integrations.';
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
            ...$this->buildFeatureSettingsLayout(),
        ];
    }

    /**
     * Build layout for feature settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildFeatureSettingsLayout()
    {
        $apiFields = [];
        $hawkiFeatureSettings = [];
        $databaseConfigSettings = [];

        // API Settings
        foreach ($this->query()['api'] as $setting) {
            $apiFields[] = $this->generateFieldForSetting($setting);
        }

        // HAWKI Feature Settings (source = "hawki")
        // Separate into App Features and Database Config
        foreach ($this->query()['hawki'] as $setting) {
            $key = $setting->key;
            
            // Skip URL-Settings (Footer Links) - they belong to System Settings
            if (in_array($key, ['hawki_dataprotection_location', 'hawki_imprint_location', 'hawki_accessibility_location'])) {
                continue;
            }
            
            // Database-based config settings
            if (in_array($key, ['hawki_ai_config_system', 'hawki_language_controller_system'])) {
                $databaseConfigSettings[] = $this->generateFieldForSetting($setting);
            } else {
                // Regular app feature settings
                $hawkiFeatureSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        $layouts = [];

        // App Feature Settings Block
        if (! empty($hawkiFeatureSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($hawkiFeatureSettings),
            ])
                ->title('App Feature Settings')
                ->description('HAWKI-specific features and functionality configuration.');
        }

        // Database-based Config Block
        if (! empty($databaseConfigSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($databaseConfigSettings),
            ])
                ->title('Database based Config')
                ->description('Toggle between file-based and database-driven configuration systems.');
        }

        // API Configuration Block
        if (! empty($apiFields)) {
            $layouts[] = Layout::block([
                Layout::rows($apiFields),
            ])
                ->title('API Configuration')
                ->description('Configure API endpoints, rate limiting, and external service integrations.');
        }

        return $layouts;
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
