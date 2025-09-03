<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Google\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class GoogleStreamingRequest extends AbstractRequest
{
    use GoogleRequestTrait;
    
    public function __construct(
        private readonly array    $payload,
        private readonly \Closure $onData
    )
    {
    }
    
    public function execute(AiModel $model): void
    {
        $this->executeStreamingRequest(
            model: $model,
            payload: $this->preparePayload($this->payload),
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse'],
            getHttpHeaders: fn() => [
                'Content-Type: application/json'
            ],
            apiUrl: $this->buildApiUrl($model)
        );
    }
    
    public function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);
        $content = '';
        $groundingMetadata = '';
        $isDone = false;
        
        // Extract content if available
        if (isset($jsonChunk['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $jsonChunk['candidates'][0]['content']['parts'][0]['text'];
        }
        
        // Add search results
        if (isset($jsonChunk['candidates'][0]['groundingMetadata'])) {
            $groundingMetadata = $jsonChunk['candidates'][0]['groundingMetadata'];
        }
        
        // Check for completion
        if (isset($jsonChunk['candidates'][0]['finishReason']) &&
            $jsonChunk['candidates'][0]['finishReason'] !== 'FINISH_REASON_UNSPECIFIED') {
            $isDone = true;
        }
        
        return new AiResponse(
            content: [
                'text' => $content,
                'groundingMetadata' => $groundingMetadata,
            ],
            usage: $this->extractUsage($model, $jsonChunk),
            isDone: $isDone,
        );
    }
}
