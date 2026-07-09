<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\ModelList;


use App\Services\Ai\Exceptions\ModelListRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;

/**
 * Thin HTTP wrapper for fetching a provider's model-list endpoint.
 *
 * Created via {@see AbstractProviderAdapter::createModelListClient()} and used
 * inside {@see ProviderAdapterInterface::checkModelStatus()} and
 * {@see ProviderAdapterInterface::getModels()} implementations to query the
 * provider's REST API and turn the response into a {@see ModelListResponse}.
 *
 * The request factory closure is called lazily on each {@see self::get()} call,
 * so authentication headers and base URLs are resolved at call time from the
 * already-configured provider HTTP client.
 *
 * Usage example inside a provider adapter:
 *
 * ```php
 * public function checkModelStatus(
 *     AiModelOnlineStatusCollection $statusCollection,
 *     AiModelDemandCollection $demandCollection,
 *     ProviderProxyWithAdapterAndDriver $provider,
 * ): void {
 *     foreach ($this->createModelListClient($provider)->get('/models')->getList('data.*') as $data) {
 *         $statusCollection->setOnline(data_get($data, 'id'));
 *     }
 * }
 * ```
 */
class ModelListClient
{
    public function __construct(
        /**
         * Called on every {@see self::get()} to produce a fresh, pre-configured
         * HTTP client for the provider (base URL, auth headers, timeouts, etc.).
         *
         * @var \Closure(): PendingRequest
         */
        private readonly \Closure $requestFactory
    )
    {
    }

    /**
     * Performs a GET request against the given provider-relative URL and wraps
     * the response in a {@see ModelListResponse} for structured data extraction.
     *
     * @throws ModelListRequestException when the connection fails or the server
     *                                   returns a non-2xx status code.
     */
    public function get(string $url): ModelListResponse
    {
        try {
            $response = ($this->requestFactory)()->get($url);
        } catch (ConnectionException $e) {
            throw ModelListRequestException::forConnectionFailure($url, $e);
        }
        if (!$response->successful()) {
            throw ModelListRequestException::forUnsuccessfulResponse($url, $response->body());
        }

        return new ModelListResponse($response);
    }
}
