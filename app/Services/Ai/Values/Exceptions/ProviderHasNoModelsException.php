<?php
declare(strict_types=1);

namespace App\Services\Ai\Values\Exceptions;

use App\Models\Ai\AiProvider;

class ProviderHasNoModelsException extends \InvalidArgumentException implements ParameterSourceExceptionInterface
{
    public static function forProvider(AiProvider $provider): self
    {
        return new self(sprintf(
            'Provider "%s" has no models, cannot create ParameterSource.',
            $provider->name
        ));
    }
}
