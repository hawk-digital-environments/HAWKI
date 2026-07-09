<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Utils\Traits\TranslatableRegistryTraitTestFixtures;

use App\Services\Ai\Utils\Traits\TranslatableRegistryTrait;

/**
 * Minimal concrete class that uses TranslatableRegistryTrait for testing purposes.
 */
final class ConcreteRegistry
{
    use TranslatableRegistryTrait;

    private array $keys = [];

    public function declare(
        string  $key,
        ?string $titleLabel = null,
        ?string $descriptionLabel = null
    ): self {
        $this->keys[$key] = true;
        $this->titleTranslationLabels[$key] = $titleLabel;
        $this->descriptionTranslationLabels[$key] = $descriptionLabel;
        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->keys[$key]);
    }
}
