<?php
declare(strict_types=1);


namespace App\Services\ExternalContent\Values;


readonly class ResolvedExternalImage
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public bool   $isFallback = false
    )
    {
    }
}
