<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Traits;


use App\Services\Ai\Values\ParameterSource;

trait OpenAiParameterTrait
{
    private function buildOpenAiCompletionsParameters(ParameterSource $source): array
    {
        /* @see https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create */
        return array_merge(
            [
                'temperature' => $source->getTemperature(),
                'top_p' => $source->getTopP(),
                'max_completion_tokens' => $source->getMaxTokens(),
            ],
            $source->toAdditionalArray()
        );
    }

    private function buildOpenAiResponsesParameters(ParameterSource $source): array
    {
        /* @see https://developers.openai.com/api/reference/resources/responses/methods/create */
        return array_merge(
            [
                'temperature' => $source->getTemperature(),
                'top_p' => $source->getTopP(),
                'max_output_tokens' => $source->getMaxTokens(),
            ],
            $source->toAdditionalArray()
        );
    }
}
