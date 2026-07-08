<?php
declare(strict_types=1);

namespace App\Services\Ai\ConfigFileSync\Syncers;

use App\Models\Ai\AiProvider;
use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Repositories\AiModelDescriptionRepository;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Models\Repositories\AiModelUsageRuleRepository;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\Adapters\WellKnownAdapterKeys;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\Ai\Providers\Values\ProviderSettings;
use App\Services\Ai\Providers\Values\WellKnownProviderSettings;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Config;


readonly class ModelAndProviderSyncer implements ConfigSyncerInterface
{
    private const array BUILT_IN_PROVIDER_ID_TO_ADAPTER_KEY_MAP = [
        'openAi' => WellKnownAdapterKeys::OPENAI,
        'gwdg' => WellKnownAdapterKeys::GWDG,
        'google' => WellKnownAdapterKeys::GEMINI,
        'ollama' => WellKnownAdapterKeys::OLLAMA,
        'openWebUi' => WellKnownAdapterKeys::OPEN_WEB_UI
    ];

    public function __construct(
        #[Config('model_providers.providers')]
        private array                        $providers,
        private AiModelRepository            $modelRepository,
        private AiModelDescriptionRepository $modelDescriptionRepository,
        private AiProviderRepository         $providerRepository,
        private AiProviderProxyResolver      $providerProxyResolver,
        private AiModelUsageRuleRepository   $useRuleRepository,
        private ModelInfoFetcher             $modelInfoFetcher,
    )
    {
    }

    public function getCurrentHash(): string
    {
        return md5(json_encode($this->providers));
    }

    public function sync(JobMetrics $metrics): void
    {
        foreach ($this->providers as $providerId => $rawConfig) {
            $this->syncProvider($providerId, $rawConfig, $metrics);
        }
    }

    private function syncProvider(string $providerId, array $config, JobMetrics $metrics): void
    {
        $adapterKey = $config['adapter_key'] ?? self::BUILT_IN_PROVIDER_ID_TO_ADAPTER_KEY_MAP[$providerId] ?? null;
        if (empty($adapterKey)) {
            $metrics->error(sprintf(
                'No adapter key specified for provider "%s" and no built-in adapter key found for this provider ID.',
                $providerId
            ));
            return;
        }

        $settings = ProviderSettings::fromArray([]);
        if (is_array($config['config'] ?? null)) {
            $settings->set(WellKnownProviderSettings::ADAPTER, $config['adapter'] ?? []);
            if (is_array($config['config'][WellKnownProviderSettings::MODEL_PARAMETERS] ?? null)) {
                $settings->set(
                    WellKnownProviderSettings::MODEL_PARAMETERS,
                    AiModelParameters::fromArray($config['config'][WellKnownProviderSettings::MODEL_PARAMETERS])
                );
            }
        }

        $provider = $this->providerRepository->upsert(
            providerId: $providerId,
            adapterKey: $adapterKey,
            name: ucfirst($providerId),
            active: (bool)($config['active'] ?? false),
            apiUrl: !empty($config['api_url']) && is_string($config['api_url'])
                ? $this->stripWellKnownPathSuffix($config['api_url'])
                : null,
            modelStatusUrl: $config['ping_url'] ?? null,
            apiKey: $config['api_key'] ?? null,
            settings: $settings
        );

        $syncedModelIds = [];
        foreach ($config['models'] ?? [] as $modelConfig) {
            $modelId = $this->syncModel($provider, $modelConfig, $metrics);
            if (!empty($modelId)) {
                $syncedModelIds[] = $modelId;
            }
        }

        $this->modelRepository->disableAllExcept($syncedModelIds, $provider);

        $metrics->increment('AI providers');
    }

    private function syncModel(AiProvider $provider, array $config, JobMetrics $metrics): string|null
    {
        $modelId = $config['id'] ?? null;
        if (empty($modelId)) {
            return null;
        }

        $settings = AiModelSettings::fromArray([]);
        if (!empty($config['max_tool_calling_rounds'])) {
            $settings->setMaxToolCallingRounds((int)$config['max_tool_calling_rounds'], streaming: false);
        }
        if (!empty($config['max_tool_calling_rounds_streaming'])) {
            $settings->setMaxToolCallingRounds((int)$config['max_tool_calling_rounds_streaming'], streaming: true);
        }

        $parameters = AiModelParameters::fromArray([]);
        if (!empty($config['default_params'])) {
            if (!empty($config['default_params']['temp'])) {
                $parameters->setTemperature((float)$config['default_params']['temp']);
            }
            if (!empty($config['default_params']['top_p'])) {
                $parameters->setTopP((float)$config['default_params']['top_p']);
            }
        }

        if (!empty($config['tools']) && is_array($config['tools'])) {
            $tools = $config['tools'];

            $settings->setUseTools(($tools['tool_calling'] ?? false) === true);
            $settings->setHandleFiles(($tools['file_upload'] ?? false) === true);
            $settings->setUseNativeCapabilities(($tools['native_capabilities'] ?? false) === true);
        }

        $modelInfo = $this->modelInfoFetcher->fetchSingle($this->providerProxyResolver->resolve($provider), $modelId);

        $model = $this->modelRepository->upsert(
            modelType: $modelInfo?->type ?? WellKnownModelTypes::CHAT,
            provider: $provider,
            modelId: $modelId,
            active: (bool)($config['active'] ?? true),
            label: $config['label'] ?? $modelInfo?->label ?? $modelId,
            input: AiModelIoMethods::fromArray($config['input'] ?? []),
            output: AiModelIoMethods::fromArray($config['output'] ?? []),
            parameters: $parameters,
            settings: $settings,
            limits: $modelInfo?->limits,
            pricing: $modelInfo?->pricing,
            flags: $modelInfo?->flags,
            nativeCapabilities: $modelInfo?->native_capabilities,
            deprecationDate: $modelInfo?->deprecation_date,
            documentationUrl: $modelInfo?->documentation_url
        );

        $this->useRuleRepository->assignTypeToModel($model, WellKnownUsageTypes::MAIN_APP);
        $this->useRuleRepository->toggleTypeOfModel(
            $model,
            WellKnownUsageTypes::EXTERNAL_APP,
            isset($config['external']) && $config['external'] === true
        );

        foreach ($modelInfo?->description ?? [] as $description) {
            $this->modelDescriptionRepository->assignDescriptionToModel($model, $description);
        }

        $metrics->increment('AI models');

        return $modelId;
    }

    /**
     * This is a legacy helper to silently strip common suffixes from the API URI
     * This should avoid issues when the old api url is still hardcoded with a suffix like /v1/chat/completions.
     * Our new adapters do not like that and expect the base URI only, so we silently strip those suffixes here to avoid breaking old configurations.
     *
     * @param string $apiUri
     * @return string
     */
    protected function stripWellKnownPathSuffix(string $apiUri): string
    {
        $apiUri = strtolower($apiUri);
        $suffixes = [
            '/chat/completions',
            '/completions'
        ];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($apiUri, $suffix)) {
                return substr($apiUri, 0, -strlen($suffix));
            }
        }

        return $apiUri;
    }
}
