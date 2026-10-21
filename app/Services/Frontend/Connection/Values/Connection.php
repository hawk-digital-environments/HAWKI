<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;


use App\Services\Translation\Value\Locale;

/**
 * Snapshot of the current connection context, sent to the frontend via the JSON:API
 * `/connections` resource.
 *
 * The `id` is either `"hawki"` for a native HAWKI session or the external app's user ID
 * for an external-app session. Optional fields are populated depending on `type`:
 * - `userinfo` — present when a user is authenticated (native or external-app)
 * - `extAppSecrets` — present only for `EXTERNAL_APP_AUTHENTICATED`; contains the
 *   per-user credentials the frontend needs to call the API on behalf of the ext-app user
 * - `extAppConnectRequest` — present only for `EXTERNAL_APP`; encrypted payload the
 *   frontend sends back to complete the account-linking flow
 * - `migrationsToApply` — present only for native authenticated sessions; tells the
 *   frontend how many frontend migrations are pending so it can run them on startup
 */
readonly class Connection
{
    public function __construct(
        /** Either `"hawki"` for a native session or the external app user ID. */
        public string             $id,
        public ConnectionType     $type,
        /** Current application version, used by the frontend for cache-busting. */
        public string             $version,
        public Locale             $locale,
        public Userinfo|null      $userinfo = null,
        public ExtAppSecrets|null $extAppSecrets = null,
        /** Encrypted connect-request payload used to link an ext-app user to a HAWKI account. */
        public string|null        $extAppConnectRequest = null,
        /** Number of pending frontend migrations for the authenticated user. */
        public int|null           $migrationsToApply = null
    )
    {
    }
}
