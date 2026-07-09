<?php
declare(strict_types=1);

namespace App\Services\ExtApp\Events;

/**
 * Fired when a new external app integration is registered.
 *
 * Triggered by the ExtApp model's Eloquent `created` event via
 * {@see \App\Models\ExtApp::$dispatchesEvents}.
 * The {@see AbstractExtAppEvent::$app} property holds the newly registered integration.
 *
 * @see ExtExtAppRemovedEvent for when the integration is removed
 */
readonly class ExtExtAppCreatedEvent extends AbstractExtAppEvent
{
}
