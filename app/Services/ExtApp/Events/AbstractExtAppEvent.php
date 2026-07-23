<?php
declare(strict_types=1);

namespace App\Services\ExtApp\Events;

use App\Models\ExtApp;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all external-app lifecycle events.
 *
 * An {@see ExtApp} represents a third-party application that has been integrated
 * with HAWKI. These events fire when such an integration is created or removed.
 *
 * @see ExtExtAppCreatedEvent for when a new external app integration is registered
 * @see ExtExtAppRemovedEvent for when an external app integration is removed
 */
abstract readonly class AbstractExtAppEvent
{
    use Dispatchable;

    public function __construct(
        /** The external app that was created or removed. */
        public ExtApp $app
    ) {}
}
