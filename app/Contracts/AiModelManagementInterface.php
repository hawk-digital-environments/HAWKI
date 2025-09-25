<?php

namespace App\Contracts;

/**
 * AI Model Management Interface
 * 
 * This interface defines the contract for AI model management services.
 * It serves as a blueprint extracted from the deprecated ModelSettingsService
 * for future implementation in new AI services.
 * 
 * @version 1.0
 * @since 2025-09-25
 * @see App\Services\Settings\ModelSettingsService.deprecated.php (original implementation)
 */
interface AiModelManagementInterface
{
    /**
     * Retrieve available models from a provider's API.
     * 
     * This method should handle:
     * - API authentication (API keys, bearer tokens, etc.)
     * - Provider-specific API endpoints and formats
     * - Error handling and timeout management
     * - Response normalization across different providers
     * 
     * @param string $providerName The name/identifier of the AI provider
     * @return array Normalized array of available models
     * @throws \Exception When provider is not found, inactive, or API request fails
     */
    public function fetchAvailableModels(string $providerName): array;

    /**
     * Import/synchronize models from API response into the database.
     * 
     * This method should handle:
     * - Model deduplication (prevent duplicates)
     * - Model metadata extraction and normalization
     * - Database transactions for consistency
     * - Batch operations for performance
     * - Audit logging for changes
     * 
     * @param int $providerId The database ID of the provider
     * @param array $apiResponse Raw API response containing models
     * @return array Import statistics ['total', 'created', 'updated', 'errors']
     * @throws \Exception When database operations fail
     */
    public function importModelsFromApiResponse(int $providerId, array $apiResponse): array;

    /**
     * Test connectivity to a provider's API endpoint.
     * 
     * This method should handle:
     * - Connection timeouts and retries
     * - Authentication validation
     * - Response time measurement
     * - Service availability checks
     * 
     * @param string $providerName The name/identifier of the AI provider
     * @return array Connection test results ['success', 'response_time', 'error', 'details']
     * @throws \Exception When connection test cannot be performed
     */
    public function testProviderConnection(string $providerName): array;

    /**
     * Generate a human-readable label for a model.
     * 
     * This method should handle:
     * - Provider-specific model ID formats
     * - Display name extraction from metadata
     * - Fallback naming conventions
     * - Internationalization considerations
     * 
     * @param string $modelId Raw model identifier from provider
     * @param array $modelData Additional model metadata
     * @return string Human-readable model label
     */
    public function generateModelLabel(string $modelId, array $modelData = []): string;

    /**
     * Validate model configuration and availability.
     * 
     * This method should handle:
     * - Model existence verification
     * - Configuration parameter validation
     * - Feature availability checks (streaming, function calling, etc.)
     * - Performance characteristics validation
     * 
     * @param string $providerName The provider name
     * @param string $modelId The model identifier
     * @param array $configuration Optional model configuration to validate
     * @return array Validation results ['valid', 'errors', 'warnings', 'capabilities']
     * @throws \Exception When validation cannot be performed
     */
    public function validateModelConfiguration(string $providerName, string $modelId, array $configuration = []): array;

    /**
     * Get detailed information about a specific model.
     * 
     * This method should handle:
     * - Model capability detection
     * - Pricing information retrieval
     * - Usage limits and quotas
     * - Model versioning information
     * 
     * @param string $providerName The provider name
     * @param string $modelId The model identifier
     * @return array Detailed model information
     * @throws \Exception When model information cannot be retrieved
     */
    public function getModelDetails(string $providerName, string $modelId): array;

    /**
     * Batch synchronize all models for all active providers.
     * 
     * This method should handle:
     * - Parallel processing for multiple providers
     * - Error isolation (one provider failure doesn't stop others)
     * - Progress tracking and reporting
     * - Resource management and rate limiting
     * 
     * @param array $providerNames Optional array to limit sync to specific providers
     * @return array Batch sync results ['total_providers', 'successful', 'failed', 'details']
     * @throws \Exception When batch operation cannot be completed
     */
    public function batchSynchronizeModels(array $providerNames = []): array;
}