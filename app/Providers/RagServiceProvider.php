<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Implementations\HawkiRagIngester;
use App\Services\Rag\Implementations\NullRagIngester;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the RAG ingestion contract to the configured backend driver,
 * mirroring the FileConverterServiceProvider pattern: a `null`/unknown
 * driver resolves to a no-op implementation instead of failing at boot.
 */
class RagServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(RagIngesterInterface::class, static function ($app): RagIngesterInterface {
            $driver = (string)$app->make('config')->get('rag.driver', 'hawki_rag');

            return match ($driver) {
                'hawki_rag' => $app->make(HawkiRagIngester::class),
                default => $app->make(NullRagIngester::class),
            };
        });
    }

    public function provides(): array
    {
        return [RagIngesterInterface::class];
    }
}
