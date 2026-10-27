<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\StallAware;


use App\Services\Ai\Providers\StallAware\Concerns\AppliesStallDetection;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;

/**
 * {@see OpenAiCompatibleGateway} with curl stall detection on its requests.
 *
 * The only changes to the parent gateway are {@see client()} and {@see generateStreamStep()}:
 * the built HTTP client additionally receives the provider's stall timeout via
 * {@see AppliesStallDetection}, and streaming steps cap their header wait by it. This mirrors
 * the way stall detection support is intended to land in the upstream `laravel/ai` package —
 * once it is supported natively, this class and its registration in
 * {@see \App\Providers\AiServiceProvider} can be removed and the remaining driver families
 * (anthropic, openai, gemini, …) gain the same behaviour for free.
 */
class StallAwareOpenAiCompatibleGateway extends OpenAiCompatibleGateway
{
    use AppliesStallDetection;

    /**
     * @inheritDoc
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return $this->withStallDetection(parent::client($provider, $timeout), $provider);
    }

    /**
     * @inheritDoc
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator {
        return parent::generateStreamStep(
            $invocationId,
            $provider,
            $model,
            $instructions,
            $messages,
            $tools,
            $schema,
            $options,
            $this->streamStepTimeout($provider, $timeout),
            $stepContext,
        );
    }
}
