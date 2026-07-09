<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config\PublicConfigRegistryTestFixtures;

use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Minimal PublicConfigInterface + AbstractConfig implementation for unit tests.
 */
class ConcretePublicConfig extends AbstractConfig implements PublicConfigInterface
{
    public string $data = '';

    public static function make(Repository $repo): static
    {
        return self::fromArray(['data' => 'from-make']);
    }

    public static function publicKey(): string
    {
        return 'test_public_config';
    }

    public function toPublicArray(Request $request): array|null
    {
        return ['data' => $this->data];
    }
}
