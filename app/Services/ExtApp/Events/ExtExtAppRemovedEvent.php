<?php
declare(strict_types=1);

namespace App\Services\ExtApp\Events;

/**
 * Fired when an external app integration is removed.
 *
 * Triggered by the ExtApp model's Eloquent `deleted` event via
 * {@see \App\Models\ExtApp::$dispatchesEvents}.
 * The {@see AbstractExtAppEvent::$app} property holds the integration that was removed.
 *
 * @see ExtExtAppCreatedEvent for when the integration is first registered
 */
readonly class ExtExtAppRemovedEvent extends AbstractExtAppEvent
{
}
