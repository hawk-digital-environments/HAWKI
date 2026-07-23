<?php
declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;

class InvalidModelEnrichmentTypeException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forInvalidLimitsType(string $modelId, string $actualType): self
    {
        return new self(sprintf(
            'Model "%s" limits must be an instance of %s, got %s. '
            . 'Ensure the model type is set to "chat" before calling enrichChatLimits().',
            $modelId,
            ChatAiModelLimits::class,
            $actualType,
        ));
    }

    public static function forInvalidPricingType(string $modelId, string $actualType): self
    {
        return new self(sprintf(
            'Model "%s" pricing must be an instance of %s, got %s. '
            . 'Ensure the model type is set to "chat" before calling enrichChatPricing().',
            $modelId,
            ChatAiModelPricing::class,
            $actualType,
        ));
    }
}
