<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Models\ProviderSetting;
use App\Orchid\Layouts\ModelSettings\ApiManagementTabMenu;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsFiltersLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsImportLayout;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsListLayout;
use App\Orchid\Traits\AiConnectionTrait;
use App\Orchid\Traits\OrchidImportTrait;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\ProviderSettingsService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProviderSettingsScreen extends Screen
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
            'providers' => ProviderSetting::with('apiFormat')
                ->filters(ProviderSettingsFiltersLayout::class)
                ->defaultSort('id', 'desc')
                ->paginate(),
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

            ProviderSettingsFiltersLayout::class,
            ProviderSettingsListLayout::class,

            Layout::modal('importProvidersModal', ProviderSettingsImportLayout::class)
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
        $provider = ProviderSetting::findOrFail($request->get('id'));

        $newStatus = ! $provider->is_active;
        $provider->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'activated' : 'deactivated';
        Toast::info("Provider '{$provider->provider_name}' has been {$statusText}.");
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
            $providers = ProviderSetting::with('apiFormat')->get();

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
            $this->logError('provider_export', [
                'action' => 'Failed to export provider settings',
                'environment' => app()->environment(),
                'error' => $e->getMessage(),
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

                // Convert to ProviderSetting format
                $convertedProvider = [
                    'provider_name' => $extractedData['id'],
                    'api_format_id' => $this->deriveApiFormatFromProviderId($extractedData['id']),
                    'api_key' => $extractedData['api_key'],
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
            $this->logError('php_config_conversion', [
                'action' => 'Failed to convert PHP config file',
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
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

            // Convert to ProviderSetting format
            $convertedProvider = [
                'provider_name' => $extractedData['id'],
                'api_format_id' => $this->deriveApiFormatFromProviderId($extractedData['id']),
                'api_key' => $extractedData['api_key'],
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
            'api_format_id',
            'api_key',
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
                $existingProvider = ProviderSetting::where('provider_name', $filteredData['provider_name'])->first();

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
                    $newProvider = ProviderSetting::create($filteredData);
                    $results['imported']++;
                    $results['imported_providers'][] = $filteredData['provider_name'];
                }
            } catch (\Exception $e) {
                $results['errors'][] = 'Row '.((int) $index + 1).': '.$e->getMessage();

                $this->logError('provider_import', [
                    'action' => 'Failed to import provider from file',
                    'row' => (int) $index + 1,
                    'data' => $providerData,
                    'error' => $e->getMessage(),
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
     * Test connection to a provider using the AiConnectionTrait.
     */
    public function testProviderConnection(Request $request): void
    {
        $providerId = $request->get('id');
        $provider = ProviderSetting::with('apiFormat.endpoints')->find($providerId);

        if (! $provider) {
            Toast::error('Provider not found.');

            return;
        }

        // Use the trait method for consistent connection testing
        $this->testConnection($provider);
    }

    /**
     * Fetch available models from a provider.
     */
    public function fetchProviderModels(Request $request): void
    {
        $providerId = $request->get('id');
        $provider = ProviderSetting::with('apiFormat.endpoints')->find($providerId);

        if (! $provider) {
            Toast::error('Provider not found.');

            return;
        }

        if (! $provider->is_active) {
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");

            return;
        }

        try {
            // Use the AI Provider Factory to get models directly
            $providerFactory = app(\App\Services\AI\AIProviderFactory::class);
            $aiProvider = $providerFactory->getProviderInterfaceById($provider->id);

            // Fetch models from the API
            $modelsResponse = $aiProvider->fetchAvailableModelsFromAPI();

            // Import models into database using ModelSettingsService
            $modelSettingsService = app(\App\Services\Settings\ModelSettingsService::class);

            $importResults = $modelSettingsService->importModelsFromApiResponse(
                $provider->id,
                $modelsResponse
            );

            if ($importResults['success']) {
                $totalModels = $importResults['total'] ?? 0;
                $importedModels = $importResults['imported'] ?? 0;
                $updatedModels = $importResults['updated'] ?? 0;

                if ($importedModels > 0 || $updatedModels > 0) {
                    Toast::success("Successfully imported models from provider '{$provider->provider_name}'. Total: {$totalModels}, Imported: {$importedModels}, Updated: {$updatedModels}");
                } else {
                    Toast::info("Connected to provider '{$provider->provider_name}' successfully. Found {$totalModels} models, but none were new or changed.");
                }

                // Log successful operation
                $this->logProviderOperation(
                    'models_fetch',
                    $provider->provider_name,
                    $provider->id,
                    'success',
                    [
                        'total_models' => $totalModels,
                        'imported_models' => $importedModels,
                        'updated_models' => $updatedModels,
                        'message' => "Total: {$totalModels}, Imported: {$importedModels}, Updated: {$updatedModels}",
                    ],
                    'info'
                );
            } else {
                $error = $importResults['error'] ?? 'Unknown error during import';
                Toast::error("Failed to import models: {$error}");

                $this->logProviderOperation(
                    'models_fetch',
                    $provider->provider_name,
                    $provider->id,
                    'error',
                    [
                        'error' => $error,
                        'import_results' => $importResults,
                    ],
                    'error'
                );
            }

        } catch (\Exception $e) {
            $errorMessage = "Failed to fetch models from provider '{$provider->provider_name}': ".$e->getMessage();
            Toast::error($errorMessage);

            $this->logProviderOperation(
                'models_fetch',
                $provider->provider_name,
                $provider->id,
                'error',
                [
                    'exception' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'error_message' => $errorMessage,
                ],
                'error'
            );
        }
    }

    /**
     * Delete a provider.
     */
    public function deleteProvider(Request $request, ProviderSettingsService $settingsService)
    {
        $providerId = $request->get('id');
        $provider = ProviderSetting::find($providerId);

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
}
