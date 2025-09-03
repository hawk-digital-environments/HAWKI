<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class GwdgStreamingRequest extends AbstractRequest
{
    use GwdgUsageTrait;
    
    public function __construct(
        private array    $payload,
        private \Closure $onData
    )
    {
    }
    
    public function execute(AiModel $model): void
    {
        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }
    
    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);
        $content = '';
        $isDone = false;
        $usage = null;
        
        // Check for the finish_reason flag
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }
        
        // Extract usage data if available
        // Mistral Fix: Additional check for empty choices array
        if (!empty($jsonChunk['usage']) && empty($jsonChunk['choices'])) {
            $usage = $this->extractUsage($model, $jsonChunk);
        }
        
        // Extract content if available
        if (isset($jsonChunk['choices'][0]['delta']['content'])) {
            $content = $jsonChunk['choices'][0]['delta']['content'];
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
