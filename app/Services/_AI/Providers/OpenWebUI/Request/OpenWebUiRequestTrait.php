<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenWebUI\Request;


use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait OpenWebUiRequestTrait
{
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usage'])) {
            return null;
        }
        
        return new TokenUsage(
            model: $model,
            promptTokens: (int)$data['usage']['prompt_tokens'],
            completionTokens: (int)$data['usage']['completion_tokens'],
        );
    }
    
    private function containsKey($obj, $targetKey)
    {
        if (!is_array($obj)) {
            return false;
        }
        if (array_key_exists($targetKey, $obj)) {
            return true;
        }
        foreach ($obj as $value) {
            if ($this->containsKey($value, $targetKey)) {
                return true;
            }
        }
        return false;
    }
    
    private function getValueForKey($obj, $targetKey)
    {
        if (!is_array($obj)) {
            return null;
        }
        if (array_key_exists($targetKey, $obj)) {
            return $obj[$targetKey];
        }
        foreach ($obj as $value) {
            $result = $this->getValueForKey($value, $targetKey);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }
}
