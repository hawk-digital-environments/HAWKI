<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Plugins\PluginAwareTraitTest\PluginAwareTraitTestFixtures;

use App\Services\System\Plugins\Attributes\PluginName;
use App\Services\System\Plugins\PluginAwareTrait;

/**
 * Fixture resolving its plugin explicitly via the attribute — the recommended way for
 * classes outside the `App\` namespace.
 */
#[PluginName('hawki-core')]
class ClassWithExplicitPluginName
{
    use PluginAwareTrait;
}
