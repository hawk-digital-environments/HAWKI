<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OpenApi\OpenApiGenerator;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\JsonResponse;

class OpenApiSpecController extends Controller
{
    /**
     * Cache key for the fully-generated OpenAPI spec. Bump the suffix in this
     * constant whenever the spec shape changes and the cached entry must be
     * invalidated without waiting for the TTL to expire.
     */
    public const string CACHE_KEY = 'hawki:openapi:spec:v1';
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly CacheRepository $cache,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $key = self::CACHE_KEY;
        $spec = $this->cache->get($key);

        if (null === $spec) {
            $store = $this->cache->getStore();

            if ($store instanceof LockProvider) {
                $lock = $store->lock($key . ':lock', 30);

                if ($lock->block(5)) {
                    try {
                        // Another process may have filled it while we waited
                        $spec = $this->cache->get($key);

                        if (null === $spec) {
                            $spec = $this->generator->generate(true);
                            $this->cache->put($key, $spec, self::CACHE_TTL_SECONDS);
                        }
                    } finally {
                        $lock->release();
                    }
                }
            } else {
                $spec = $this->generator->generate(true);
            }
        }

        return response()->json($spec);
    }
}
