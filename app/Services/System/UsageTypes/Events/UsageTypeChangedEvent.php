<?php
declare(strict_types=1);

namespace App\Services\System\UsageTypes\Events;

use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\System\UsageTypes\UsageContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched immediately after the active usage type has been changed via {@see UsageContext::set()}.
 *
 * Listeners receive the updated {@see UsageContext} singleton, which can be queried to determine
 * which usage type is now active:
 *
 * ```php
 * public function handle(UsageTypeChangedEvent $event): void
 * {
 *     if ($event->context->isMainApp()) {
 *         // Running in the main HAWKI interface
 *     } elseif ($event->context->isExternalApp()) {
 *         // Running in an external client integration
 *     }
 *
 *     // Or check for a custom usage type by its string identifier:
 *     if ($event->context->is('my-custom-type')) {
 *         // ...
 *     }
 * }
 * ```
 *
 * Well-known usage type identifiers are defined in {@see WellKnownUsageTypes}.
 */
readonly class UsageTypeChangedEvent
{
    use Dispatchable;

    public function __construct(
        /**
         * The usage context singleton after the change has been applied.
         * Use {@see UsageContext::isMainApp()}, {@see UsageContext::isExternalApp()},
         * or {@see UsageContext::is()} to inspect the new active usage type.
         */
        public UsageContext $context
    )
    {
    }
}
