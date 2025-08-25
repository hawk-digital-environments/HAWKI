<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use App\Services\ProviderSettingsService;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsListLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsFiltersLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsEditLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsImportLayout;
use App\Orchid\Traits\OrchidImportTrait;
use App\Orchid\Traits\OrchidLoggingTrait;
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
    use OrchidImportTrait, OrchidLoggingTrait;
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
            ModalToggle::make('Import')
                ->icon('bs.upload')
                ->modal('importProvidersModal')
                ->method('importProvidersFromJson'),
                
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

            Layout::modal('importProvidersModal', ProviderSettingsImportLayout::class)
                ->title('Import Providers from JSON')
                ->applyButton('Import')
                ->closeButton('Cancel'),

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
     * Import providers from JSON or PHP config file.
     */
    public function importProvidersFromJson(Request $request): void
    {
        // Validate uploaded file using OrchidImportTrait with file extension validation
        // Use extensions instead of mimes to avoid MIME type detection issues
        $file = $this->validateImportFile($request, 'importFile', 'json,php', 2048);
        if (!$file) {
            return;
        }

        // Determine file type and process accordingly
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $providersData = null;

        if ($fileExtension === 'php') {
            // Process PHP config file
            $providersData = $this->convertPhpConfigToProviderFormat($file);
        } elseif ($fileExtension === 'json') {
            // Process JSON file using OrchidImportTrait
            $jsonData = $this->validateAndDecodeJsonFile($file);
            if ($jsonData) {
                // Handle different JSON structures
                if (isset($jsonData['providers']) && is_array($jsonData['providers'])) {
                    // Structure: {"providers": {...}}
                    $providersData = $this->convertJsonProvidersToArray($jsonData['providers']);
                } elseif (is_array($jsonData) && !isset($jsonData['providers'])) {
                    // Structure: Direct array of providers
                    $providersData = $this->convertJsonProvidersToArray($jsonData);
                } else {
                    Toast::error('Invalid JSON structure. Expected "providers" object or direct provider array.');
                    return;
                }
            }
        } else {
            Toast::error('Unsupported file format. Please upload a .php or .json file.');
            return;
        }

        if (!$providersData) {
            return;
        }

        // Process the providers data (same logic for both file types)
        $this->processProvidersImport($providersData, $file->getClientOriginalName());
    }

    /**
     * Convert PHP config file to provider format.
     */
    private function convertPhpConfigToProviderFormat($file): ?array
    {
        try {
            // Get file content
            $tempPath = $file->getRealPath();
            $content = file_get_contents($tempPath);
            
            if (empty($content)) {
                Toast::error('The uploaded PHP file is empty.');
                return null;
            }

            // Create a temporary file for safe inclusion
            $tempFile = tempnam(sys_get_temp_dir(), 'provider_config_');
            file_put_contents($tempFile, $content);

            // Include the PHP file to get the config array
            $config = include $tempFile;
            
            // Clean up temp file
            unlink($tempFile);

            if (!is_array($config)) {
                Toast::error('Invalid PHP config file format. Expected array.');
                return null;
            }

            // Extract providers array - handle different config structures
            $providers = null;
            
            if (isset($config['providers']) && is_array($config['providers'])) {
                // Structure: ['providers' => [...]]
                $providers = $config['providers'];
            } elseif (is_array($config) && !isset($config['providers'])) {
                // Structure: Direct array of providers
                $providers = $config;
            } else {
                Toast::error('No providers found in config file. Expected "providers" key or direct provider array.');
                return null;
            }

            $convertedProviders = [];

            foreach ($providers as $providerKey => $providerData) {
                if (!is_array($providerData)) {
                    continue; // Skip non-array entries
                }

                // Extract only the required keys, ignore all others
                $extractedData = [];
                
                // ID: Use 'id' field if available, otherwise use array key
                $extractedData['id'] = $providerData['id'] ?? $providerKey;
                
                // API Key: Extract if available
                $extractedData['api_key'] = $providerData['api_key'] ?? '';
                
                // API URL: Look for various common names
                $extractedData['api_url'] = $providerData['api_url'] 
                    ?? $providerData['base_url'] 
                    ?? $providerData['url'] 
                    ?? '';
                
                // Ping URL: Extract if available
                $extractedData['ping_url'] = $providerData['ping_url'] 
                    ?? $providerData['models_url'] 
                    ?? $providerData['health_url'] 
                    ?? '';

                // Convert to ProviderSetting format
                $convertedProvider = [
                    'provider_name' => $extractedData['id'],
                    'api_format' => $this->deriveApiFormatFromProviderId($extractedData['id']),
                    'api_key' => $extractedData['api_key'],
                    'base_url' => $extractedData['api_url']
                ];

                $convertedProviders[] = $convertedProvider;
            }

            if (empty($convertedProviders)) {
                Toast::error('No valid providers found in the uploaded file.');
                return null;
            }

            Toast::info("Successfully converted {$file->getClientOriginalName()} - found " . count($convertedProviders) . " providers.");
            return $convertedProviders;

        } catch (\Exception $e) {
            $this->logError('php_config_conversion', [
                'action' => 'Failed to convert PHP config file',
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            Toast::error("Error processing PHP config file: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Derive API format from provider ID.
     */
    private function deriveApiFormatFromProviderId(string $providerId): string
    {
        $providerId = strtolower($providerId);
        
        // Map provider IDs to their API formats
        $apiFormatMap = [
            'openai' => 'openai',
            'openwebui' => 'openai',  // OpenWebUI uses OpenAI-compatible format
            'ollama' => 'ollama',
            'google' => 'google',
            'gemini' => 'google',
            'gwdg' => 'openai',       // GWDG typically uses OpenAI-compatible format
            'anthropic' => 'anthropic',
            'claude' => 'anthropic',
            'mistral' => 'openai',    // Mistral API is OpenAI-compatible
            'groq' => 'openai',       // Groq uses OpenAI-compatible format
            'huggingface' => 'huggingface',
            'cohere' => 'cohere',
            'together' => 'openai',   // Together AI uses OpenAI-compatible format
            'perplexity' => 'openai', // Perplexity uses OpenAI-compatible format
        ];

        // Check for exact matches first
        if (isset($apiFormatMap[$providerId])) {
            return $apiFormatMap[$providerId];
        }

        // Check for partial matches (contains)
        foreach ($apiFormatMap as $pattern => $format) {
            if (str_contains($providerId, $pattern)) {
                return $format;
            }
        }

        // Default to OpenAI format (most common)
        return 'openai';
    }

    /**
     * Convert JSON providers object to array format expected by processProvidersImport.
     */
    private function convertJsonProvidersToArray(array $providers): array
    {
        $convertedProviders = [];

        foreach ($providers as $providerKey => $providerData) {
            if (!is_array($providerData)) {
                continue; // Skip non-array entries
            }

            // Extract data similar to PHP config conversion
            $extractedData = [];
            
            // ID: Use 'id' field if available, otherwise use array key
            $extractedData['id'] = $providerData['id'] ?? $providerKey;
            
            // API Key: Extract if available
            $extractedData['api_key'] = $providerData['api_key'] ?? '';
            
            // API URL: Look for various common names
            $extractedData['api_url'] = $providerData['api_url'] 
                ?? $providerData['base_url'] 
                ?? $providerData['url'] 
                ?? '';
            
            // Ping URL: Extract if available
            $extractedData['ping_url'] = $providerData['ping_url'] 
                ?? $providerData['models_url'] 
                ?? $providerData['health_url'] 
                ?? '';

            // Convert to ProviderSetting format
            $convertedProvider = [
                'provider_name' => $extractedData['id'],
                'api_format' => $this->deriveApiFormatFromProviderId($extractedData['id']),
                'api_key' => $extractedData['api_key'],
                'base_url' => $extractedData['api_url']
            ];

            $convertedProviders[] = $convertedProvider;
        }

        return $convertedProviders;
    }

    /**
     * Process the providers import data.
     */
    private function processProvidersImport(array $providersData, string $filename): void
    {
        // Define allowed keys matching ProviderSetting fillable fields
        $allowedKeys = [
            'provider_name',
            'api_format',
            'api_key',
            'base_url',
            'additional_settings',
            'created_at',
            'updated_at'
        ];

        $results = [
            'total' => count($providersData),
            'imported' => 0,
            'updated' => 0,
            'errors' => [],
            'imported_providers' => [],
            'updated_providers' => [],
            'updated_provider_details' => []
        ];

        foreach ($providersData as $index => $providerData) {
            try {
                // Filter provider data to only allowed keys
                $filteredData = $this->filterImportData($providerData, $allowedKeys);
                
                // Validate required fields
                if (empty($filteredData['provider_name'])) {
                    $results['errors'][] = "Row " . ((int) $index + 1) . ": Missing required provider_name";
                    continue;
                }

                // Handle additional_settings field
                if (isset($filteredData['additional_settings']) && is_string($filteredData['additional_settings'])) {
                    $decoded = json_decode($filteredData['additional_settings'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $filteredData['additional_settings'] = $decoded;
                    }
                }

                // Try to find existing provider by name
                $existingProvider = ProviderSetting::where('provider_name', $filteredData['provider_name'])->first();

                if ($existingProvider) {
                    $existingProvider->update($filteredData);
                    $results['updated']++;
                    $results['updated_providers'][] = $filteredData['provider_name'];
                    
                    // Track which keys were updated for this specific provider
                    $updatedKeys = array_keys($filteredData);
                    $results['updated_provider_details'][] = [
                        'provider_name' => $filteredData['provider_name'],
                        'updated_keys' => $updatedKeys
                    ];
                } else {
                    $newProvider = ProviderSetting::create($filteredData);
                    $results['imported']++;
                    $results['imported_providers'][] = $filteredData['provider_name'];
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Row " . ((int) $index + 1) . ": " . $e->getMessage();
                
                $this->logError('provider_import', [
                    'action' => 'Failed to import provider from file',
                    'row' => (int) $index + 1,
                    'data' => $providerData,
                    'error' => $e->getMessage(),
                    'filename' => $filename
                ]);
            }
        }

        // Log overall import results with comprehensive details
        $this->logBatchOperation('provider_import', 'providers', [
            'total' => (int) $results['total'],
            'imported' => (int) $results['imported'],
            'updated' => (int) $results['updated'],
            'errors_count' => count($results['errors']),
            'success_count' => (int) $results['imported'] + (int) $results['updated'],
            'action' => 'Bulk provider import from file',
            'filename' => $filename,
            'file_type' => pathinfo($filename, PATHINFO_EXTENSION),
            'imported_providers' => $results['imported_providers'],
            'updated_providers' => $results['updated_providers'],
            'updated_provider_details' => $results['updated_provider_details'],
            'errors' => count($results['errors']) > 0 ? array_slice($results['errors'], 0, 3) : []
        ]);

        // Display results using OrchidImportTrait
        $this->displayImportResults($results, 'provider', $results['errors']);
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
