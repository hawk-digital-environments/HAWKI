<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Text citation in the Anthropic style: the cited text itself, without a URL.
 */
readonly class TextCitation
{
    public function __construct(public ?string $citedText = null)
    {
    }
}
