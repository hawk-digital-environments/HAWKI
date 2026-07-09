<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Drivers\GeminiExtended;


use App\Services\Ai\LaravelAi\Values\UrlMultiCitation;
use Generator;
use Illuminate\Support\Collection;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;

/**
 * Extends the Laravel AI Gemini gateway with two HAWKI-specific behaviours:
 *
 * **1. Request body post-processing** — the upstream `GeminiGateway` serialises
 * provider-supplied `generationConfig` into a nested `generationConfig.generationConfig`
 * key instead of flattening it into the top-level object. This gateway hoists that
 * inner config after the parent builds the body, and also promotes any
 * `safetySettings` key from inside `generationConfig` up to the root level where
 * the Gemini API actually expects it.
 *
 * **2. Citation extraction** — the Gemini streaming API returns grounding and
 * citation metadata in the final SSE frame. This gateway records the last raw SSE
 * data frame while streaming, then emits {@see Citation} events immediately before
 * the terminal {@see StreamEnd} event. Both the legacy `citationMetadata` format
 * and the newer Google Search grounding metadata format are supported via
 * {@see extractCitations()}.
 */
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
     * Flatten any nested `generationConfig.generationConfig` structure produced by
     * the upstream gateway when a provider supplies its own `generationConfig` block,
     * and promote `safetySettings` from inside `generationConfig` to the request root
     * where the Gemini API actually reads it.
     */
    private function postProcessRequestBody(array $body): array
    {
        if (isset($body['generationConfig']['generationConfig'])) {
            $innerConfig = $body['generationConfig']['generationConfig'];
            unset($body['generationConfig']['generationConfig']);
            $body['generationConfig'] = array_merge($body['generationConfig'], $innerConfig);
        }

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

    /**
     * The last raw SSE data frame received during streaming.
     *
     * Gemini sends grounding/citation metadata in its final frame, so tracking
     * the last frame allows {@see processTextStream()} to extract citations once
     * the stream finishes.
     */
    private array $lastData = [];

    /**
     * @inheritDoc
     *
     * Intercepts each parsed SSE data frame to keep {@see $lastData} up to date.
     */
    protected function parseServerSentEvents($streamBody): Generator
    {
        foreach (parent::parseServerSentEvents($streamBody) as $data) {
            $this->lastData = $data;
            yield $data;
        }
    }


    /**
     * Yields all events from the parent stream, then injects {@see Citation} events
     * derived from the final SSE frame immediately before the {@see StreamEnd} event,
     * so that downstream consumers receive citations as part of the same stream.
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    protected function processTextStream(string $invocationId, Provider $provider, string $model, $streamBody): Generator
    {
        $response = parent::processTextStream($invocationId, $provider, $model, $streamBody);
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

        return $response->getReturn();
    }

    /**
     * Extract citations from a raw Gemini SSE data frame.
     *
     * Handles two Gemini citation formats:
     * - **Legacy `citationMetadata`**: simple list of source URIs attached to a candidate.
     * - **Grounding metadata** (Google Search): `groundingSupports` entries that map
     *   segment byte ranges to `groundingChunks`, which each carry a web URI. Multiple
     *   supports may reference the same chunk, so ranges for the same URL are merged
     *   into a single {@see UrlMultiCitation} via {@see UrlMultiCitation::addRange()}.
     *
     * @return Collection<int, UrlMultiCitation>
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
