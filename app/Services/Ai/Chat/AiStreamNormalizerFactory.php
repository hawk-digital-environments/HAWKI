<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat;

use App\Services\ExternalContent\CitationUrlCleaner;
use Illuminate\Container\Attributes\Singleton;
use Psr\Clock\ClockInterface;

/**
 * Creates fresh {@see AiStreamNormalizer} instances.
 *
 * Stream normalization is stateful (block indices, tool-call counters), so normalizers
 * must never be shared between invocations — this factory is the singleton, the
 * normalizer it hands out is per-call.
 */
#[Singleton()]
readonly class AiStreamNormalizerFactory
{
    public function __construct(
        private CitationUrlCleaner $citationCleaner,
        private ClockInterface $clock,
    ) {
    }

    public function create(): AiStreamNormalizer
    {
        return new AiStreamNormalizer(
            citationCleaner: $this->citationCleaner,
            clock: $this->clock,
        );
    }
}
