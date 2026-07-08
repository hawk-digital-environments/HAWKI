<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;

/**
 * String constants for the built-in provider adapter keys registered by the HAWKI core.
 *
 * Pass these constants to {@see \App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry::declare()}
 * when registering a built-in adapter, or to
 * {@see \App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry::get()} when retrieving one.
 *
 * Third-party packages may define their own string keys without using this interface.
 *
 * @api
 */
interface WellKnownAdapterKeys
{
    public const ANTHROPIC = 'anthropic';
    public const OPENAI = 'openai';
    public const OPENAI_LIKE = 'openai_like';
    public const OPENAI_AZURE = 'openai_azure';
    public const OLLAMA = 'ollama';
    public const OPEN_WEB_UI = 'open_web_ui';
    public const GEMINI = 'gemini';
    public const GWDG = 'gwdg';
    public const OPEN_ROUTER = 'open_router';
    public const MISTRAL = 'mistral';
    public const ZAI = 'zai';
    public const HUGGINGFACE = 'huggingface';
    public const DEEPSEEK = 'deepseek';
    public const XAI = 'xai';
    public const AWS_BEDROCK = 'aws_bedrock';
    public const COHERE = 'cohere';
}
