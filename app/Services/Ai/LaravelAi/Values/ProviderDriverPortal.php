<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Values;


use App\Services\Ai\Exceptions\InvalidTransferIdException;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * One-time transfer vehicle that lets an already-resolved {@see Driver} instance
 * cross the boundary between HAWKI's provider abstraction layer and the Laravel AI
 * {@see \App\Services\Ai\LaravelAi\ExtendedAiManager}.
 *
 * **Why this exists**: Laravel AI agents can only identify their provider by a plain
 * string name. Normally the manager would re-resolve that string from config/database,
 * which would duplicate the work already done when building the HAWKI
 * {@see AiProviderProxy}. This class bridges the gap: it registers a short-lived
 * string token (the "transfer ID") mapped to the pre-built driver, casts itself to
 * that token via {@see __toString()}, and lets {@see ExtendedAiManager::instance()}
 * retrieve the driver by token instead of going through normal resolution.
 *
 * **One-shot semantics**: each entry is deleted from the static registry the first
 * time it is consumed via {@see fromTransferId()}, preventing accidental reuse and
 * keeping the registry small.
 *
 * Typical flow:
 * ```php
 * // Inside AbstractLaravelAgent::send()
 * $portal = ProviderDriverPortal::fromProviderProxy($context->provider);
 * $this->prompt(provider: (string)$portal, ...);
 *
 * // Inside ExtendedAiManager::instance()
 * if (ProviderDriverPortal::isActiveTransferId($name)) {
 *     return ProviderDriverPortal::fromTransferId($name)->driver; // consumes entry
 * }
 * ```
 */
class ProviderDriverPortal implements \Stringable
{
    /**
     * Active transfer entries keyed by their transfer ID.
     *
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
     * Returns the transfer ID so the portal can be passed as a plain string
     * wherever Laravel AI expects a provider name.
     */
    public function __toString(): string
    {
        return $this->transferId;
    }

    /**
     * Check whether a transfer entry for the given ID is still pending.
     *
     * Returns false once the entry has been consumed by {@see fromTransferId()}.
     */
    public static function isActiveTransferId(string $transferId): bool
    {
        return array_key_exists($transferId, self::$transferList);
    }

    /**
     * Retrieve and consume the portal registered under the given transfer ID.
     *
     * The entry is removed from the registry upon retrieval (one-shot semantics).
     *
     * @throws \App\Services\Ai\Exceptions\InvalidTransferIdException when the ID is
     *         unknown or has already been consumed.
     */
    public static function fromTransferId(string $transferId): self
    {
        if (!array_key_exists($transferId, self::$transferList)) {
            throw InvalidTransferIdException::forUnknownTransferId($transferId);
        }
        $self = self::$transferList[$transferId];
        unset(self::$transferList[$transferId]);
        return $self;
    }

    /**
     * Register a new portal for the driver carried by an {@see AiProviderProxy} and
     * return the portal instance.
     *
     * The portal's string representation can then be passed to Laravel AI as the
     * provider name and will be intercepted by {@see ExtendedAiManager::instance()}.
     */
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
