<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value\Connection;


use App\Services\Frontend\Connection\Value\AiConfig;
use App\Services\Frontend\Connection\Value\ExtAppSecrets;
use App\Services\Frontend\Connection\Value\FeatureFlags;
use App\Services\Frontend\Connection\Value\LocaleConfig;
use App\Services\Frontend\Connection\Value\Salts;
use App\Services\Frontend\Connection\Value\StorageConfig;
use App\Services\Frontend\Connection\Value\TransferConfig;
use App\Services\Frontend\Connection\Value\Userinfo;

readonly class ExtAppConnection extends AbstractConnection
{
    public function __construct(
        string               $version,
        LocaleConfig         $locale,
        FeatureFlags         $featureFlags,
        AiConfig             $ai,
        Userinfo             $userinfo,
        Salts                $salts,
        StorageConfig        $storage,
        TransferConfig       $transfer,
        public ExtAppSecrets $secrets,
    )
    {
        parent::__construct(
            version: $version,
            featureFlags: $featureFlags,
            locale: $locale,
            ai: $ai,
            userinfo: $userinfo,
            salts: $salts,
            storage: $storage,
            transfer: $transfer,
        );
    }
}
