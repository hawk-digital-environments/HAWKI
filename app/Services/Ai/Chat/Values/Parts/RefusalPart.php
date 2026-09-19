<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * A model refusal with its reason text.
 */
readonly class RefusalPart implements ContentPart
{
    public const string TYPE = 'refusal';

    public function __construct(public string $refusal)
    {
    }
}
