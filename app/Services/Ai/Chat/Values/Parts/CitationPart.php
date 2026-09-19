<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * A citation attached to generated content, in either the URL ({@see UrlCitation}) or
 * text ({@see TextCitation}) flavour.
 */
readonly class CitationPart implements ContentPart
{
    public const string TYPE = 'citation';

    public function __construct(
        public ?UrlCitation $urlCitation = null,
        public ?TextCitation $textCitation = null,
    ) {
    }
}
