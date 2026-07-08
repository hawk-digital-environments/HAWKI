<?php

namespace App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible;

use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\BuildsTextRequests;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\CreatesOpenAiCompatibleClient;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\HandlesTextStreaming;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\MapsAttachments;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\MapsChatCompletionMessages;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\MapsChatCompletionTools;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\ParsesTextResponses;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Concerns\PerformsChatCompletionSteps;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Foreign\DelegatesToTextGenerationLoop;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Foreign\StepTextGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Gateway\Concerns\HandlesFailoverErrors;
use Laravel\Ai\Gateway\Concerns\ParsesServerSentEvents;
use Laravel\Ai\Providers\Provider;

class OpenAiCompatibleGateway implements StepTextGateway, TextGateway
{
    use BuildsTextRequests;
    use CreatesOpenAiCompatibleClient;
    use HandlesTextStreaming;
    use MapsAttachments;
    use MapsChatCompletionMessages;
    use MapsChatCompletionTools;
    use ParsesTextResponses;
    use PerformsChatCompletionSteps;
    use DelegatesToTextGenerationLoop;
    use HandlesFailoverErrors;
    use ParsesServerSentEvents;

    public function __construct(protected Dispatcher $events)
    {
        //
    }

    /**
     * Get the stream options sent with a streaming Chat Completions request.
     */
    protected function streamOptions(Provider $provider): ?array
    {
        return $provider->additionalConfiguration()['stream_options'] ?? null;
    }
}
