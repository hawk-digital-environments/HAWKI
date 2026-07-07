<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Limits\Values;


use App\Services\Ai\Models\Limits\AiModelLimitsInterface;

final class NullAiModelLimits implements AiModelLimitsInterface
{
    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
