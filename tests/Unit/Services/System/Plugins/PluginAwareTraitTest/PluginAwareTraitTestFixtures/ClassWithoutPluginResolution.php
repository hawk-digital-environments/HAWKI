<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Plugins\PluginAwareTraitTest\PluginAwareTraitTestFixtures;

use App\Services\System\Plugins\PluginAwareTrait;

/**
 * Fixture in a namespace that matches no plugin — forces the unresolvable-class path.
 */
class ClassWithoutPluginResolution
{
    use PluginAwareTrait;
}
