<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


use App\Services\AI\Value\AiModelMap;

abstract class AbstractMissingConfiguredModelsException extends \RuntimeException implements AiServiceExceptionInterface
{
    private array $missingModelIds;
    private array $missingTypes;

    final public function __construct(
        array $missingModelIds,
        array $missingTypes
    )
    {
        $this->missingModelIds = $missingModelIds;
        $this->missingTypes = $missingTypes;

        $message = 'The following ' . $this->getListType() . ' AI model IDs are missing: ' . implode(', ', $missingModelIds) . '.';
        if (!empty($missingTypes)) {
            $message .= ' Missing types: ' . implode(', ', $missingTypes) . '.';
        }
        parent::__construct($message);
    }

    abstract protected function getListType(): string;

    public function getMissingModelIds(): array
    {
        return $this->missingModelIds;
    }

    public function getMissingTypes(): array
    {
        return $this->missingTypes;
    }

    /**
     * @param array $knownModelIds
     * @param AiModelMap $registeredModels
     * @return static
     */
    public static function createForMissing(
        array      $knownModelIds,
        AiModelMap $registeredModels,
    ): static
    {
        $missingDefaultModelIds = array_diff($knownModelIds, $registeredModels->toIdArray());
        $missingDefaultModelTypes = array_keys(
            array_filter(
                $knownModelIds, static fn($id) => in_array($id, $missingDefaultModelIds, true)
            )
        );

        return new static(
            missingModelIds: array_unique($missingDefaultModelIds),
            missingTypes: $missingDefaultModelTypes
        );
    }
}
