<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\JsonSerializableTrait;

readonly class FeatureFlags implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public bool $aiInGroups
    )
    {
    }
    
    public static function createAllowAll(): self
    {
        return new self(
            aiInGroups: true
        );
    }
}
