<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class SystemSettingsScreen extends Screen
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
        $basicSettings = AppSetting::where('group', 'basic')->get();

        return [
            'basic' => $basicSettings,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'System Settings';
    }

    public function description(): ?string
    {
        return 'Configure basic application and system settings.';
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
            ...$this->buildBasicSettingsLayout(),
        ];
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
        $overrideSettings = [];

        // Gruppiere die Einstellungen nach Kategorien
        foreach ($this->query()['basic'] as $setting) {
            $key = $setting->key;

            // Gruppierung der Settings basierend auf ihrem Key
            if (in_array($key, ['app_name'])) {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            } elseif (in_array($key, ['app_url', 'app_env', 'app_timezone', 'app_locale'])) {
                $systemSettings[] = $this->generateFieldForSetting($setting);
            } elseif (str_contains($key, 'groupchat') || str_contains($key, 'ai_handle')) {
                $chatSettings[] = $this->generateFieldForSetting($setting);
            } elseif (in_array($key, ['app_debug'])) {
                $overrideSettings[] = $this->generateFieldForSetting($setting);
            } else {
                // Fallback für neue/unbekannte Einstellungen
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];

        // Allgemeine Anwendungseinstellungen
        if (! empty($generalSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($generalSettings),
            ])
                ->title('Base Settings')
                ->description('Basic application configuration and branding settings.');
        }

        // System-Einstellungen
        if (! empty($systemSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($systemSettings),
            ])
                ->title('System Settings')
                ->description('Core system configuration including environment and locale settings.');
        }

        // Chat-Einstellungen
        if (! empty($chatSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($chatSettings),
            ])
                ->title('App Feature Settings')
                ->description('Application-specific features and functionality configuration.');
        }

        // // Override/Overwrite Settings
        // if (! empty($overrideSettings)) {
        //    $layouts[] = Layout::block([
        //        Layout::rows($overrideSettings),
        //    ])
        //        ->title('Override Settings')
        //        ->description('Configuration overrides for debugging and development purposes.');
        // }

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
