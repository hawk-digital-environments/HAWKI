<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Inline binary image data (base64) with its media type.
 */
readonly class ImageAsset
{
    public function __construct(
        public string $data,
        public string $mediaType,
    ) {
    }
}
