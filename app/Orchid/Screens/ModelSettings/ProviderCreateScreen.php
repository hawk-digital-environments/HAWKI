<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiProvider;
use App\Orchid\Layouts\ModelSettings\ProviderAdvancedSettingsLayout;
use App\Orchid\Layouts\ModelSettings\ProviderAuthenticationLayout;
use App\Orchid\Layouts\ModelSettings\ProviderBasicInfoLayout;
use App\Orchid\Layouts\ModelSettings\ProviderStatusLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProviderCreateScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Create a new provider with explicitly empty values to prevent autofill
        $provider = [
            'provider_name' => '',
            'api_format_id' => null,
            'base_url' => '',
            'api_key' => '',
            'is_active' => true,
            'display_order' => 50,
            'additional_settings' => '',
        ];

        return [
            'provider' => $provider,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Create New Provider';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Add a new API provider to the system.';
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
        Link::make('Cancel')
                ->icon('x-circle')
                ->route('platform.models.api.providers'),    
        Button::make('Create Provider')
                ->icon('bs.check-circle')
                ->method('create'),

            
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
            Layout::block(ProviderBasicInfoLayout::class)
                ->title('Basic Information')
                ->description('Configure the provider name and API format.'),

            Layout::block(ProviderAuthenticationLayout::class)
                ->title('Authentication')
                ->description('Set up authentication credentials for this provider.'),

            Layout::block(ProviderStatusLayout::class)
                ->title('Provider Status')
                ->description('Control whether this provider is active and available for use.'),

            Layout::block(ProviderAdvancedSettingsLayout::class)
                ->title('Advanced Settings')
                ->description('Additional configuration options in JSON format.'),
        ];
    }

    /**
     * Create a new provider.
     */
    public function create(Request $request)
    {
        $request->validate([
            'provider.provider_name' => 'required|string|max:255|unique:api_providers,provider_name',
            'provider.api_format_id' => 'required|exists:api_formats,id',
            'provider.base_url' => 'required|string|url|max:500',
            'provider.api_key' => 'nullable|string|max:500',
            'provider.is_active' => 'boolean',
            'provider.display_order' => 'required|integer|min:0|max:999',
            'provider.additional_settings' => 'nullable|string',
        ]);

        $providerData = $request->input('provider');
        $providerName = $providerData['provider_name'];

        // Ensure display_order is treated as integer
        if (isset($providerData['display_order'])) {
            $providerData['display_order'] = (int) $providerData['display_order'];
        } else {
            // Auto-assign display_order if not provided
            $providerData['display_order'] = $this->getDefaultDisplayOrder($providerName);
        }

        // Convert JSON string to array for storage
        if (! empty($providerData['additional_settings'])) {
            $decoded = json_decode($providerData['additional_settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Toast::error('Additional settings must be valid JSON format.');

                return back()->withInput();
            }
            $providerData['additional_settings'] = $decoded;
        } else {
            $providerData['additional_settings'] = null;
        }

        // Create the new provider
        ApiProvider::create($providerData);

        Toast::success("New provider '{$providerName}' was successfully created.");

        return redirect()->route('platform.models.api.providers');
    }

    /**
     * Get appropriate display_order for a provider based on its name.
     */
    private function getDefaultDisplayOrder(string $providerName): int
    {
        $providerName = strtolower($providerName);

        // Define default display orders for common providers
        $defaultOrders = [
            'openai' => 1,
            'gpt' => 1,
            'gwdg' => 2,
            'google' => 3,
            'gemini' => 3,
            'anthropic' => 4,
            'claude' => 4,
            'ollama' => 10,
            'open webui' => 11,
            'openwebui' => 11,
            'local' => 20,
            'custom' => 50,
        ];

        // Check for exact matches
        if (isset($defaultOrders[$providerName])) {
            return $defaultOrders[$providerName];
        }

        // Check for partial matches
        foreach ($defaultOrders as $key => $order) {
            if (str_contains($providerName, $key)) {
                return $order;
            }
        }

        // Default to high number for unknown providers
        return 99;
    }
}
