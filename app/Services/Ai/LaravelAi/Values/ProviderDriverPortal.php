<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Values;


use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\Provider as Driver;

// This class is a bit of a hack to get around the fact that Laravel AI doesn't provide
// a good way of forwarding our AiProvider/Adapter into the {@see ExtendedAiManager}.
// This is because the "provider" method of a laravel agent can only return a string.
// The {@see ExtendedAiManager} then needs to be able to resolve the actual configuration
// based on that string.
// Together with {@see ExtendedAiManager} it does a bit of black magic, to allow us to pass
// already resolved information into the manager, without having to re-resolve it from the database again.
class ProviderDriverPortal implements \Stringable
{
    /**
     * @var array<string, self>
     */
    private static array $transferList = [];

    public function __construct(
        public readonly string $transferId,
        public readonly Driver $driver
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->transferId;
    }

    public static function isActiveTransferId(string $transferId): bool
    {
        return array_key_exists($transferId, self::$transferList);
    }

    public static function fromTransferId(string $transferId): self
    {
        if (!array_key_exists($transferId, self::$transferList)) {
            // @todo exception
            throw new \InvalidArgumentException('Invalid transfer ID: ' . $transferId);
        }
        $self = self::$transferList[$transferId];
        // This is a one-time transfer, so we remove it from the list after it's been used.
        unset(self::$transferList[$transferId]);
        return $self;
    }

    public static function fromProviderProxy(AiProviderProxy $provider): self
    {
        $transferId = 'provider-adapter-portal:transfer:' . $provider->provider_id;
        $self = new self(
            transferId: $transferId,
            driver: $provider->driver
        );
        self::$transferList[$transferId] = $self;
        return $self;
    }
}
