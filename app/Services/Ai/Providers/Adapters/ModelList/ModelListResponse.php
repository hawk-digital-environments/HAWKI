<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\ModelList;


use App\Services\Ai\Exceptions\ModelListResponseException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * Wraps the raw HTTP response from a provider's model-list endpoint and exposes
 * structured accessors for extracting data via Laravel dot-notation paths.
 *
 * The JSON body is decoded lazily and cached on the first call to {@see self::getAll()},
 * so subsequent extractions (getList, getMapped, getOne) do not re-parse the response.
 */
class ModelListResponse
{
    private array|null $data = null;

    public function __construct(
        public readonly Response $response
    )
    {
    }

    /**
     * Decodes and returns the full JSON response body as a PHP array.
     *
     * The result is cached — repeated calls return the same array without
     * re-parsing the response body.
     *
     * @throws ModelListResponseException when the body is not valid JSON or the
     *                                    top-level value is not an array.
     */
    public function getAll(): array
    {
        if ($this->data === null) {
            try {
                $data = $this->response->json(flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ModelListResponseException::forInvalidJson($this->response->body(), $e);
            }
            if (!is_array($data)) {
                throw ModelListResponseException::forNonArrayResponse(get_debug_type($data));
            }
            $this->data = $data;
        }

        return $this->data;
    }

    /**
     * Extracts a single value from the response using a Laravel dot-notation path.
     *
     * Returns `null` when the path does not exist. Use {@see self::getList()} when
     * the value at the path must be an array.
     */
    public function getOne(string $extractPath): mixed
    {
        return data_get($this->getAll(), $extractPath);
    }

    /**
     * Extracts a list of items from the response at the given dot-notation path.
     *
     * Wildcard segments (`*`) are supported and follow Laravel's `data_get` rules,
     * e.g. `'data.*'` collects every element of the `data` array.
     *
     * @return Collection<int, mixed>
     * @throws ModelListResponseException when the value at the path is not an array.
     */
    public function getList(string $extractPath): Collection
    {
        $list = data_get($this->getAll(), $extractPath);
        if (!is_array($list)) {
            throw ModelListResponseException::forNonArrayExtract($extractPath, $list);
        }
        return collect($list);
    }

    /**
     * Extracts a list and maps each item through `$mapCallback`, dropping any
     * items for which the callback returns `null`.
     *
     * Typical use: transform raw provider model objects into typed value objects
     * while silently skipping incomplete or unknown entries.
     *
     * ```php
     * $models = $response->getMapped('data.*', function (mixed $item) use ($provider): ?ModelInfo {
     *     $id = data_get($item, 'id');
     *     return $id ? $this->createNewModelInfo(modelId: $id, provider: $provider) : null;
     * });
     * ```
     *
     * @return Collection<int, mixed>
     * @throws ModelListResponseException when the value at `$extractPath` is not an array.
     */
    public function getMapped(string $extractPath, callable $mapCallback): Collection
    {
        return $this->getList($extractPath)->map($mapCallback)->filter(fn($item) => $item !== null);
    }
}
