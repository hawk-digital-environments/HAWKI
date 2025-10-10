<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value\Connection;


use App\Services\Frontend\Connection\Value\AiConfig;
use App\Services\Frontend\Connection\Value\FeatureFlags;
use App\Services\Frontend\Connection\Value\LocaleConfig;
use App\Services\Frontend\Connection\Value\Salts;
use App\Services\Frontend\Connection\Value\StorageConfig;
use App\Services\Frontend\Connection\Value\TransferConfig;
use App\Services\Frontend\Connection\Value\Userinfo;
use App\Utils\JsonSerializableTrait;

abstract readonly class AbstractConnection implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public string         $version,
        public FeatureFlags   $featureFlags,
        public LocaleConfig   $locale,
        public AiConfig       $ai,
        public Userinfo       $userinfo,
        public Salts          $salts,
        public StorageConfig  $storage,
        public TransferConfig $transfer,
    )
    {
    }
}
