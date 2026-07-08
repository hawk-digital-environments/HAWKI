<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Pricing\Values;


use App\Services\Ai\Models\Pricing\AiModelPricingInterface;

final class NullPricing implements AiModelPricingInterface
{
    /**
     * @inheritDoc
     */
    public function isUndefined(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isFree(): bool
    {
        return false;
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
