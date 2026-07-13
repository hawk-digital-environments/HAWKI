<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ClientSchema\ClientSchemaGenerator;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSchemaController extends Controller
{
    /**
     * Per-user client-schema cache key prefix. The full key includes the user
     * id so that policy-gated fields (allowed/writable flags) don't leak
     * between callers. Bump the suffix to force re-generation.
     */
    public const string CACHE_KEY_PREFIX = 'hawki:client-schema:user:v1:';
    private const int CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly ClientSchemaGenerator $generator,
        private readonly CacheRepository $cache,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Same test-env bypass rationale as OpenApiSpecController: tests
        // routinely swap the Server binding and per-user policy state should
        // not be cached across test cases.
        if ('testing' === app()->environment()) {
            return response()->json($this->generator->generate($request->user()));
        }

        $user = $request->user();
        $cacheKey = self::CACHE_KEY_PREFIX . $user->id;

        $schema = $this->cache->remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => $this->generator->generate($user));

        return response()->json($schema);
    }
}

