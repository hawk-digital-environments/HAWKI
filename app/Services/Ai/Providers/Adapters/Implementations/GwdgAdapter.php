<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\OpenAiCompatibleProvider;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterCreatesDriverInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for the GWDG Chat-AI service (AcademicCloud).
 *
 * GWDG runs an OpenAI-compatible chat-completion endpoint for academic institutions.
 * It uses a custom driver ({@see OpenAiCompatibleProvider}) instead of the standard
 * OpenAI driver because the Laravel AI framework does not yet support chat-completion
 * endpoints natively — only the newer "responses" API.
 *
 * This adapter extends {@see OpenAiLikeAdapter} but overrides:
 * - {@see createDriver()} — uses the `openai-compatible` custom provider with GWDG's
 *   default base URL (`https://chat-ai.academiccloud.de/v1`).
 * - {@see getModels()} — maps GWDG's richer model metadata (modality arrays, display
 *   names, documentation links) on top of the standard OpenAI model shape.
 * - {@see checkModelStatus()} — reads per-model `status` and `demand` fields from
 *   the same `/models` response to populate online-status and demand collections.
 * - {@see supportsFileAsAttachment()} — restricts native attachments to images only,
 *   falling back to text embedding for all other file types.
 *
 * @see https://docs.hpc.gwdg.de/services/ai-services/chat-ai/models/index.html GWDG model docs
 * @see app/Services/Ai/LaravelAi/Drivers/OpenaiCompatible/README.md Custom driver rationale
 */
class GwdgAdapter extends OpenAiLikeAdapter implements ProviderAdapterCreatesDriverInterface
{
    public const string DEFAULT_DOCUMENTATION_URL = 'https://docs.hpc.gwdg.de/services/ai-services/chat-ai/models/index.html';

    /**
     * Returns true only for image MIME types.
     *
     * GWDG's endpoint accepts inline image attachments but not arbitrary binary files,
     * so non-image uploads are sent as extracted text instead.
     */
    public function supportsFileAsAttachment(FileInterface $file): bool
    {
        return str_starts_with($file->getMimeType(), 'image/');
    }

    /**
     * Creates an OpenAI-compatible driver pointed at GWDG's chat-completion endpoint.
     *
     * Falls back to `https://chat-ai.academiccloud.de/v1` when no `api_url` is stored,
     * because that is the stable public GWDG base URL for academic users.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::OpenAICompatible,
            config: [
                'key' => $provider->api_key,
                'url' => $provider->api_url ?? 'https://chat-ai.academiccloud.de/v1',
            ],
        );
    }

    /**
     * Fetches models from GWDG's `/models` endpoint using a custom mapper that reads
     * GWDG-specific fields beyond what the standard OpenAI shape provides.
     *
     * Extra fields mapped per model:
     * - `input` / `output` — modality arrays (e.g. `["text", "image"]`) used to populate
     *   the model's I/O method sets. A model is only typed as {@see WellKnownModelTypes::CHAT}
     *   when both arrays contain `"text"`.
     * - `name` — human-readable display label stored as `model->label`.
     * - `documentation_url` — set to {@see DEFAULT_DOCUMENTATION_URL} for all GWDG models.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList(
            $provider,
            $this->createModelListClient($this->client($provider->driver)),
            alternativeMapper: function (array $item) use ($provider) {
                $input = data_get($item, 'input', []);
                $output = data_get($item, 'output', []);
                $inputOutputContainsText = in_array('text', $input, true) && in_array('text', $output, true);
                $modelInfo = $this->createNewModelInfo(
                    modelId: data_get($item, 'id'),
                    provider: $provider,
                    modelType: $inputOutputContainsText ? WellKnownModelTypes::CHAT : null
                );
                $modelInfo->label = data_get($item, 'name');
                $modelInfo->documentation_url = self::DEFAULT_DOCUMENTATION_URL;
                $this->enrichInput($modelInfo, $input);
                $this->enrichOutput($modelInfo, $output);
                return $modelInfo;
            }
        );
    }

    /**
     * Probes GWDG's `/models` endpoint and populates both status and demand collections.
     *
     * GWDG includes `status` and `demand` fields in the standard model-list response,
     * so a single HTTP call covers both concerns without a dedicated health endpoint.
     *
     * Status mapping:
     * - `"ready"`   → {@see OnlineStatus::ONLINE}
     * - `"offline"` → {@see OnlineStatus::OFFLINE}
     * - anything else → {@see OnlineStatus::UNKNOWN}
     *
     * Demand mapping (numeric `demand` field):
     * - ≥ 4 → {@see ModelDemand::HIGH}
     * - ≥ 2 → {@see ModelDemand::MEDIUM}
     * - < 2 → {@see ModelDemand::LOW}
     */
    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        $this->fetchOpenAiModelList(
            $provider,
            $this->createModelListClient($this->client($provider->driver)),
            alternativeMapper: function (array $item) use ($statusCollection, $demandCollection) {
                $modelId = data_get($item, 'id');

                $status = match ($item['status']) {
                    'ready' => OnlineStatus::ONLINE,
                    'offline' => OnlineStatus::OFFLINE,
                    default => OnlineStatus::UNKNOWN,
                };
                $statusCollection->set($modelId, $status);

                $demandInt = data_get($item, 'demand', 0);
                $demand = match (true) {
                    $demandInt >= 4 => ModelDemand::HIGH,
                    $demandInt >= 2 => ModelDemand::MEDIUM,
                    default => ModelDemand::LOW,
                };
                $demandCollection->set($modelId, $demand);
            });
    }
}
