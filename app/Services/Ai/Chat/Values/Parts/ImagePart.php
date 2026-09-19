<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Image content, referenced either by URL or by inline {@see ImageAsset} data.
 */
readonly class ImagePart implements ContentPart
{
    public const string TYPE = 'image';

    /**
     * @param null|array<string, mixed> $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public ?string $imageUrl = null,
        public ?ImageAsset $imageData = null,
        public ?string $detail = null,
        public ?array $providerMetadata = null,
    ) {
    }

    public static function fromUrl(string $url): self
    {
        return new self(imageUrl: $url);
    }
}
