<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Drivers\OpenAi;


use App\Services\Ai\LaravelAi\Values\UrlMultiCitation;
use Generator;
use Illuminate\Support\Collection;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;

class ExtendedOpenAiGateway extends OpenAiGateway
{
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

    protected function processTextStream(string $invocationId, Provider $provider, string $model, array $tools, ?array $schema, ?TextGenerationOptions $options, $streamBody, array $requestBody = [], int $depth = 0, ?int $maxSteps = null, ?int $timeout = null): Generator
    {
        $response = parent::processTextStream($invocationId, $provider, $model, $tools, $schema, $options, $streamBody, $requestBody, $depth, $maxSteps, $timeout);
        $messageId = $this->generateEventId();
        foreach ($response as $event) {
            if ($event instanceof TextDelta || $event instanceof TextEnd) {
                $messageId = $event->messageId;
            }

            if ($event instanceof StreamEnd) {
                $citations = $this->extractCitations($this->lastData['response']['output'] ?? []);
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
     * Extract citations from the output array.
     */
    protected function extractCitations(array $output): Collection
    {
        /** @var Collection<string,UrlMultiCitation> $citations */
        $citations = new Collection;

        foreach ($output as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                foreach ($content['annotations'] ?? [] as $annotation) {
                    if (($annotation['type'] ?? '') === 'url_citation' && !empty($annotation['url'])) {
                        $citations->getOrPut(
                            $annotation['url'],
                            fn() => new UrlMultiCitation($annotation['url'], $annotation['title'] ?? null)
                        )->addRange(
                            isset($annotation['start_index']) ? (int)$annotation['start_index'] : null,
                            isset($annotation['end_index']) ? (int)$annotation['end_index'] : null
                        );
                    }
                }
            }
        }

        return $citations->values();
    }
}
