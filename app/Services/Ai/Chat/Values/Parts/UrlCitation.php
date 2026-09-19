<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * URL citation in the OpenAI style: character ranges over the annotated text plus
 * the cited source URL and title.
 */
readonly class UrlCitation
{
    public function __construct(
        public ?int $startIndex = null,
        public ?int $endIndex = null,
        public ?string $title = null,
        public ?string $url = null,
    ) {
    }
}
