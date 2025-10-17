<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\OpenAi\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use Illuminate\Support\Facades\Log;

class OpenAiStreamingRequest extends AbstractRequest
{
    use OpenAiUsageTrait;

    public function __construct(
        private array $payload,
        private \Closure $onData
    ) {}

    public function execute(AiModel $model): void
    {
        $this->payload['stream'] = true;
//        $this->payload['stream_options'] = [
//            'include_usage' => true,
//        ];

        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }
    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        Log::debug($chunk);
        // Parse the event JSON
        $jsonChunk = json_decode($chunk, true);
        if (!$jsonChunk) {
            return $this->createErrorResponse('Invalid JSON chunk received.');
        }

        // If the chunk contains an error
        if (isset($jsonChunk['error'])) {
            return $this->createErrorResponse($jsonChunk['error']['message'] ?? 'Unknown error');
        }

        $type = $jsonChunk['type'] ?? '';
        $content = '';
        $isDone = false;
        $usage = null;

        switch ($type) {
            // The main streaming text deltas
            case 'response.output_text.delta':
                $content = $jsonChunk['delta'] ?? '';
                break;

            // When the full text part is completed
//            case 'response.output_text.done':
//            case 'response.content_part.done':
//                $content = $jsonChunk['text'] ?? ($jsonChunk['part']['text'] ?? '');
//                break;

            // When the whole response is done
            case 'response.completed':
                $isDone = true;
                if (!empty($jsonChunk['response']['usage'])) {
                    $usage = $this->extractUsage($model, $jsonChunk['response']);
                }
                break;

            // Optional: handle created/in_progress etc. for logging/debugging
            case 'response.created':
            case 'response.in_progress':
            case 'response.output_item.added':
            case 'response.output_item.done':
            case 'response.content_part.added':
                // No text content, just metadata events
                break;

            default:
                // Unknown or unsupported type — ignore or log it
                break;
        }

        return new AiResponse(
            content: ['text' => $content],
            usage: $usage,
            isDone: $isDone
        );
    }

}
