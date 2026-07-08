<?php
declare(strict_types=1);


namespace App\Services\Ai\Utils\Traits;


trait TranslatableRegistryTrait
{
    private array $titleTranslationLabels = [];
    private array $descriptionTranslationLabels = [];

    abstract public function has(string $key): bool;

    public function getTitleLabel(string $key): string|null
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("The key '$key' is not declared in " . static::class . " and therefore does not have a title.");
        }
        return $this->titleTranslationLabels[$key] ?? null;
    }

    public function getDescriptionLabel(string $key): string|null
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("The key '$key' is not declared in " . static::class . " and therefore does not have a title.");
        }
        return $this->descriptionTranslationLabels[$key] ?? null;
    }
}
