<?php

namespace App\Services\AI;
use Illuminate\Support\Facades\Log;

use App\Services\AI\ModelUtilityService;


class AiResponseFormatterService
{
    public function __construct(ModelUtilityService $utilities){
        $this->utilities = $utilities;
    }

    public function formatDefaultChunk($chunk){

        $jsonChunk = json_decode($chunk, true);
        $usage = NULL;
        $isDone = false;
        // Check for the finish_reason flag
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }
        if(!empty($jsonChunk['usage'])){
            $usage = [
                'prompt_tokens' => $jsonChunk['usage']['prompt_tokens'],
                'completion_tokens' =>  $jsonChunk['usage']['completion_tokens'],
            ];
        }

        $chunk = '';
        if (isset($jsonChunk['choices'][0]['delta']['content'])) {
            $chunk =  $jsonChunk['choices'][0]['delta']['content'];
        }

        return [$chunk, $isDone, $usage];
    }

    public function formatDefaultResponse($response){
        $responseContent = $response->getContent();
        
        $jsonContent = json_decode($responseContent, true);
        $content = $jsonContent['choices'][0]['message']['content'];
        
        $usage = NULL;
        if(!empty($jsonContent['usage'])){
            $usage = [
                'prompt_tokens' => $jsonContent['usage']['prompt_tokens'],
                'completion_tokens' =>  $jsonContent['usage']['completion_tokens'],
            ];
        }
        
        return [$content, $usage];
    }

    public function formatGoogleResponse($response){
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);
        $content = $jsonContent['candidates'][0]['content']['parts'][0]['text'];
        
        $usage = NULL;
        if(!empty($jsonContent['usage'])){
            $usage = [
                'prompt_tokens' => $jsonContent['usageMetadata']['promptTokenCount'],
                'completion_tokens' =>  $jsonContent['usageMetadata']['candidatesTokenCount'],
            ];
        }
        return [$content, $usage];
    }



}