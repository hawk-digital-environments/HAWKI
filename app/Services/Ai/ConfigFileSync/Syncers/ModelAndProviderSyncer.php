<?php
declare(strict_types=1);

namespace App\Services\Ai\ConfigFileSync\Syncers;

use App\Models\Ai\AiProvider;
use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ProviderAdapters\WellKnownAdapterKeys;
use App\Services\Ai\Registries\AiModelSettingRegistry;
use App\Services\Ai\Repositories\AiModelRepository;
use App\Services\Ai\Repositories\AiModelUsageRuleRepository;
use App\Services\Ai\Repositories\AiProviderRepository;
use App\Services\Ai\Values\ModelCapabilities;
use App\Services\Ai\Values\ModelCapabilityValueType;
use App\Services\Ai\Values\ModelIoMethods;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ModelSettings;
use App\Services\Ai\Values\ProviderSettings;
use App\Services\Ai\Values\WellKnownCapabilities;
use App\Services\Ai\Values\WellKnownProviderSettings;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Config;


readonly class ModelAndProviderSyncer implements ConfigSyncerInterface
{
    private const BUILT_IN_PROVIDER_ID_TO_ADAPTER_KEY_MAP = [
        'openAi' => WellKnownAdapterKeys::OPENAI_RESPONSES,
        'gwdg' => WellKnownAdapterKeys::GWDG,
        'google' => WellKnownAdapterKeys::GEMINI,
        'ollama' => WellKnownAdapterKeys::OLLAMA,
        'openWebUi' => WellKnownAdapterKeys::OPEN_WEB_UI
    ];

    public function __construct(
        #[Config('model_providers.providers')]
        private array                      $providers,
        private AiModelRepository          $modelRepository,
        private AiProviderRepository       $providerRepository,
        private AiModelUsageRuleRepository $useRuleRepository,
        private AiModelSettingRegistry     $settingRegistry
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
                    ModelParameters::fromArray($config['config'][WellKnownProviderSettings::MODEL_PARAMETERS])
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

        foreach ($config['models'] ?? [] as $modelConfig) {
            $this->syncModel($provider, $modelConfig, $metrics);
        }

        $metrics->increment('AI providers');
    }

    private function syncModel(AiProvider $provider, array $config, JobMetrics $metrics): void
    {
        $modelId = $config['id'] ?? null;
        if (empty($modelId)) {
            return;
        }

        $settings = ModelSettings::fromArray([], $this->settingRegistry);
        if (!empty($config['max_tool_calling_rounds'])) {
            $settings->setMaxToolCallingRounds((int)$config['max_tool_calling_rounds'], streaming: false);
        }
        if (!empty($config['max_tool_calling_rounds_streaming'])) {
            $settings->setMaxToolCallingRounds((int)$config['max_tool_calling_rounds_streaming'], streaming: true);
        }

        $parameters = ModelParameters::fromArray([]);
        if (!empty($config['default_params'])) {
            if (!empty($config['default_params']['temp'])) {
                $parameters->setTemperature((float)$config['default_params']['temp']);
            }
            if (!empty($config['default_params']['top_p'])) {
                $parameters->setTopP((float)$config['default_params']['top_p']);
            }
        }

        $capabilities = ModelCapabilities::fromArray([]);
        if (!empty($config['tools']) && is_array($config['tools'])) {
            $tools = $config['tools'];

            $settings->setUseTools($tools['tool_calling'] === true);
            $settings->setHandleFiles($tools['file_upload'] === true);

            foreach ([
                         WellKnownCapabilities::WEB_SEARCH,
                         WellKnownCapabilities::KNOWLEDGE_BASE
                     ] as $capability) {
                if (isset($tools[$capability])) {
                    $toolValue = $tools[$capability];

                    $isTruthy = in_array($toolValue, [true, 'true', 1, '1'], true);
                    $isFalsy = in_array($toolValue, [false, 'false', 0, '0', null, 'null'], true)
                        || (is_string($toolValue) && strtolower($toolValue) === 'unsupported');
                    $isNative = $toolValue === 'native';

                    if ($isNative) {
                        $capabilities->set($capability, ModelCapabilityValueType::NATIVE);
                    } elseif ($isTruthy && !$isFalsy) {
                        $capabilities->set($capability, ModelCapabilityValueType::YES);
                    } else {
                        $capabilities->set($capability, ModelCapabilityValueType::NO);
                    }
                }
            }
        }

        $model = $this->modelRepository->upsert(
            provider: $provider,
            modelId: $modelId,
            active: (bool)($config['active'] ?? true),
            label: $config['label'] ?? $modelId,
            input: ModelIoMethods::fromArray($config['input'] ?? []),
            output: ModelIoMethods::fromArray($config['output'] ?? []),
            parameters: $parameters,
            settings: $settings,
            capabilities: $capabilities
        );

        $this->useRuleRepository->assignTypeToModel($model, WellKnownUsageTypes::MAIN_APP);
        $this->useRuleRepository->toggleTypeOfModel(
            $model,
            WellKnownUsageTypes::EXTERNAL_APP,
            isset($config['external']) && $config['external'] === true
        );


        $metrics->increment('AI models');
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
