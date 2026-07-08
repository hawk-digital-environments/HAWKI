<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi;


use App\Services\Ai\Exceptions\InvalidAiManagerException;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Utils\DecoratorTrait;
use Laravel\Ai\AiManager;

class ExtendedAiManager extends AiManager
{
    use DecoratorTrait;

    private array|null $instanceConfig = null;

    /**
     * @inheritDoc
     */
    public function instance($name = null)
    {
        // If the portal already contains a resolved driver, simply reuse it
        // do not re-resolve it, so we keep existing configuration and state of the driver.
        if (is_string($name) && ProviderDriverPortal::isActiveTransferId($name)) {
            return ProviderDriverPortal::fromTransferId($name)->driver;
        }

        return parent::instance($name);
    }

    public function instanceWithConfig($name = null, array $config = [])
    {
        $instanceConfigBackup = $this->instanceConfig;
        try {
            $this->instanceConfig = $config;

            return $this->instance($name);
        } finally {
            $this->instanceConfig = $instanceConfigBackup;
        }
    }

    /**
     * @inheritDoc
     */
    public function getInstanceConfig($name): array
    {
        return $this->instanceConfig ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getDefaultInstance(): string
    {
        throw InvalidAiManagerException::forUnsupportedDefaultInstance();
    }
}
