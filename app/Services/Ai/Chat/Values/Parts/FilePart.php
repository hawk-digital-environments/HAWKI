<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Arbitrary file content, referenced either by URL or by inline {@see FileAsset} data.
 */
readonly class FilePart implements ContentPart
{
    public const string TYPE = 'file';

    /**
     * @param null|array<string, mixed> $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public ?string $fileUrl = null,
        public ?FileAsset $fileData = null,
        public ?string $fileName = null,
        public ?string $fileType = null,
        public ?array $providerMetadata = null,
    ) {
    }

    public static function fromUrl(string $url, ?string $name = null): self
    {
        return new self(fileUrl: $url, fileName: $name);
    }
}
