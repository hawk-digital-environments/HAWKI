<?php
declare(strict_types=1);

namespace App\Services\Users\Keychain\Value;

/**
 * Whether an authenticated user's server-side keychain is usable, and if not, why not.
 *
 * The keychain is end-to-end encrypted: the server stores opaque blobs it cannot read, and only
 * the browser can repair them. So this enum is a *diagnosis* handed to the frontend, which then
 * runs the matching ceremony (unlock, migrate, set up) — the backend never repairs or cleans up
 * anything on its own.
 *
 * @see \App\Services\Users\Keychain\KeychainStateResolver  Where each case is decided.
 */
enum KeychainState: string
{
    /**
     * The keychain is complete: the core entries are present and the user's public key is on
     * the user record. The frontend can go straight to unlocking it with the passkey.
     */
    case INITIALIZED = 'initialized';

    /**
     * The user still holds their keychain in the pre-passkey-upgrade format. The core entries
     * are absent and the blob migration is still pending for them, so the frontend has to run
     * that migration before anything else.
     */
    case LEGACY_MIGRATION_REQUIRED = 'legacy_migration_required';

    /**
     * The user has no keychain at all — a fresh account. The frontend runs the initial key
     * generation ceremony.
     */
    case SETUP_REQUIRED = 'setup_required';

    /**
     * Some, but not all, of the expected pieces exist. Nothing is deleted or regenerated on the
     * strength of this: the remaining blobs may be the user's only copy of their data, so the
     * frontend must offer a deliberate recovery or reset instead of retrying silently.
     */
    case INCONSISTENT = 'inconsistent';
}
