<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Flags\Values;


use App\Policies\AiModelFlagPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * Read-only snapshot of a declared model flag from the {@see AiModelFlagRegistry}.
 *
 * The $colorCode is either a UI theme token (one of {@see AiModelFlagRegistry}::COLOR_* constants),
 * or a raw CSS color value. Instances are created on demand by the registry.
 */
#[UsePolicy(AiModelFlagPolicy::class)]
readonly class ModelFlagDefinition
{
    public function __construct(
        public string  $key,
        public ?string $titleLabel,
        public ?string $descriptionLabel,
        public ?string $colorCode
    )
    {
    }
}
