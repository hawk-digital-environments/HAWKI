<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenWebUI\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class OpenWebUiNonStreamingRequest extends AbstractRequest
{
    use OpenWebUiRequestTrait;
    
    public function __construct(
        private array $payload
    )
    {
    }
    
    public function execute(AiModel $model): AiResponse
    {
        $this->payload['stream'] = false;
        return $this->executeNonStreamingRequest(
            model: $model,
            payload: $this->payload,
            dataToResponse: function (array $data) use ($model) {
                $content = '';
                
                if ($this->containsKey($data, 'content')) {
                    $content = $this->getValueForKey($data, 'content');
                }
                
                return new AiResponse(
                    content: [
                        'text' => $content
                    ],
                    usage: $this->extractUsage($model, $data)
                );
            }
        );
    }
}
