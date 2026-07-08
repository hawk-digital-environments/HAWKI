<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Events;

use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when {@see \App\Services\Ai\ModelInformation\ModelInfoFetcher::fetchSingle()} begins,
 * before the provider model list is fetched.
 *
 * Listeners receive the target model ID and provider so they can log or trace single-model
 * fetch operations before any HTTP calls are made.
 */
readonly class SingleModelInfoFetchStartingEvent
{
    use Dispatchable;

    public function __construct(
        /** The provider whose catalogue will be searched for the model. */
        public AiProviderProxy $provider,
        /** The model identifier being looked up (e.g. "gpt-4o"). */
        public string          $modelId,
        /** Structured metrics collector for this fetch run. */
        public JobMetrics      $metrics,
    )
    {
    }
}
