<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Ollama\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class OllamaStreamingRequest extends AbstractRequest
{
    use OllamaUsageTrait;
    
    public function __construct(
        private array    $payload,
        private \Closure $onData
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
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);
        
        $content = '';
        $isDone = false;
        $usage = null;
        
        // Extract content based on Ollama's streaming format
        if (isset($jsonChunk['message']['content'])) {
            $content = $jsonChunk['message']['content'];
        }
        
        // Check if this is the final chunk
        if (isset($jsonChunk['done']) && $jsonChunk['done'] === true) {
            $isDone = true;
            
            // Extract usage if available in the final chunk
            if (isset($jsonChunk['eval_count'], $jsonChunk['prompt_eval_count'])) {
                $usage = $this->extractUsage($model, $jsonChunk);
            }
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
