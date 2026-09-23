<?php
declare(strict_types=1);

namespace App\Services\Auth\Value;

/**
 * Where a successful authentication has to send the visitor next.
 *
 * Deliberately a step and not a URL: the legacy Blade gateway and the Svelte SPA host the same
 * two steps at different paths, and the JSON:API login action reports the step verbatim
 * (`meta.next`) so the frontend can route it itself.
 */
enum LoginNextStep: string
{
    /**
     * A local user record exists. The client continues with the handshake, where the keychain
     * is unlocked with the user's passkey.
     */
    case HANDSHAKE = 'handshake';

    /**
     * The visitor authenticated successfully but has no local user record yet. The client runs
     * the registration ceremony, which generates their keypair and creates the account.
     */
    case REGISTER = 'register';
}
