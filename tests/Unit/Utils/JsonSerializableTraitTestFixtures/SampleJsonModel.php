<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\JsonSerializableTraitTestFixtures;

use App\Utils\JsonSerializableTrait;

class SampleJsonModel implements \JsonSerializable
{
    use JsonSerializableTrait;

    public string $name = 'alice';
    public int $age = 30;
    public string $uninitializedProp; // intentionally not initialized
    protected string $hidden = 'secret';
    private string $internal = 'private';
    public static string $staticProp = 'static';
}
