<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

use Carbon\CarbonInterface;

/**
 * A freshly minted share link as handed to its creator: the plain token
 * exists only here, the database keeps its hash.
 */
final readonly class AssistantShareLinkToken
{
    public function __construct(
        public string $token,
        public bool $canEdit,
        public CarbonInterface $expiresAt,
    ) {
    }
}
