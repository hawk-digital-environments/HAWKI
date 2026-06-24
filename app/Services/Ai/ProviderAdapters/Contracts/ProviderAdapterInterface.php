<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Contracts;


use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\ProviderTool;

interface ProviderAdapterInterface
{
    public function getNameLabel(): string|null;

    public function getDescriptionLabel(): string|null;

    public function supportsChat(): bool;

    public function supportsStreaming(): bool;

    public function supportsImageGeneration(): bool;

    public function supportsTextToSpeech(): bool;

    public function supportsSpeechToText(): bool;

    public function createNeuronProvider(ParameterSource $source): AIProviderInterface;

    public function createHttpClient(ParameterSource $source): HttpClientInterface|null;

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void;


    public function getProviderToolForCapability(string $capability, ParameterSource $source): ProviderTool|null;
}
