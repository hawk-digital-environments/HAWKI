<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Values;


use App\Policies\AiToolCapabilityPolicy;
use App\Services\Ai\Values\ModelCapabilityValueType;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[UsePolicy(AiToolCapabilityPolicy::class)]
readonly class AiToolCapabilityDefinition
{
    public function __construct(
        public string                   $key,
        public ModelCapabilityValueType $defaultValue,
        public ?string                  $titleLabel,
        public ?string                  $descriptionLabel,
        public ?string                  $iconPath
    )
    {
    }
}
