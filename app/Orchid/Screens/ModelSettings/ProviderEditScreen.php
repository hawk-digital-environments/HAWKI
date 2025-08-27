<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use App\Models\ApiFormat;
use App\Orchid\Layouts\ModelSettings\ProviderBasicInfoLayout;
use App\Orchid\Layouts\ModelSettings\ProviderAuthenticationLayout;
use App\Orchid\Layouts\ModelSettings\ProviderStatusLayout;
use App\Orchid\Layouts\ModelSettings\ProviderAdvancedSettingsLayout;
use App\Orchid\Traits\AiConnectionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProviderEditScreen extends Screen
{
    use AiConnectionTrait;
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
            Button::make('Save')
                ->icon('save')
                ->method('save'),

            Button::make('Test Connection')
                ->icon('wifi')
                ->method('testConnection')
                ->canSee($provider && $provider->is_active),

            Link::make('Cancel')
                ->icon('x-circle')
                ->route('platform.models.api.providers'),
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
     * Save the provider settings.
     */
    public function save(Request $request, ProviderSetting $provider)
    {
        try {
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

            // Store original values for change tracking
            $originalName = $provider->provider_name;
            $originalApiFormat = $provider->api_format_id;
            $originalActive = $provider->is_active;
            $originalSettings = $provider->additional_settings;

            $providerData = $request->input('provider');
            
            // Handle password field - only update if not empty
            if (empty($providerData['api_key'])) {
                unset($providerData['api_key']);
            }
            
            // Convert JSON string to array for storage
            if (!empty($providerData['additional_settings'])) {
                $decoded = json_decode($providerData['additional_settings'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Invalid JSON in provider additional settings', [
                        'provider_id' => $provider->id,
                        'json_error' => json_last_error_msg(),
                        'input' => $providerData['additional_settings'],
                    ]);
                    
                    Toast::error('Additional settings must be valid JSON format.');
                    return back()->withInput();
                }
                $providerData['additional_settings'] = $decoded;
            } else {
                $providerData['additional_settings'] = null;
            }
            
            $provider->fill($providerData)->save();

            // Log successful update with change details
            $changes = [];
            if ($originalName !== $provider->provider_name) {
                $changes['provider_name'] = ['from' => $originalName, 'to' => $provider->provider_name];
            }
            if ($originalApiFormat !== $provider->api_format_id) {
                $changes['api_format_id'] = ['from' => $originalApiFormat, 'to' => $provider->api_format_id];
            }
            if ($originalActive !== $provider->is_active) {
                $changes['is_active'] = ['from' => $originalActive, 'to' => $provider->is_active];
            }
            if ($originalSettings !== $provider->additional_settings) {
                $changes['additional_settings'] = 'updated';
            }

            Log::info('Provider settings updated successfully', [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name,
                'changes' => $changes,
                'updated_by' => auth()->id(),
            ]);

            Toast::success("Provider '{$provider->provider_name}' has been updated successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for provider update', [
                'provider_id' => $provider->id,
                'errors' => $e->errors(),
                'updated_by' => auth()->id(),
            ]);
            
            throw $e; // Re-throw validation exceptions to show form errors
            
        } catch (\Exception $e) {
            Log::error('Error updating provider settings', [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'updated_by' => auth()->id(),
            ]);
            
            Toast::error('An error occurred while saving: ' . $e->getMessage());
            return back()->withInput();
        }
        
        return redirect()->route('platform.models.api.providers.edit', $provider);
    }
}
