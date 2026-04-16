<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\JsonSerializableTraitTestFixtures;

use App\Utils\JsonSerializableTrait;

class NoPublicPropertiesModel implements \JsonSerializable
{
    use JsonSerializableTrait;

    protected string $hidden = 'secret';
    private string $internal = 'private';
}
