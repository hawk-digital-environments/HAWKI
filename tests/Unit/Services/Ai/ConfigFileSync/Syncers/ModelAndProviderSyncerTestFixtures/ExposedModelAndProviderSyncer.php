<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncerTestFixtures;

use App\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncer;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;

readonly class ExposedModelAndProviderSyncer extends ModelAndProviderSyncer
{
    public function exposeStripSuffix(string $input): string
    {
        return $this->stripWellKnownPathSuffix($input);
    }

    protected function makeModelSettings(): AiModelSettings
    {
        // Mirrors the well-known declarations of AiServiceProvider, which the unit test
        // scope cannot resolve because no container is available to the settings object.
        $registry = new AiModelSettingRegistry();
        $registry->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS, 5);
        $registry->declare(WellKnownModelSettings::FILE_UPLOAD, false);
        $registry->declare(WellKnownModelSettings::TOOL_CALLING, false);
        $registry->declare(WellKnownModelSettings::NATIVE_CAPABILITIES, true);

        $settings = AiModelSettings::fromArray([]);
        $settings->setService(AiModelSettingRegistry::class, $registry);
        return $settings;
    }

    public function exposeParseMaxToolCallingRounds(array $config): int|null
    {
        return $this->parseMaxToolCallingRounds($config);
    }
}
