<?php
declare(strict_types=1);


namespace App\Services\Config;

use App\Services\System\Container\ServiceLocator;
use Illuminate\Config\Repository;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class ConfigService
{
    /** @var array<class-string<AbstractConfig>, AbstractConfig> */
    private array $map = [];

    public function __construct(
        private readonly ServiceLocator $serviceLocator,
        private readonly Repository     $repo
    )
    {
    }

    /**
     * Load a config object. Returns the same instance on repeated calls.
     *
     * @template T of AbstractConfig
     * @param class-string<T> $configClass
     * @return T
     */
    public function get(string $configClass): AbstractConfig
    {
        if (isset($this->map[$configClass])) {
            return $this->map[$configClass];
        }

        if (!is_subclass_of($configClass, AbstractConfig::class)) {
            throw new \InvalidArgumentException("Invalid config class: " . $configClass);
        }

        if (!method_exists($configClass, 'make')) {
            throw new \InvalidArgumentException("Config class must have a static make method: " . $configClass);
        }

        return $this->map[$configClass] = $this->serviceLocator->call(
            ['configService.make', $configClass],
            $configClass . '::make',
            ['repo' => $this->repo]
        );
    }
}
