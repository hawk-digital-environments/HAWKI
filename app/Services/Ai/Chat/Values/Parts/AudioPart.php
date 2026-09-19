<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Audio content, referenced either by URL or by inline {@see AudioAsset} data.
 */
readonly class AudioPart implements ContentPart
{
    public const string TYPE = 'audio';

    /**
     * @param null|array<string, mixed> $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public ?string $url = null,
        public ?AudioAsset $audioData = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
