<?php

declare(strict_types=1);

namespace App\Orchid\Traits;

use App\Models\ApiProvider;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

trait AiConnectionTrait
{
    /**
     * Test connection to a provider using simple HTTP request (DB config only).
     *
     * @param ApiProvider $provider
     * @return array ['success' => bool, 'error' => string|null, 'endpoint' => string, 'status_code' => int|null]
     */
    public function testConnection(ApiProvider $provider): array
    {
        if (!$provider->is_active) {
            return [
                'success' => false,
                'error' => 'Provider is inactive',
                'endpoint' => 'none'
            ];
        }

        // Try models endpoint first
        $testUrl = $provider->getModelsUrl();
        if (!$testUrl) {
            // Fallback to base URL with /models
            $base = rtrim($provider->base_url ?? '', '/');
            if (!$base) {
                return [
                    'success' => false,
                    'error' => 'No base URL configured',
                    'endpoint' => 'none'
                ];
            }
            $testUrl = $base . '/models';
        }

        try {
            // Use provider-specific authentication method
            $response = $this->makeAuthenticatedRequest($provider, $testUrl);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'endpoint' => $testUrl,
                    'status_code' => $response->status()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $response->status(),
                    'endpoint' => $testUrl,
                    'status_code' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'endpoint' => $testUrl
            ];
        }
    }

    /**
     * Fetch models directly from provider using HTTP requests (DB config only).
     *
     * @param ApiProvider $provider
     * @return array Normalized array of models
     * @throws \Exception
     */
    public function fetchModelsDirectly(ApiProvider $provider): array
    {
        if (!$provider->is_active) {
            throw new \Exception('Provider is inactive');
        }

        $modelsUrl = $provider->getModelsUrl();
        if (!$modelsUrl) {
            // Fallback to common path
            $base = rtrim($provider->base_url ?? '', '/');
            if (!$base) {
                throw new \Exception('No base URL configured');
            }
            $modelsUrl = $base . '/models';
        }

        try {
            // Use provider-specific authentication method
            $response = $this->makeAuthenticatedRequest($provider, $modelsUrl);
        } catch (\Exception $e) {
            throw new \Exception('Request failed: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new \Exception('Provider returned HTTP ' . $response->status() . ' for ' . $modelsUrl);
        }

        $payload = $response->json();

        // Normalize models list from common provider responses
        return $this->normalizeModelsResponse($payload);
    }

    /**
     * Save models to database using updateOrCreate.
     *
     * @param ApiProvider $provider
     * @param array $models
     * @return array ['success' => bool, 'total' => int, 'created' => int, 'updated' => int, 'error' => string|null]
     */
    public function saveModelsToDatabase(ApiProvider $provider, array $models): array
    {
        try {
            $created = 0;
            $updated = 0;
            $order = 1;

            foreach ($models as $m) {
                $modelData = $this->extractModelData($m, $order++);
                
                if (!$modelData['model_id']) {
                    continue;
                }

                $aiModel = AiModel::where([
                    'provider_id' => $provider->id,
                    'model_id' => $modelData['model_id']
                ])->first();

                if ($aiModel) {
                    $aiModel->update([
                        'label' => $modelData['label'],
                        'is_active' => false,
                        'is_visible' => false,
                        'display_order' => $modelData['display_order'],
                        'information' => $modelData['information'],
                    ]);
                    $updated++;
                } else {
                    AiModel::create([
                        'provider_id' => $provider->id,
                        'model_id' => $modelData['model_id'],
                        'label' => $modelData['label'],
                        'is_active' => false,
                        'is_visible' => false,
                        'display_order' => $modelData['display_order'],
                        'information' => $modelData['information'],
                    ]);
                    $created++;
                }
            }

            return [
                'success' => true,
                'total' => count($models),
                'created' => $created,
                'updated' => $updated
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'total' => 0,
                'created' => 0,
                'updated' => 0
            ];
        }
    }

    /**
     * Test connection and fetch models in one operation.
     *
     * @param ApiProvider $provider
     * @param bool $saveToDatabase
     * @return array
     */
    public function testAndFetchModels(ApiProvider $provider, bool $saveToDatabase = false): array
    {
        // Test connection first
        $connectionResult = $this->testConnection($provider);
        if (!$connectionResult['success']) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $connectionResult['error'],
                'connection_test' => $connectionResult
            ];
        }

        try {
            // Fetch models
            $models = $this->fetchModelsDirectly($provider);
            
            $result = [
                'success' => true,
                'connection_test' => $connectionResult,
                'models_count' => count($models),
                'models' => $models
            ];

            // Optionally save to database
            if ($saveToDatabase && !empty($models)) {
                $saveResult = $this->saveModelsToDatabase($provider, $models);
                $result['save_result'] = $saveResult;
                
                if (!$saveResult['success']) {
                    $result['success'] = false;
                    $result['error'] = 'Models fetched but save failed: ' . $saveResult['error'];
                }
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Models fetch failed: ' . $e->getMessage(),
                'connection_test' => $connectionResult
            ];
        }
    }

    /**
     * Make an authenticated HTTP request based on provider-specific authentication method.
     *
     * @param ApiProvider $provider
     * @param string $url
     * @return \Illuminate\Http\Client\Response
     */
    protected function makeAuthenticatedRequest(ApiProvider $provider, string $url)
    {
        $apiFormatName = $provider->apiFormat?->unique_name ?? 'openai-api';
        
        // Google API uses query parameter authentication
        if ($apiFormatName === 'google-generative-language-api') {
            $queryParams = [];
            if (!empty($provider->api_key)) {
                $queryParams['key'] = $provider->api_key;
            }
            
            $headers = $this->buildHttpHeaders($provider, false); // Don't add Authorization header
            
            return Http::withHeaders($headers)
                ->timeout(10)
                ->get($url, $queryParams);
        }
        
        // Default authentication method (Bearer token in headers)
        $headers = $this->buildHttpHeaders($provider, true);
        
        return Http::withHeaders($headers)
            ->timeout(10)
            ->get($url);
    }

    /**
     * Build HTTP headers from provider configuration.
     *
     * @param ApiProvider $provider
     * @param bool $includeAuth Whether to include Authorization header
     * @return array
     */
    protected function buildHttpHeaders(ApiProvider $provider, bool $includeAuth = true): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        // Merge any headers from additional_settings
        $additionalHeaders = $provider->additional_settings['headers'] ?? [];
        if (is_array($additionalHeaders)) {
            foreach ($additionalHeaders as $k => $v) {
                $headers[$k] = $v;
            }
        }

        // Add Authorization header if API key exists and not already set
        if ($includeAuth && !empty($provider->api_key) && !isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ' . $provider->api_key;
        }

        return $headers;
    }

    /**
     * Normalize models response from different provider formats.
     *
     * @param mixed $payload
     * @return array
     */
    protected function normalizeModelsResponse($payload): array
    {
        $models = [];
        
        if (!is_array($payload)) {
            return $models;
        }

        // Some providers return ['models' => [...]] or ['data' => [...]] or array directly
        if (isset($payload['models']) && is_array($payload['models'])) {
            $models = $payload['models'];
        } elseif (isset($payload['data']) && is_array($payload['data'])) {
            $models = $payload['data'];
        } else {
            // If payload is associative but seems like a single model, wrap it
            $isAssoc = array_keys($payload) !== range(0, count($payload) - 1);
            if ($isAssoc && (isset($payload['name']) || isset($payload['id']))) {
                $models = [$payload];
            } else {
                // Maybe it's a numeric-indexed array already
                $models = $payload;
            }
        }

        return $models;
    }

    /**
     * Extract model data from various formats.
     *
     * @param mixed $m
     * @param int $displayOrder
     * @return array
     */
    protected function extractModelData($m, int $displayOrder): array
    {
        if (is_string($m)) {
            $cleanModelId = $this->cleanGoogleModelId($m);
            return [
                'model_id' => $cleanModelId,
                'label' => $cleanModelId,
                'display_order' => $displayOrder,
                'information' => ['source' => 'direct_http']
            ];
        } elseif (is_array($m)) {
            $modelId = $m['id'] ?? $m['name'] ?? ($m['model'] ?? null);
            $cleanModelId = $this->cleanGoogleModelId((string) $modelId);
            
            // For label, prefer displayName (Google API) or fallback to cleaned model ID
            $label = $m['displayName'] ?? $m['name'] ?? $m['id'] ?? $m['model'] ?? $cleanModelId;
            
            return [
                'model_id' => $cleanModelId,
                'label' => $label,
                'display_order' => $displayOrder,
                'information' => array_merge($m, ['source' => 'direct_http'])
            ];
        } elseif (is_object($m)) {
            $modelId = $m->id ?? $m->name ?? null;
            $cleanModelId = $this->cleanGoogleModelId((string) $modelId);
            $label = $m->displayName ?? $m->name ?? $m->id ?? $cleanModelId;
            
            return [
                'model_id' => $cleanModelId,
                'label' => $label,
                'display_order' => $displayOrder,
                'information' => array_merge(json_decode(json_encode($m), true), ['source' => 'direct_http'])
            ];
        }

        return [
            'model_id' => null,
            'label' => 'unknown',
            'display_order' => $displayOrder,
            'information' => ['source' => 'direct_http']
        ];
    }

    /**
     * Clean Google API model IDs by removing 'models/' prefix.
     *
     * @param string $modelId
     * @return string
     */
    protected function cleanGoogleModelId(string $modelId): string
    {
        // Remove 'models/' prefix from Google API model IDs
        if (str_starts_with($modelId, 'models/')) {
            return substr($modelId, 7); // Remove 'models/' (7 characters)
        }
        
        return $modelId;
    }
}
