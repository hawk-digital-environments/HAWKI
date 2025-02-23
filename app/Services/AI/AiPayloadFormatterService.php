<?php

namespace App\Services\AI;
use Illuminate\Support\Facades\Log;

use App\Services\AI\ModelUtilityService;


class AiPayloadFormatterService
{
    public function __construct(ModelUtilityService $utilities){
        $this->utilities = $utilities;
    }


    /**
     * Format payload for a specific provider.
     *
     * @param array $payload
     * @return array
     */
    public function formatPayload(array $payload): array
    {
        $provider = $this->utilities->getProviderId($payload['model']);

        switch ($provider) {
            case 'openai':
            case 'gwdg':
                return $this->formatForOpenAi($payload, $provider);
            case 'google':
                return $this->formatForGoogle($payload);
            default:
                throw new \Exception("Unsupported provider");
        }
    }


    /**
     * Format the payload for OpenAI or GWDG.
     *
     * @param array $payload
     * @param string $provider
     * @return array
     */
    private function formatForOpenAi(array $payload, string $provider): array
    {
        $messages = $payload['messages'];

        // Handle GWDG special role case
        if ($provider === 'gwdg') {
            //mixtral does not accept system role and should recieve a 
            if ($payload['model'] === 'mixtral-8x7b-instruct') {
                if($messages[0]['role'] === 'system')
                $messages[0]['role'] = 'user';
            }
        }
        if($provider === 'openai'){
            if ($payload['model'] === 'o1-mini') {
                if($messages[0]['role'] === 'system'){
                    $messages[0]['role'] = 'user';
                }

                $payload['stream'] = false;
            }
        }

        // Format messages for OpenAI
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text']
            ];
        }

        return [
            'model' => $payload['model'],
            'messages' => $formattedMessages,
            'stream' => $payload['stream'],
        ];
    }

    /**
     * Format the payload for Google Gemini.
     *
     * @param array $payload
     * @return array
     */
    private function formatForGoogle(array $payload): array
    {
        $messages = $payload['messages'];


        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    'text' => $message['content']['text']
                ]
            ];
        }

        return [
            'model' => $payload['model'], // Assuming Google might need to know the model
            'contents' => $formattedMessages,
            'stream' => true,
            // Include other parameters as needed by Google's API
        ];
    }
}