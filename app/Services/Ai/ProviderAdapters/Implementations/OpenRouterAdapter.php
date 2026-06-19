<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


class OpenRouterAdapter extends OpenAiLikeAdapter
{
    protected string|null $baseUrl = 'https://openrouter.ai/api/v1';
}
