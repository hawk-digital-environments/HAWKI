<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncerTestFixtures;

use App\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncer;

readonly class ExposedModelAndProviderSyncer extends ModelAndProviderSyncer
{
    public function exposeStripSuffix(string $input): string
    {
        return $this->stripWellKnownPathSuffix($input);
    }
}
