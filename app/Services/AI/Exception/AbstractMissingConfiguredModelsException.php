<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


use App\Services\AI\Value\AiModelMap;

abstract class AbstractMissingConfiguredModelsException extends \RuntimeException implements AiServiceExceptionInterface
{
    private array $missingModelIds;
    private array $missingTypes;
    
    abstract protected static function getListType(): string;

    public function getMissingModelIds(): array
    {
        return $this->missingModelIds;
    }

    public function getMissingTypes(): array
    {
        return $this->missingTypes;
    }

    public static function createForMissing(
        array      $knownModelIds,
        AiModelMap $registeredModels,
    ): static
    {
        $missingModelIds = array_diff($knownModelIds, $registeredModels->toIdArray());
        $missingModelTypes = array_keys(
            array_filter(
                $knownModelIds, static fn($id) => in_array($id, $missingModelIds, true)
            )
        );

        $message = 'The following ' . static::getListType() . ' AI model IDs are missing: ' . implode(', ', $missingModelIds) . '.';
        if (!empty($missingModelTypes)) {
            $message .= ' Missing types: ' . implode(', ', $missingModelTypes) . '.';
        }

        $i = new static($message);
        $i->missingModelIds = $missingModelIds;
        $i->missingTypes = $missingModelTypes;
        return $i;
    }
}
