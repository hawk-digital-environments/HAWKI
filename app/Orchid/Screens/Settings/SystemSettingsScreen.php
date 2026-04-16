<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Models\User;
use App\Notifications\MaintenanceModeEnabled;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

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
        $basicSettings = AppSetting::where('group', 'basic')
            ->where('source', '!=', 'hawki')
            ->get();
        $hawkiSettings = AppSetting::where('source', 'hawki')->get();

        return [
            'basic' => $basicSettings,
            'hawki' => $hawkiSettings,
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

            Button::make($this->isMaintenanceModeActive() ? 'Unlock System' : 'Lock System')
                ->icon($this->isMaintenanceModeActive() ? 'bs.lock' : 'bs.unlock')
                ->confirm($this->isMaintenanceModeActive()
                    ? 'This will disable the maintenance mode. The website will be available to all users.'
                    : 'This will put the application into maintenance mode. Only users with bypass access will be able to access the site.')
                ->method('toggleMaintenanceMode'),
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
        $urlSettings = [];
        $overrideSettings = [];

        // Gruppiere die basic Einstellungen nach Kategorien
        foreach ($this->query()['basic'] as $setting) {
            $key = $setting->key;

            // Gruppierung der Settings basierend auf ihrem Key
            if (in_array($key, ['app_name'])) {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            } elseif (in_array($key, ['app_url', 'app_env', 'app_timezone', 'app_locale'])) {
                $systemSettings[] = $this->generateFieldForSetting($setting);
            } elseif (in_array($key, ['app_debug'])) {
                $overrideSettings[] = $this->generateFieldForSetting($setting);
            } else {
                // Fallback für neue/unbekannte Einstellungen
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        // Lade HAWKI Settings und gruppiere Footer Links
        foreach ($this->query()['hawki'] as $setting) {
            $key = $setting->key;
            
            // Gruppiere nur URL-Settings (Footer Links)
            if (in_array($key, ['hawki_dataprotection_location', 'hawki_imprint_location', 'hawki_accessibility_location'])) {
                $urlSettings[] = $this->generateFieldForSetting($setting);
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

        // Footer Links Block
        if (! empty($urlSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($urlSettings),
            ])
                ->title('Footer Links')
                ->description('Configure URLs for footer links on the login page. Supports both internal routes (e.g., /dataprotection) and external URLs (e.g., https://example.com/privacy). Leave empty to disable.');
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
     * Check if the application is in maintenance mode
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
            Toast::success('Maintenance mode has been disabled. Site is now accessible to all users.');
            
            // Remove notifications for all admins
            $admins = User::whereHas('roles', function ($query) {
                $query->where('slug', 'admin')
                    ->orWhere('slug', 'super-admin');
            })->get();

            foreach ($admins as $admin) {
                $admin->notifications()
                    ->where('type', MaintenanceModeEnabled::class)
                    ->delete();
            }
        } else {
            // Generate a secret bypass path
            $secret = 'admin-bypass-'.md5(now());

            Artisan::call('down', [
                '--refresh' => '15',  // Refresh the page every 15 seconds
                '--secret' => $secret,  // Secret URL path to bypass maintenance mode
                '--with-secret' => true,  // Enable secret bypass
            ]);

            // Generate the full URL for the admin bypass
            $bypassUrl = url($secret);

            // Send notification to all admin users
            $admins = User::whereHas('roles', function ($query) {
                $query->where('slug', 'admin')
                    ->orWhere('slug', 'super-admin');
            })->get();

            foreach ($admins as $admin) {
                $admin->notify(new MaintenanceModeEnabled($bypassUrl, $secret));
            }

            Toast::info('Maintenance mode enabled!')
                ->message('Admin panel (/admin/*) remains accessible. Check your notifications (🔔) for the bypass URL.')
                ->persistent();

            // Log the information
            Log::info('Maintenance mode enabled', [
                'bypass_url' => $bypassUrl,
                'secret' => $secret,
                'enabled_by' => Auth::user()->email,
                'admins_notified' => $admins->count(),
            ]);
        }
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
