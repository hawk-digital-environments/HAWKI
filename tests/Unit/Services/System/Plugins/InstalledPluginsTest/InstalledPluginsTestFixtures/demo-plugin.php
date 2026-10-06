<?php

declare(strict_types=1);

/**
 * Plugin cache fixture with a single demo plugin for
 * {@see \Tests\Unit\Services\System\Plugins\InstalledPluginsTest}.
 */

use Tests\Unit\Services\System\Plugins\PluginRegistryTest\PluginRegistryTestFixtures\DemoPlugin;

return [
    'hawk/demo-plugin' => [
        'class' => DemoPlugin::class,
        'namespace' => 'hawk-demo-plugin',
        'namespaces' => ['Hawk\\DemoPlugin\\'],
        'version' => '1.0.0',
        'path' => '/plugins/hawk/demo-plugin',
    ],
];
