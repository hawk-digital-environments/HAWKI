<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;


use App\Utils\Casts\AbstractCastableObject;

/**
 * Encrypted payload that initiates the account-linking flow for an external app user
 * who has not yet been connected to a HAWKI account.
 *
 * The `ConnectionFactory` encrypts this via `ExtAppConnectRequestCrypto` and places the
 * result in `Connection::$extAppConnectRequest`. The frontend sends it back to the
 * connect endpoint, where it is decrypted and validated to authorise the link.
 */
class ExtAppConnectRequestPayload extends AbstractCastableObject
{
    /** Database ID of the external application requesting the connection. */
    public readonly int $appId;

    /** Current HAWKI application version, used by the receiving endpoint for compatibility checks. */
    public readonly string $version;

    /** The user identifier used by the external application (not a HAWKI user ID). */
    public readonly string $extAppUserId;
}
