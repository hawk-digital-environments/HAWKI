<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Drivers\GeminiExtended;


use App\Services\Ai\LaravelAi\Values\UrlMultiCitation;
use Generator;
use Illuminate\Support\Collection;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;

class ExtendedGeminiGateway extends GeminiGateway
{
    /* =======================================================================
     * Override the request body building to hoist the 'generationConfig' and
     * 'safetySettings' from the inner 'generationConfig' if they exist.
     * ======================================================================= */

    /**
     * @inheritDoc
     */
    protected function buildTextRequestBody(
        Provider               $provider,
        ?string                $instructions,
        array                  $messages,
        array                  $tools,
        ?array                 $schema,
        ?TextGenerationOptions $options
    ): array
    {
        $out = parent::buildTextRequestBody($provider, $instructions, $messages, $tools, $schema, $options);
        return [
            $this->postProcessRequestBody($out[0]),
            $out[1]
        ];
    }

    /**
     * @inheritDoc
     */
    protected function rebuildContinuationBody(array $contents, ?string $instructions, array $tools, ?array $schema, ?TextGenerationOptions $options, Provider $provider): array
    {
        $out = parent::rebuildContinuationBody($contents, $instructions, $tools, $schema, $options, $provider);
        return $this->postProcessRequestBody($out);
    }

    private function postProcessRequestBody(array $body): array
    {
        // If the provider itself set a 'generationConfig', the current implementation will not merge it correctly.
        if (isset($body['generationConfig']['generationConfig'])) {
            $innerConfig = $body['generationConfig']['generationConfig'];
            unset($body['generationConfig']['generationConfig']);
            $body['generationConfig'] = array_merge($body['generationConfig'], $innerConfig);
        }

        // Also hoist the 'safetySettings' if it exists in the inner 'generationConfig'
        if (isset($body['generationConfig']['safetySettings'])) {
            $safetySettings = $body['generationConfig']['safetySettings'];
            unset($body['generationConfig']['safetySettings']);
            $body['safetySettings'] = $safetySettings;
        }

        return $body;
    }

    /* =======================================================================
     * Override the text streaming to extract citations from the response.
     * ======================================================================= */

    private array $lastData = [];

    /**
     * @inheritDoc
     */
    protected function parseServerSentEvents($streamBody): Generator
    {
        foreach (parent::parseServerSentEvents($streamBody) as $data) {
            $this->lastData = $data;
            yield $data;
        }
    }

    /**
     * @inheritDoc
     */
    protected function processTextStream(string $invocationId, Provider $provider, string $model, array $tools, ?array $schema, ?TextGenerationOptions $options, $streamBody, array $contents = [], ?string $instructions = null, int $depth = 0, ?int $maxSteps = null, ?int $timeout = null): Generator
    {
        $response = parent::processTextStream($invocationId, $provider, $model, $tools, $schema, $options, $streamBody, $contents, $instructions, $depth, $maxSteps, $timeout);
        $messageId = $this->generateEventId();
        foreach ($response as $event) {
            if ($event instanceof TextDelta || $event instanceof TextEnd) {
                $messageId = $event->messageId;
            }

            if ($event instanceof StreamEnd) {
                $citations = $this->extractCitations($this->lastData);
                if ($citations->isNotEmpty()) {
                    foreach ($citations as $citation) {
                        yield new Citation(
                            $this->generateEventId(),
                            $messageId,
                            $citation,
                            time()
                        );
                    }
                }
            }

            yield $event;
        }
    }

    /**
     * Extract citations from the response data.
     */
    protected function extractCitations(array $data): Collection
    {
        /** @var Collection<string, UrlMultiCitation> $citations */
        $citations = new Collection;

        $candidate = $data['candidates'][0] ?? [];

        // Legacy citation metadata format...
        $sources = $candidate['citationMetadata']['citationSources'] ?? [];

        foreach ($sources as $source) {
            if (isset($source['uri'])) {
                $citations->push(new UrlMultiCitation(
                    $source['uri'],
                    $source['title'] ?? null,
                ));
            }
        }

        // Grounding metadata format (Google Search grounding)...
        $groundingSupports = $candidate['groundingMetadata']['groundingSupports'] ?? [];
        $groundingChunks = $candidate['groundingMetadata']['groundingChunks'] ?? [];

        foreach ($groundingSupports as $support) {
            foreach ($support['groundingChunkIndices'] ?? [] as $index) {
                $web = $groundingChunks[$index]['web'] ?? [];
                $citations->getOrPut(
                    $web['uri'],
                    fn() => new UrlMultiCitation($web['uri'], $web['title'] ?? null, isByteOffset: true)
                )->addRange($support['segment']['startIndex'] ?? null, $support['segment']['endIndex'] ?? null);
            }
        }

        return $citations->unique('url')->values();
    }
}
