<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use App\Models\ApiFormat;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsEditLayout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProviderEditScreen extends Screen
{
    /**
     * @var ProviderSetting
     */
    public $provider;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(ProviderSetting $provider): iterable
    {
        $this->provider = $provider;
        
        // Load API format relationship
        $provider->load('apiFormat');
        
        // Convert additional_settings to string for form display
        $providerData = $provider->toArray();
        if (isset($providerData['additional_settings']) && is_array($providerData['additional_settings'])) {
            $providerData['additional_settings'] = json_encode($providerData['additional_settings'], JSON_PRETTY_PRINT);
        } elseif (is_null($providerData['additional_settings'])) {
            $providerData['additional_settings'] = '';
        }
        
        return [
            'provider' => $providerData,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        $provider = $this->provider;
        // Fallback: Versuche Provider aus Route oder Request zu laden, falls nicht gesetzt
        if (!$provider || empty($provider->provider_name)) {
            $routeProvider = request()->route('provider');
            if ($routeProvider && $routeProvider instanceof \App\Models\ProviderSetting) {
                $provider = $routeProvider;
            } elseif ($routeProvider && is_numeric($routeProvider)) {
                $provider = \App\Models\ProviderSetting::find($routeProvider);
            }
        }
        $providerName = $provider && $provider->provider_name ? $provider->provider_name : 'Unknown';
        return 'Edit Provider: ' . $providerName;
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Modify the settings for this API provider.';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.providers',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        $provider = request()->route('provider');
        
        return [
            Button::make('Save Changes')
                ->icon('save')
                ->method('save'),

            Button::make('Test Connection')
                ->icon('wifi')
                ->method('testConnection')
                ->canSee($provider && $provider->is_active),

            Link::make('Cancel')
                ->icon('x-circle')
                ->route('platform.modelsettings.providers'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            ProviderSettingsEditLayout::class,
        ];
    }

    /**
     * Save the provider settings.
     */
    public function save(Request $request, ProviderSetting $provider)
    {
        $request->validate([
            'provider.provider_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(ProviderSetting::class, 'provider_name')->ignore($provider),
            ],
            'provider.api_format_id' => [
                'required',
                'integer',
                'exists:api_formats,id'
            ],
            'provider.api_key' => 'nullable|string|max:500',
            'provider.is_active' => 'boolean',
            'provider.additional_settings' => 'nullable|string',
        ]);

        $providerData = $request->input('provider');
        
        // Handle password field - only update if not empty
        if (empty($providerData['api_key'])) {
            unset($providerData['api_key']);
        }
        
        // Convert JSON string to array for storage
        if (!empty($providerData['additional_settings'])) {
            $decoded = json_decode($providerData['additional_settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Toast::error('Additional settings must be valid JSON format.');
                return back()->withInput();
            }
            $providerData['additional_settings'] = $decoded;
        } else {
            $providerData['additional_settings'] = null;
        }
        
        $provider->fill($providerData)->save();

        Toast::success('Provider settings have been updated successfully.');
        
        return redirect()->route('platform.modelsettings.providers');
    }

    /**
     * Test connection to the provider.
     */
    public function testConnection(ProviderSetting $provider)
    {
        if (!$provider->is_active) {
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");
            return;
        }
        
        // Load API format relationship
        $provider->load('apiFormat');
        
        if (!$provider->apiFormat) {
            Toast::warning("No API format configured for provider '{$provider->provider_name}'.");
            return;
        }
        
        // Get models endpoint from API format
        $modelsEndpoint = $provider->apiFormat->getModelsEndpoint();
        if (!$modelsEndpoint) {
            Toast::warning("No models endpoint available for API format '{$provider->apiFormat->display_name}'.");
            return;
        }
        
        $testUrl = $modelsEndpoint->full_url;
        if (empty($testUrl)) {
            Toast::warning("Cannot construct test URL for provider '{$provider->provider_name}'.");
            return;
        }
        
        try {
            // Simple connection test - this could be enhanced with actual API testing
            $response = @get_headers($testUrl);
            
            if ($response !== false) {
                Toast::success("Connection test successful for provider '{$provider->provider_name}' at {$testUrl}.");
            } else {
                Toast::error("Connection test failed for provider '{$provider->provider_name}' at {$testUrl}.");
            }
        } catch (\Exception $e) {
            Toast::error("Connection test error for provider '{$provider->provider_name}': " . $e->getMessage());
        }
    }
}
