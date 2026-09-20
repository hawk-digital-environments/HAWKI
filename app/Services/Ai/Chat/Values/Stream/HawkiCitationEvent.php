<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

use App\Services\Ai\Chat\Values\CitationData;

/**
 * HAWKI citation event: a source citation surfaced during generation.
 *
 * Rendered spec-natively by the openResponses formatter as a
 * `response.output_text.annotation.added` url_citation (and as annotations on the
 * message item's output_text part); the openai formatter emits it as a custom,
 * env-switched `hawki:citation` frame (the Chat Completions format has no native slot).
 *
 * Uses the Open Responses extension convention: clients that do not understand the
 * `hawki:` type prefix can safely ignore the event and still reconstruct the canonical
 * response.
 */
readonly class HawkiCitationEvent implements AiStreamEvent
{
    public const string TYPE = 'hawki:citation';

    public function __construct(public CitationData $citation)
    {
    }
}
