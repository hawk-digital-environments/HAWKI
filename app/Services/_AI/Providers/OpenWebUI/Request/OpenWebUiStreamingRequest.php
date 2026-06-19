<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenWebUI\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class OpenWebUiStreamingRequest extends AbstractRequest
{
    use OpenWebUiRequestTrait;
    
    public function __construct(
        private array             $payload,
        private readonly \Closure $onData
    )
    {
    }
    
    public function execute(AiModel $model): void
    {
        $this->payload['stream'] = true;
        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }
    
    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $jsonChunk = json_decode($chunk, true);
        
        $content = '';
        $isDone = false;
        $usage = null;
        
        // Check for the finish_reason flag
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }
        
        // Extract usage data if available
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($model, $jsonChunk);
        }
        
        // Extract content if available
        if ($this->containsKey($jsonChunk, 'content')) {
            $content = $this->getValueForKey($jsonChunk, 'content');
        }
        
        return new AiResponse(
            content: [
                'text' => $content,
            ],
            usage: $usage,
            isDone: $isDone
        );
    }
}
