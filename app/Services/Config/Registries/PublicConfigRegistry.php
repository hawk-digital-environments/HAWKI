<?php
declare(strict_types=1);


namespace App\Services\Config\Registries;

use App\Services\Config\Contracts\PublicConfigInterface;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Singleton;
use Traversable;

/**
 * @implements \IteratorAggregate<PublicConfigInterface>
 */
#[Singleton]
class PublicConfigRegistry implements \IteratorAggregate
{
    /**
     * @var array<class-string<PublicConfigInterface>, class-string<PublicConfigInterface>>
     */
    private array $publicConfigClasses = [];

    public function __construct(
        /**
         * @var LazySingletonList<class-string<PublicConfigInterface>, PublicConfigInterface>
         */
        private readonly LazySingletonList $publicConfigs
    )
    {

    }

    public function declare(string $configClass): self
    {
        if (!is_a($configClass, PublicConfigInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Public config class %s must implement %s',
                $configClass,
                PublicConfigInterface::class));
        }

        $this->publicConfigClasses[$configClass] = $configClass;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        foreach ($this->publicConfigClasses as $configClass) {
            yield $this->publicConfigs->get($configClass);
        }
    }
}
