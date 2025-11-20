<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Listeners;


use App\Events\ExternalAccessFeatureToggleEvent;
use App\Services\ExtApp\ExtAppContext;

readonly class ExtAppContextUpdateListener
{
    public function __construct(
        private ExtAppContext $context
    )
    {
    }
    
    public function handle(ExternalAccessFeatureToggleEvent $_): void
    {
        // Currently we only check if the event was fired, no need to check any properties
        // -> A fired event means an external app is in use
        $this->context->markAsExternal();
    }
}
