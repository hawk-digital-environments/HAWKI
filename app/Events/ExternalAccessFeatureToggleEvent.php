<?php
declare(strict_types=1);


namespace App\Events;


use App\Http\Middleware\ExternalAccessMiddleware;
use App\Services\ExtApp\ExtAppContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted when a request by an external app has been allowed by {@see ExternalAccessMiddleware}.
 * This is primarily used to update the {@see ExtAppContext}, but can also be used for logging or other purposes.
 *
 * Info: This MAY trigger multiple times in a single request, if the {@see ExternalAccessMiddleware} is used multiple times.
 */
readonly class ExternalAccessFeatureToggleEvent
{
    use Dispatchable;
    
    public function __construct(
        /**
         * The list of allowed features.
         * This corresponds to the feature names used in the {@see ExternalAccessMiddleware} and the configuration at "external_access.*".
         */
        public array $features
    )
    {
    }
}
