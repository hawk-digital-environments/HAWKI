<?php
declare(strict_types=1);

namespace App\Services\Config;

use Illuminate\Config\Repository;

/** Overlays resolved deployment configuration without changing env() or the config cache. */
class EnvironmentConfigProxy
{
    private array $defaults = [];

    public function __construct(private readonly Repository $config) {}

    public function capture(array $paths): void
    {
        foreach ($paths as $path) {
            if (!array_key_exists($path, $this->defaults)) {
                $this->defaults[$path] = $this->config->get($path);
            }
        }
    }

    public function default(string $path): mixed
    {
        if (!array_key_exists($path, $this->defaults)) {
            throw new \InvalidArgumentException('Unregistered configuration path: ' . $path);
        }

        return $this->defaults[$path];
    }

    /** Replace the complete overlay, restoring defaults for removed overrides. */
    public function apply(array $overrides): void
    {
        foreach (array_keys($overrides) as $path) {
            $this->default($path);
        }
        $this->config->set(array_replace($this->defaults, $overrides));
    }
}
