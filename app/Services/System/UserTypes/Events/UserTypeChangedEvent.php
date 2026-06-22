<?php
declare(strict_types=1);

namespace App\Services\System\UserTypes\Events;

use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched immediately after the active user type has been changed via {@see UserContext::set()}.
 *
 * Listeners receive the updated {@see UserContext} singleton, which can be queried to determine
 * which user type is now active:
 *
 * ```php
 * public function handle(UserTypeChangedEvent $event): void
 * {
 *     if ($event->context->isGuest()) {
 *         // No authenticated user
 *     } elseif ($event->context->isUser()) {
 *         // A fully authenticated HAWKI user
 *     } elseif ($event->context->isRegisteringUser()) {
 *         // A user partway through registration — not yet available via the guard
 *         $registering = $event->context->getRegisteringUser();
 *     } elseif ($event->context->isExternalApp()) {
 *         // An external application establishing a connection, before the real user is resolved
 *     }
 *
 *     // Or check for a custom user type by its string identifier:
 *     if ($event->context->is('my-custom-type')) {
 *         // ...
 *     }
 * }
 * ```
 *
 * Well-known user type identifiers are defined in {@see WellKnownUserTypes}.
 */
readonly class UserTypeChangedEvent
{
    use Dispatchable;

    public function __construct(
        /**
         * The user context singleton after the change has been applied.
         * Use {@see UserContext::isGuest()}, {@see UserContext::isUser()},
         * {@see UserContext::isRegisteringUser()}, {@see UserContext::isExternalApp()},
         * or {@see UserContext::is()} to inspect the new active user type.
         */
        public UserContext $context
    ) {
    }
}
