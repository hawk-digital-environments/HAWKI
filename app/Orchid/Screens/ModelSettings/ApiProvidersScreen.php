<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Models\ApiProvider;
use App\Orchid\Layouts\ModelSettings\ApiManagementTabMenu;
use App\Orchid\Layouts\ModelSettings\ApiProvidersFiltersLayout;
use App\Orchid\Layouts\ModelSettings\ApiProvidersImportLayout;
use App\Orchid\Layouts\ModelSettings\ApiProvidersListLayout;
use App\Orchid\Traits\AiConnectionTrait;
use App\Orchid\Traits\OrchidImportTrait;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\ApiProvidersService;
use App\Models\AiModel;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ApiProvidersScreen extends Screen
{
    use AiConnectionTrait, OrchidImportTrait, OrchidLoggingTrait;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'providers' => ApiProvider::with(['apiFormat', 'aiModels'])
                ->filters(ApiProvidersFiltersLayout::class)
                ->defaultSort('display_order', 'asc')
                ->paginate(),
            'sync_status' => $this->getSyncStatus(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
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
        $buttons = [];

        // Only show export button in non-production environments
        if (app()->environment() !== 'production') {
            $buttons[] = Button::make('Export')
                ->icon('bs.upload')
                ->method('exportProvidersToJson')
                ->rawClick()
                ->confirm('Export all provider settings to JSON file? This will include API keys, so make sure your export data is secured accordingly. This feature is disabled for production environment!');
        }

        $buttons[] = ModalToggle::make('Import')
            ->icon('bs.download')
            ->modal('importProvidersModal')
            ->method('importProvidersFromJson');

        $buttons[] = Link::make('Add')
            ->icon('bs.plus-circle')
            ->route('platform.models.api.providers.create');

        return $buttons;
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            ApiManagementTabMenu::class,

            ApiProvidersFiltersLayout::class,
            ApiProvidersListLayout::class,

            Layout::modal('importProvidersModal', ApiProvidersImportLayout::class)
                ->title('Import Providers from JSON')
                ->applyButton('Import')
                ->closeButton('Cancel'),
        ];
    }

    /**
     * Toggle the active status of a provider.
     */
    public function toggleStatus(Request $request): void
    {
        $provider = ApiProvider::findOrFail($request->get('id'));

        $newStatus = ! $provider->is_active;
        
        // If activating the provider, run connection test automatically
        if ($newStatus) {
            // First activate the provider, then test connection
            $provider->update(['is_active' => $newStatus]);
            
            $startTime = microtime(true);
            
            try {
                // Use trait method for connection testing (provider is now active)
                $result = $this->testConnection($provider);
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                // Store connection test result in additional_settings
                $additionalSettings = $provider->additional_settings ?? [];
                $additionalSettings['last_connection_test'] = [
                    'timestamp' => now()->toISOString(),
                    'success' => $result['success'],
                    'response_time_ms' => $responseTime,
                    'endpoint' => $result['endpoint'] ?? null,
                    'error' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
                ];
                
                $provider->update(['additional_settings' => $additionalSettings]);
                
                if ($result['success']) {
                    Toast::success("Provider '{$provider->provider_name}' activated successfully! Connection test: {$responseTime}ms");
                } else {
                    Toast::warning("Provider '{$provider->provider_name}' activated but connection test failed: {$result['error']}");
                }
                
            } catch (\Exception $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                // Store failed connection test result
                $additionalSettings = $provider->additional_settings ?? [];
                $additionalSettings['last_connection_test'] = [
                    'timestamp' => now()->toISOString(),
                    'success' => false,
                    'response_time_ms' => $responseTime,
                    'endpoint' => null,
                    'error' => $e->getMessage(),
                ];
                
                $provider->update(['additional_settings' => $additionalSettings]);
                
                Toast::warning("Provider '{$provider->provider_name}' activated but connection test failed: {$e->getMessage()}");
            }
        } else {
            // Just deactivate without testing
            $provider->update(['is_active' => $newStatus]);
            Toast::info("Provider '{$provider->provider_name}' has been deactivated.");
        }
    }

    /**
     * Export all provider settings to JSON format.
     * Only available in non-production environments for security.
     */
    public function exportProvidersToJson(): \Symfony\Component\HttpFoundation\Response
    {
        // Security check: Only allow in non-production environments
        if (app()->environment() === 'production') {
            Toast::error('Export function is disabled in production environment for security reasons.');

            return redirect()->back();
        }

        try {
            // Get all providers with their API format information from database
            $providers = ApiProvider::with('apiFormat')->get();

            if ($providers->isEmpty()) {
                Toast::warning('No providers found to export.');

                return redirect()->back();
            }

            $exportData = [];

            foreach ($providers as $provider) {
                // Create unique name from provider name (lowercase, replace spaces with hyphens)
                $uniqueName = strtolower(str_replace(' ', '-', $provider->provider_name));

                $exportData[$uniqueName] = [
                    'provider_name' => $provider->provider_name,
                    'api_format' => $provider->apiFormat ? $provider->apiFormat->unique_name : null,
                    'api_key' => $provider->api_key,
                    'base_url' => $provider->base_url,
                    'is_active' => $provider->is_active,
                    'display_order' => $provider->display_order,
                ];
            }

            // Generate filename with timestamp and environment
            $timestamp = now()->format('Y-m-d_H-i-s');
            $environment = app()->environment();
            $filename = "provider_settings_export_{$environment}_{$timestamp}.json";

            // Create JSON response with download
            $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            Toast::success("Successfully exported {$providers->count()} provider settings to {$filename}")
                ->autoHide(false);
            Toast::warning('⚠️ This export contains API keys! Handle with care.')
                ->autoHide(false);

            return response($jsonContent)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");

        } catch (\Exception $e) {
            $this->logError('provider_export', $e, [
                'action' => 'Failed to export provider settings',
                'environment' => app()->environment(),
            ]);

            Toast::error('Error exporting provider settings: '.$e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Import providers from JSON or PHP config file.
     */
    public function importProvidersFromJson(Request $request): void
    {
        // Validate uploaded file using OrchidImportTrait with file extension validation
        // Use extensions instead of mimes to avoid MIME type detection issues
        $file = $this->validateImportFile($request, 'importFile', 'json,php', 2048);
        if (! $file) {
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
                } elseif (is_array($jsonData) && ! isset($jsonData['providers'])) {
                    // Check if this is the new export format or legacy format
                    $isNewFormat = $this->isNewExportFormat($jsonData);

                    if ($isNewFormat) {
                        // Structure: Direct object with unique_name keys and {provider_name, api_format, api_key} values
                        $providersData = $this->convertJsonProvidersToArray($jsonData);
                    } else {
                        // Structure: Direct array of providers (legacy)
                        $providersData = $this->convertJsonProvidersToArray($jsonData);
                    }
                } else {
                    Toast::error('Invalid JSON structure. Expected "providers" object or direct provider array.');

                    return;
                }
            }
        } else {
            Toast::error('Unsupported file format. Please upload a .php or .json file.');

            return;
        }

        if (! $providersData) {
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

            if (! is_array($config)) {
                Toast::error('Invalid PHP config file format. Expected array.');

                return null;
            }

            // Extract providers array - handle different config structures
            $providers = null;

            if (isset($config['providers']) && is_array($config['providers'])) {
                // Structure: ['providers' => [...]]
                $providers = $config['providers'];
            } elseif (is_array($config) && ! isset($config['providers'])) {
                // Structure: Direct array of providers
                $providers = $config;
            } else {
                Toast::error('No providers found in config file. Expected "providers" key or direct provider array.');

                return null;
            }

            $convertedProviders = [];

            foreach ($providers as $providerKey => $providerData) {
                if (! is_array($providerData)) {
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

                // Convert to ApiProvider format
                $convertedProvider = [
                    'provider_name' => $extractedData['id'],
                    'api_format_id' => $this->deriveApiFormatFromProviderId($extractedData['id']),
                    'api_key' => $extractedData['api_key'],
                    'base_url' => $extractedData['api_url'],
                    'display_order' => $this->getDisplayOrderForProvider($extractedData['id']),
                ];

                $convertedProviders[] = $convertedProvider;
            }

            if (empty($convertedProviders)) {
                Toast::error('No valid providers found in the uploaded file.');

                return null;
            }

            Toast::info("Successfully converted {$file->getClientOriginalName()} - found ".count($convertedProviders).' providers.');

            return $convertedProviders;

        } catch (\Exception $e) {
            $this->logError('php_config_conversion', $e, [
                'action' => 'Failed to convert PHP config file',
                'filename' => $file->getClientOriginalName(),
            ]);

            Toast::error('Error processing PHP config file: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Derive API format from provider ID using database.
     */
    private function deriveApiFormatFromProviderId(string $providerId): ?int
    {
        $providerId = strtolower($providerId);

        // Get all API formats from database
        $apiFormats = ApiFormat::all();

        // Check for exact matches first
        foreach ($apiFormats as $format) {
            if ($format->unique_name === $providerId) {
                return $format->id;
            }
        }

        // Check for partial matches (contains) based on metadata
        foreach ($apiFormats as $format) {
            $compatibleProviders = $format->metadata['compatible_providers'] ?? [];
            if (in_array($providerId, $compatibleProviders)) {
                return $format->id;
            }

            // Also check if provider ID contains format unique_name
            if (str_contains($providerId, $format->unique_name)) {
                return $format->id;
            }
        }

        // Default to OpenAI format (most common) - find by unique_name
        $defaultFormat = ApiFormat::where('unique_name', 'openai-api')->first();

        return $defaultFormat?->id;
    }

    /**
     * Get API format ID by unique name.
     */
    private function getApiFormatIdByUniqueName(string $uniqueName): ?int
    {
        if (empty($uniqueName)) {
            return null;
        }

        $apiFormat = ApiFormat::where('unique_name', $uniqueName)->first();

        return $apiFormat?->id;
    }

    /**
     * Get appropriate display_order for a provider based on its name.
     */
    private function getDisplayOrderForProvider(string $providerName): int
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

    /**
     * Check if JSON data is in the new export format.
     */
    private function isNewExportFormat(array $data): bool
    {
        // Check if any entry has the new format structure
        foreach ($data as $key => $value) {
            if (is_array($value) &&
                isset($value['provider_name']) &&
                isset($value['api_format'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert JSON providers object to array format expected by processProvidersImport.
     */
    private function convertJsonProvidersToArray(array $providers): array
    {
        $convertedProviders = [];

        foreach ($providers as $providerKey => $providerData) {
            if (! is_array($providerData)) {
                continue; // Skip non-array entries
            }

            // Check if this is the new export format
            if (isset($providerData['provider_name']) && isset($providerData['api_format'])) {
                // New export format: {unique_name: {provider_name, api_format, api_key}}
                $convertedProvider = [
                    'provider_name' => $providerData['provider_name'],
                    'api_format_id' => $this->getApiFormatIdByUniqueName($providerData['api_format']),
                    'api_key' => $providerData['api_key'] ?? null,
                    'base_url' => $providerData['base_url'] ?? null,
                    'display_order' => $providerData['display_order'] ?? $this->getDisplayOrderForProvider($providerData['provider_name']),
                ];

                $convertedProviders[] = $convertedProvider;

                continue;
            }

            // Legacy format: Extract data similar to PHP config conversion
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

            // Convert to ApiProvider format
            $convertedProvider = [
                'provider_name' => $extractedData['id'],
                'api_format_id' => $this->deriveApiFormatFromProviderId($extractedData['id']),
                'api_key' => $extractedData['api_key'],
                'base_url' => $extractedData['api_url'],
                'display_order' => $this->getDisplayOrderForProvider($extractedData['id']),
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
        // Define allowed keys matching ApiProvider fillable fields
        $allowedKeys = [
            'provider_name',
            'api_format_id',
            'api_key',
            'base_url',
            'is_active',
            'display_order',
            'additional_settings',
            'created_at',
            'updated_at',
        ];

        $results = [
            'total' => count($providersData),
            'imported' => 0,
            'updated' => 0,
            'errors' => [],
            'imported_providers' => [],
            'updated_providers' => [],
            'updated_provider_details' => [],
        ];

        foreach ($providersData as $index => $providerData) {
            try {
                // Filter provider data to only allowed keys
                $filteredData = $this->filterImportData($providerData, $allowedKeys);

                // Validate required fields
                if (empty($filteredData['provider_name'])) {
                    $results['errors'][] = 'Row '.((int) $index + 1).': Missing required provider_name';

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
                $existingProvider = ApiProvider::where('provider_name', $filteredData['provider_name'])->first();

                if ($existingProvider) {
                    $existingProvider->update($filteredData);
                    $results['updated']++;
                    $results['updated_providers'][] = $filteredData['provider_name'];

                    // Track which keys were updated for this specific provider
                    $updatedKeys = array_keys($filteredData);
                    $results['updated_provider_details'][] = [
                        'provider_name' => $filteredData['provider_name'],
                        'updated_keys' => $updatedKeys,
                    ];
                } else {
                    $newProvider = ApiProvider::create($filteredData);
                    $results['imported']++;
                    $results['imported_providers'][] = $filteredData['provider_name'];
                }
            } catch (\Exception $e) {
                $results['errors'][] = 'Row '.((int) $index + 1).': '.$e->getMessage();

                $this->logError('provider_import', $e, [
                    'action' => 'Failed to import provider from file',
                    'row' => (int) $index + 1,
                    'data' => $providerData,
                    'filename' => $filename,
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
            'errors' => count($results['errors']) > 0 ? array_slice($results['errors'], 0, 3) : [],
        ]);

        // Display results using OrchidImportTrait
        $this->displayImportResults($results, 'provider', $results['errors']);
    }

    /**
     * Test connection to a provider using direct HTTP requests (DB config only).
     */
    public function testProviderConnection(Request $request): void
    {
        $providerId = $request->get('id');
        $provider = ApiProvider::find($providerId);

        if (!$provider) {
            Toast::error('Provider not found.');
            return;
        }

        if (!$provider->is_active) {
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");
            return;
        }

        $startTime = microtime(true);

        try {
            // Use trait method for connection testing
            $result = $this->testConnection($provider);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // Store connection test result in additional_settings
            $additionalSettings = $provider->additional_settings ?? [];
            $additionalSettings['last_connection_test'] = [
                'timestamp' => now()->toISOString(),
                'success' => $result['success'],
                'response_time_ms' => $responseTime,
                'endpoint' => $result['endpoint'] ?? null,
                'error' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            ];
            
            $provider->update(['additional_settings' => $additionalSettings]);

            if ($result['success']) {
                Toast::success("Connection to '{$provider->provider_name}' successful! Response time: {$responseTime}ms");
                
                $this->logInfo('provider_connection_test', [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider_name,
                    'response_time_ms' => $responseTime,
                    'endpoint' => $result['endpoint'] ?? 'unknown',
                    'status' => 'success'
                ]);
            } else {
                Toast::error("❌ Connection to '{$provider->provider_name}' failed: {$result['error']}");
                
                $this->logWarning('provider_connection_test', [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider_name,
                    'response_time_ms' => $responseTime,
                    'error' => $result['error'],
                    'status' => 'failed'
                ]);
            }
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Store failed connection test result
            $additionalSettings = $provider->additional_settings ?? [];
            $additionalSettings['last_connection_test'] = [
                'timestamp' => now()->toISOString(),
                'success' => false,
                'response_time_ms' => $responseTime,
                'endpoint' => null,
                'error' => $e->getMessage(),
            ];
            
            $provider->update(['additional_settings' => $additionalSettings]);
            
            Toast::error("Failed to test provider connection: {$e->getMessage()}");

            $this->logError('provider_connection_test', $e, [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name,
                'response_time_ms' => $responseTime,
                'status' => 'exception'
            ]);
        }
    }

    /**
     * Fetch available models from a provider using direct HTTP requests (DB config only).
     */
    public function fetchProviderModels(Request $request): void
    {
        $providerId = $request->get('id');
        $provider = ApiProvider::find($providerId);

        if (!$provider) {
            Toast::error('Provider not found.');
            return;
        }

        if (!$provider->is_active) {
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");
            return;
        }

        try {
            // Use trait method for fetching models
            $models = $this->fetchModelsDirectly($provider);

            if (empty($models)) {
                Toast::info("No models found for provider '{$provider->provider_name}'.");
                return;
            }

            // Use trait method for saving models to database
            $saved = $this->saveModelsToDatabase($provider, $models);

            if ($saved['success']) {
                $message = "Successfully processed {$saved['total']} models from '{$provider->provider_name}'. Created: {$saved['created']}, Updated: {$saved['updated']}";
                if ($saved['skipped'] > 0) {
                    $message .= ", Skipped: {$saved['skipped']}";
                }
                Toast::success($message);
                
                $this->logInfo('provider_models_fetch', [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider_name,
                    'total_models' => $saved['total'],
                    'created' => $saved['created'],
                    'updated' => $saved['updated'],
                    'skipped' => $saved['skipped'],
                    'status' => 'success'
                ]);
            } else {
                Toast::error("Failed to save models: {$saved['error']}");
                
                $this->logWarning('provider_models_fetch', [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider_name,
                    'error' => $saved['error'],
                    'status' => 'failed'
                ]);
            }

        } catch (\Exception $e) {
            Toast::error("Failed to fetch models from '{$provider->provider_name}': {$e->getMessage()}");

            $this->logError('provider_models_fetch', $e, [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name,
                'status' => 'exception'
            ]);
        }
    }

    /**
     * Delete a provider.
     */
    public function deleteProvider(Request $request, ApiProvidersService $settingsService)
    {
        $providerId = $request->get('id');
        $provider = ApiProvider::find($providerId);

        if (! $provider) {
            Toast::error('Provider not found.');

            return redirect()->back();
        }

        $providerName = $provider->provider_name;

        // Delete provider
        $provider->delete();

        Toast::success("Provider '{$providerName}' was successfully deleted.");

        return redirect()->back();
    }

    /**
     * Delete all models associated with a provider.
     */
    public function deleteProviderModels(Request $request): void
    {
        $providerId = $request->get('id');
        $provider = ApiProvider::find($providerId);

        if (! $provider) {
            Toast::error('Provider not found.');

            return;
        }

        try {
            // Count models before deletion
            $modelCount = AiModel::where('provider_id', $providerId)->count();

            if ($modelCount === 0) {
                Toast::info("No models found for provider '{$provider->provider_name}'.");

                return;
            }

            // Delete all models associated with this provider
            AiModel::where('provider_id', $providerId)->delete();

            Toast::success("Successfully deleted {$modelCount} model(s) from provider '{$provider->provider_name}'.");

            $this->logBatchOperation('provider_models_delete', 'models', [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name,
                'deleted_count' => $modelCount,
                'action' => 'Delete all models for provider',
            ]);

        } catch (\Exception $e) {
            Toast::error("Failed to delete models for '{$provider->provider_name}': {$e->getMessage()}");

            $this->logError('provider_models_delete', $e, [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name,
                'action' => 'Delete all models for provider',
            ]);
        }
    }

    /**
     * Synchronize providers from configuration file.
     */
    public function syncProvidersFromConfig(): void
    {
        try {
            $adapter = app(ProviderSyncAdapter::class);
            $result = $adapter->syncProvidersFromConfig();

            if (empty($result->errors)) {
                Toast::success("✅ Provider sync completed! Created: {$result->created}, Updated: {$result->updated}, Skipped: {$result->skipped}");
            } else {
                $errorCount = count($result->errors);
                Toast::warning("⚠️ Provider sync completed with {$errorCount} error(s). Created: {$result->created}, Updated: {$result->updated}, Skipped: {$result->skipped}");

                // Show first few errors
                foreach (array_slice($result->errors, 0, 3) as $error) {
                    Toast::error($error);
                }
            }

            $this->logBatchOperation('provider_sync', 'providers', [
                'created' => $result->created,
                'updated' => $result->updated,
                'skipped' => $result->skipped,
                'errors_count' => count($result->errors),
                'duration_ms' => $result->additionalInfo['duration_ms'] ?? 0,
                'action' => 'Sync providers from config',
            ]);

        } catch (\Exception $e) {
            Toast::error("Failed to sync providers: {$e->getMessage()}");

            $this->logError('provider_sync', $e, [
                'action' => 'Sync providers from config',
            ]);
        }
    }

    /**
     * Test connections to all active providers.
     */
    public function testAllProviderConnections(): void
    {
        try {
            $activeProviders = ApiProvider::where('is_active', true)->ordered()->get();

            if ($activeProviders->isEmpty()) {
                Toast::warning('No active providers found to test.');

                return;
            }

            $adapter = app(ProviderSyncAdapter::class);
            $results = [
                'total' => $activeProviders->count(),
                'successful' => 0,
                'failed' => 0,
                'details' => [],
            ];

            foreach ($activeProviders as $provider) {
                $result = $adapter->testProviderConnection($provider);

                if ($result->success) {
                    $results['successful']++;
                    $results['details'][] = [
                        'provider' => $provider->provider_name,
                        'status' => 'success',
                        'response_time' => $result->responseTimeMs,
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'provider' => $provider->provider_name,
                        'status' => 'failed',
                        'error' => $result->error,
                    ];
                }
            }

            // Display summary
            if ($results['failed'] === 0) {
                Toast::success("✅ All {$results['successful']} provider connections successful!");
            } else {
                Toast::warning("⚠️ Connection test completed: {$results['successful']} successful, {$results['failed']} failed");
            }

            // Log detailed results
            $this->logBatchOperation('connection_test_all', 'providers', $results);

        } catch (\Exception $e) {
            Toast::error("Failed to test provider connections: {$e->getMessage()}");

            $this->logError('connection_test_all', $e, [
                'action' => 'Test all provider connections',
            ]);
        }
    }

    /**
     * Sync models from all active providers.
     */
    public function syncAllProviderModels(): void
    {
        try {
            $activeProviders = ApiProvider::where('is_active', true)->ordered()->get();

            if ($activeProviders->isEmpty()) {
                Toast::warning('No active providers found to sync.');

                return;
            }

            $modelAdapter = app(ModelSyncAdapter::class);
            $totalResults = [
                'providers_processed' => 0,
                'total_models' => 0,
                'models_created' => 0,
                'models_updated' => 0,
                'models_skipped' => 0,
                'errors' => [],
            ];

            foreach ($activeProviders as $provider) {
                try {
                    $result = $modelAdapter->syncModelsForProvider($provider);
                    $totalResults['providers_processed']++;
                    $totalResults['total_models'] += $result->totalModels;
                    $totalResults['models_created'] += $result->created;
                    $totalResults['models_updated'] += $result->updated;
                    $totalResults['models_skipped'] += $result->skipped;
                    $totalResults['errors'] = array_merge($totalResults['errors'], $result->errors);

                } catch (\Exception $e) {
                    $totalResults['errors'][] = "Provider '{$provider->provider_name}': {$e->getMessage()}";
                }
            }

            // Display summary
            $errorCount = count($totalResults['errors']);
            if ($errorCount === 0) {
                Toast::success("✅ Model sync completed! Processed {$totalResults['providers_processed']} providers, {$totalResults['total_models']} models (Created: {$totalResults['models_created']}, Updated: {$totalResults['models_updated']}, Skipped: {$totalResults['models_skipped']})");
            } else {
                Toast::warning("⚠️ Model sync completed with {$errorCount} error(s). Processed {$totalResults['providers_processed']} providers, {$totalResults['total_models']} models (Created: {$totalResults['models_created']}, Updated: {$totalResults['models_updated']}, Skipped: {$totalResults['models_skipped']})");

                // Show first few errors
                foreach (array_slice($totalResults['errors'], 0, 3) as $error) {
                    Toast::error($error);
                }
            }

            // Log detailed results
            $this->logBatchOperation('model_sync_all', 'models', $totalResults);

        } catch (\Exception $e) {
            Toast::error("Failed to sync models: {$e->getMessage()}");

            $this->logError('model_sync_all', $e, [
                'action' => 'Sync models from all providers',
            ]);
        }
    }

    /**
     * Sync models for a single provider using the new ModelSyncAdapter.
     */
    public function syncProviderModels(Request $request): void
    {
        $providerId = $request->get('id');
        $provider = ApiProvider::find($providerId);

        if (! $provider) {
            Toast::error('Provider not found.');

            return;
        }

        if (! $provider->is_active) {
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");

            return;
        }

        try {
            $adapter = app(ModelSyncAdapter::class);
            $result = $adapter->syncModelsForProvider($provider);

            if (empty($result->errors)) {
                Toast::success("✅ Model sync for '{$provider->provider_name}' completed! Total: {$result->totalModels}, Created: {$result->created}, Updated: {$result->updated}, Skipped: {$result->skipped}");
            } else {
                $errorCount = count($result->errors);
                Toast::warning("⚠️ Model sync for '{$provider->provider_name}' completed with {$errorCount} error(s). Total: {$result->totalModels}, Created: {$result->created}, Updated: {$result->updated}, Skipped: {$result->skipped}");

                // Show first few errors
                foreach (array_slice($result->errors, 0, 2) as $error) {
                    Toast::error($error);
                }
            }

            $this->logProviderOperation(
                'model_sync',
                $provider->provider_name,
                $provider->id,
                empty($result->errors) ? 'success' : 'warning',
                [
                    'total_models' => $result->totalModels,
                    'created' => $result->created,
                    'updated' => $result->updated,
                    'skipped' => $result->skipped,
                    'errors_count' => count($result->errors),
                    'duration_ms' => $result->additionalInfo['duration_ms'] ?? 0,
                ],
                empty($result->errors) ? 'info' : 'warning'
            );

        } catch (\Exception $e) {
            Toast::error("Failed to sync models for '{$provider->provider_name}': {$e->getMessage()}");

            $this->logProviderOperation(
                'model_sync',
                $provider->provider_name,
                $provider->id,
                'error',
                [
                    'exception' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ],
                'error'
            );
        }
    }

    /**
     * Reorder all providers using default display order logic.
     */
    public function reorderProviders(): void
    {
        try {
            $providers = ApiProvider::all();
            $reorderedCount = 0;

            foreach ($providers as $provider) {
                $newOrder = $this->getDisplayOrderForProvider($provider->provider_name);

                if ($provider->display_order !== $newOrder) {
                    $provider->update(['display_order' => $newOrder]);
                    $reorderedCount++;
                }
            }

            if ($reorderedCount > 0) {
                Toast::success("Successfully reordered {$reorderedCount} providers using default display order logic.");

                $this->logBatchOperation('provider_reorder', 'providers', [
                    'total_providers' => $providers->count(),
                    'reordered_count' => $reorderedCount,
                    'action' => 'Reorder providers using default display order',
                ]);
            } else {
                Toast::info('All providers are already in correct display order.');
            }

        } catch (\Exception $e) {
            Toast::error("Failed to reorder providers: {$e->getMessage()}");

            $this->logError('provider_reorder', $e, [
                'action' => 'Reorder providers using default display order',
            ]);
        }
    }



    /**
     * Get sync status information.
     */
    private function getSyncStatus(): array
    {
        $totalProviders = ApiProvider::count();
        $activeProviders = ApiProvider::where('is_active', true)->count();

        return [
            'total_providers' => $totalProviders,
            'active_providers' => $activeProviders,
            'inactive_providers' => $totalProviders - $activeProviders,
            'last_sync' => ApiProvider::max('updated_at'),
        ];
    }
}
