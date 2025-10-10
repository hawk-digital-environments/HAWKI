<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class AuthMethodEditScreen extends Screen
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
        $currentMethod = config('auth.authentication_method', '');

        if (empty($currentMethod)) {
            return 'Authentication Configuration';
        }

        return "Authentication Configuration - {$currentMethod}";
    }

    public function description(): ?string
    {
        $currentMethod = config('auth.authentication_method', '');

        if (empty($currentMethod)) {
            return 'Configure authentication method and provider-specific settings.';
        }

        return "Configure {$currentMethod} provider-specific settings and attribute mappings.";
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
            ...$this->buildAuthMethodSettingsLayout(),
        ];
    }

    /**
     * Build layout for authentication method specific settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildAuthMethodSettingsLayout()
    {
        $currentMethod = config('auth.authentication_method', '');
        $upperCurrentMethod = strtoupper($currentMethod);

        $layouts = [];

        // Get all authentication settings
        $allSettings = $this->query()['authentication'];

        // Show specific settings based on the selected authentication method
        switch ($upperCurrentMethod) {
            case 'LDAP':
                $layouts = array_merge($layouts, $this->buildLdapSettings($allSettings));
                break;
            case 'OIDC':
                $layouts = array_merge($layouts, $this->buildOidcSettings($allSettings));
                break;
            case 'SHIBBOLETH':
                $layouts = array_merge($layouts, $this->buildShibbolethSettings($allSettings));
                break;
            case 'LOCAL':
                $layouts = array_merge($layouts, $this->buildLocalSettings($allSettings));
                break;
            default:
                $layouts[] = Layout::block([
                    Layout::rows([
                        \Orchid\Screen\Fields\Label::make('no_method_selected')
                            ->title('Please select an authentication method first from the main authentication settings')
                            ->addclass('text-muted text-center'),
                    ]),
                ])
                    ->title('Information')
                    ->description('No authentication method selected. Please configure the authentication method first.');
        }

        return $layouts;
    }

    /**
     * Build LDAP-specific settings layout
     */
    private function buildLdapSettings($allSettings): array
    {
        $ldapSettings = [];
        $attributeMappingSettings = [];

        foreach ($allSettings as $setting) {
            // LDAP configuration settings (including debug_mode)
            if (str_starts_with($setting->key, 'ldap_') &&
                ! str_contains($setting->key, 'attribute_map') &&
                ! str_contains($setting->key, 'connections.default.attribute_map')) {
                $ldapSettings[] = $this->generateFieldForSetting($setting);
            }
            // LDAP attribute mapping settings - check for both patterns
            elseif (str_contains($setting->key, 'ldap_connections.default.attribute_map') ||
                    str_contains($setting->key, 'ldap_connections__default__attribute_map')) {
                $attributeMappingSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        $layouts = [];

        if (! empty($ldapSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($ldapSettings),
            ])
                ->title('LDAP Configuration')
                ->description('Configure LDAP directory service connection settings and debug options.');
        }

        if (! empty($attributeMappingSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($attributeMappingSettings),
            ])
                ->title('LDAP Attribute Mapping')
                ->description('Configure how LDAP attributes are mapped to local user fields.');
        }

        return $layouts;
    }

    /**
     * Build OIDC-specific settings layout
     */
    private function buildOidcSettings($allSettings): array
    {
        $oidcSettings = [];
        $attributeMappingSettings = [];

        foreach ($allSettings as $setting) {
            // OIDC configuration settings
            if (str_starts_with($setting->key, 'open_id_connect_') &&
                ! str_contains($setting->key, 'attribute_map')) {
                $oidcSettings[] = $this->generateFieldForSetting($setting);
            }
            // OIDC attribute mapping settings - check for both patterns
            elseif (str_contains($setting->key, 'open_id_connect_attribute_map.') ||
                    str_contains($setting->key, 'open_id_connect_attribute_map__')) {
                $attributeMappingSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        $layouts = [];

        if (! empty($oidcSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($oidcSettings),
            ])
                ->title('OpenID Connect Configuration')
                ->description('Configure OpenID Connect provider endpoints and client credentials.');
        }

        if (! empty($attributeMappingSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($attributeMappingSettings),
            ])
                ->title('OIDC Attribute Mapping')
                ->description('Configure how OpenID Connect claims are mapped to local user fields.');
        }

        return $layouts;
    }

    /**
     * Build Shibboleth-specific settings layout
     */
    private function buildShibbolethSettings($allSettings): array
    {
        $shibbolethSettings = [];
        $attributeMappingSettings = [];

        foreach ($allSettings as $setting) {
            // Shibboleth configuration settings
            if (str_starts_with($setting->key, 'shibboleth_') &&
                ! str_contains($setting->key, 'attribute_map')) {
                $shibbolethSettings[] = $this->generateFieldForSetting($setting);
            }
            // Shibboleth attribute mapping settings - check for both patterns
            elseif (str_contains($setting->key, 'shibboleth_attribute_map.') ||
                    str_contains($setting->key, 'shibboleth_attribute_map__')) {
                $attributeMappingSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        $layouts = [];

        if (! empty($shibbolethSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($shibbolethSettings),
            ])
                ->title('Shibboleth Configuration')
                ->description('Configure Shibboleth SSO integration settings.');
        }

        if (! empty($attributeMappingSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($attributeMappingSettings),
            ])
                ->title('Shibboleth Attribute Mapping')
                ->description('Configure how Shibboleth attributes are mapped to local user fields.');
        }

        return $layouts;
    }

    /**
     * Build Local authentication settings layout
     */
    private function buildLocalSettings($allSettings): array
    {
        $localSettings = [];

        foreach ($allSettings as $setting) {
            if (str_starts_with($setting->key, 'auth_') &&
                ! str_contains($setting->key, 'attribute_map') &&
                $setting->key !== 'auth_authentication_method') {
                $localSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        $layouts = [];

        if (! empty($localSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($localSettings),
            ])
                ->title('Local Authentication Configuration')
                ->description('Configure local user authentication and passkey settings.');
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
