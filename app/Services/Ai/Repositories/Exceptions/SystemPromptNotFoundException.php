<?php
declare(strict_types=1);

namespace App\Services\Ai\Repositories\Exceptions;

use App\Services\Ai\Values\SystemPromptType;

class SystemPromptNotFoundException extends \RuntimeException implements SystemPromptExceptionInterface
{
    public static function forTypeAndLocale(
        SystemPromptType   $type,
        string|\Stringable $locale,
        ?string            $usageType = null
    ): self
    {
        return new self(sprintf(
            'No system prompt found for type "%s" and locale "%s"%s.',
            $type->value,
            $locale,
            $usageType ? sprintf(' and usage type "%s"', $usageType) : ''
        ));
    }
}
