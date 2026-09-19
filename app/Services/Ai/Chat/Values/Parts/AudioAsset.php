<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Inline binary audio data (base64) with its media type.
 */
readonly class AudioAsset
{
    public function __construct(
        public string $data,
        public string $mediaType,
    ) {
    }
}
