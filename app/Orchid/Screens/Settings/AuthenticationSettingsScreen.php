<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SessionSettingsLayout;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class AuthenticationSettingsScreen extends Screen
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
        $authSettings = AppSetting::where('group', 'authentication')->get();

        return [
            'authentication' => $authSettings,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Authentication Settings';
    }

    public function description(): ?string
    {
        return 'Configure authentication methods and external authentication providers.';
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
            ...$this->buildAuthSettingsLayout(),
        ];
    }

    /**
     * Build layout for authentication settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildAuthSettingsLayout()
    {
        $authMethodSetting = null;
        $passkeySettings = [];
        $otherAuthSettings = [];
        $sessionSettings = [];

        // Alle Authentifizierungseinstellungen nach Typ sortieren
        foreach ($this->query()['authentication'] as $setting) {
            if ($setting->key === 'auth_authentication_method') {
                $authMethodSetting = $setting;
            } elseif (str_contains($setting->key, 'attribute_map')) {
                // Skip attribute_map settings - they are handled in AuthMethodEditScreen
                continue;
            } elseif (str_starts_with($setting->key, 'ldap_') ||
                      str_starts_with($setting->key, 'open_id_connect_') ||
                      str_starts_with($setting->key, 'shibboleth_')) {
                // Skip provider-specific settings - they are handled in AuthMethodEditScreen
                continue;
            } elseif (str_starts_with($setting->key, 'session_')) {
                // Session settings - will be rendered separately
                $sessionSettings[] = $setting;
            } elseif (str_starts_with($setting->key, 'auth_passkey_')) {
                // Check if we should show conditional passkey settings
                $shouldShowSetting = true;

                // Hide specific settings when passkey_method is not 'system'
                if (in_array($setting->key, ['auth_passkey_otp', 'auth_passkey_otp_timeout'])) {
                    $passkeyMethod = config('auth.passkey_method', '');
                    $shouldShowSetting = (strtolower($passkeyMethod) === 'system');
                }

                if ($shouldShowSetting) {
                    $passkeySettings[] = $this->generateFieldForSetting($setting);
                }
            } else {
                $otherAuthSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];

        // Local User Authentication Settings first
        if (! empty($otherAuthSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($otherAuthSettings),
            ])
                ->title('Local User Authentication System')
                ->description('Local authentication can be used in addition to an external auth method for test users or external guest users.');
        }

        // Passkey-Einstellungen als eigenes Layout
        if (! empty($passkeySettings)) {
            $layouts[] = Layout::block([
                Layout::rows($passkeySettings),
            ])
                ->title('Passkey Settings')
                ->description('Configure passkey authentication for enhanced security. This can be changed later, but could prove inconvenient for your users.');
        }

        // Authentifizierungsmethode in separatem Layout, falls vorhanden
        if ($authMethodSetting) {
            $authMethodRows = [$this->generateFieldForSetting($authMethodSetting)];

            // Add authentication provider settings link for external authentication methods
            $currentMethod = config('auth.authentication_method', '');
            if (in_array($currentMethod, ['LDAP', 'OIDC', 'Shibboleth'])) {
                $authMethodRows[] = \Orchid\Screen\Fields\Group::make([
                    \Orchid\Screen\Fields\Label::make('provider_settings_title')
                        ->title('Provider Settings')
                        ->help('Advanced configuration'),

                    Link::make("Configure {$currentMethod} Settings")
                        ->icon('bs.gear')
                        ->route('platform.settings.authentication.edit'),
                ])
                    ->fullWidth();
            }            $layouts[] = Layout::block([
                Layout::rows($authMethodRows),
            ])
                ->title('External Authentication Method')
                ->description('Configure the primary authentication method for external user validation.');
        }

        // Session Settings Layout (new section)
        if (! empty($sessionSettings)) {
            $sessionLayouts = SessionSettingsLayout::build(collect($sessionSettings));
            $layouts = array_merge($layouts, $sessionLayouts);
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
