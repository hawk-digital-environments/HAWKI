<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

use App\Services\Ai\Chat\Values\CitationData;

/**
 * HAWKI extension event (`hawki:citation`): a source citation surfaced during generation.
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
