<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use App\Services\ProviderSettingsService;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsListLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsFiltersLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsEditLayout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProviderSettingsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'providers' => ProviderSetting::filters(ProviderSettingsFiltersLayout::class)
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'API Provider Settings';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage API provider connections and their configurations.';
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
        return [
            Button::make('Import from Config')
                ->icon('cloud-download')
                ->method('importFromConfig')
                ->confirm('Are you sure? Existing providers will be overwritten with settings from the config file.'),
                
            Link::make('Add Provider')
                ->icon('bs.plus-circle')
                ->route('platform.modelsettings.provider.create'),
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
            ProviderSettingsFiltersLayout::class,
            ProviderSettingsListLayout::class,

            Layout::modal('editProviderModal', ProviderSettingsEditLayout::class)
                ->title('Edit Provider Settings')
                ->applyButton('Save Provider')
                ->closeButton('Cancel')
                ->deferred('loadProviderOnOpenModal'),
        ];
    }

    /**
     * Loads provider data when opening the modal window.
     *
     * @return array
     */
    public function loadProviderOnOpenModal(ProviderSetting $provider): iterable
    {
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
     * Save provider settings from modal.
     */
    public function saveProvider(Request $request, ProviderSetting $provider)
    {
        $request->validate([
            'provider.provider_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(ProviderSetting::class, 'provider_name')->ignore($provider),
            ],
            'provider.api_format' => 'required|string|max:255',
            'provider.base_url' => 'nullable|url|max:500',
            'provider.ping_url' => 'nullable|url|max:500',
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

        Toast::info('Provider settings have been updated successfully.');
    }

    /**
     * Toggle the active status of a provider.
     */
    public function toggleStatus(Request $request): void
    {
        $provider = ProviderSetting::findOrFail($request->get('id'));
        
        $newStatus = !$provider->is_active;
        $provider->update(['is_active' => $newStatus]);
        
        $statusText = $newStatus ? 'activated' : 'deactivated';
        Toast::info("Provider '{$provider->provider_name}' has been {$statusText}.");
    }

    /**
     * Test connection to a provider.
     */
    public function testConnection(Request $request): void
    {
        $provider = ProviderSetting::findOrFail($request->get('id'));
        
        if (!$provider->is_active) {
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");
            return;
        }
        
        if (empty($provider->ping_url)) {
            Toast::warning("No models URL configured for provider '{$provider->provider_name}'.");
            return;
        }
        
        try {
            // Simple connection test - this could be enhanced with actual API testing
            $response = @get_headers($provider->ping_url);
            
            if ($response !== false) {
                Toast::success("Connection test successful for provider '{$provider->provider_name}'.");
            } else {
                Toast::error("Connection test failed for provider '{$provider->provider_name}'.");
            }
        } catch (\Exception $e) {
            Toast::error("Connection test error for provider '{$provider->provider_name}': " . $e->getMessage());
        }
    }

    /**
     * Import settings from config file.
     */
    public function importFromConfig(ProviderSettingsService $settingsService)
    {
        $stats = $settingsService->importFromConfig();
        
        if ($stats['imported'] > 0 || $stats['updated'] > 0) {
            Toast::success("{$stats['imported']} providers imported and {$stats['updated']} providers updated.");
        } else {
            Toast::info("No providers were imported or updated. Please check if the model_providers.php file exists.");
        }
        
        return redirect()->back();
    }

    /**
     * Delete a provider.
     */
    public function deleteProvider(Request $request, ProviderSettingsService $settingsService)
    {
        $providerId = $request->get('id');
        $provider = ProviderSetting::find($providerId);
        
        if (!$provider) {
            Toast::error('Provider not found.');
            return redirect()->back();
        }
        
        $providerName = $provider->provider_name;
        
        // Delete provider
        $provider->delete();
        
        Toast::success("Provider '{$providerName}' was successfully deleted.");
        return redirect()->back();
    }
}
