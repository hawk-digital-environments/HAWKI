<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Capabilities\Values;


use App\Policies\AiToolCapabilityPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * Read-only snapshot of a declared model capability from the {@see AiModelCapabilityRegistry}.
 *
 * Instances are created on demand by the registry; do not instantiate directly.
 * The $iconPath is an absolute filesystem path, not a public URL — the API layer converts
 * it to a data URI before sending it to clients.
 */
#[UsePolicy(AiToolCapabilityPolicy::class)]
readonly class AiModelCapabilityDefinition
{
    public function __construct(
        public string  $key,
        public ?string $titleLabel,
        public ?string $descriptionLabel,
        public ?string $iconPath
    )
    {
    }
}
