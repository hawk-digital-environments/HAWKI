<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;


use App\Services\Translation\Value\Locale;

readonly class Connection
{
    public function __construct(
        public string             $id,
        public ConnectionType     $type,
        public string             $version,
        public Locale             $locale,
        public Userinfo|null      $userinfo = null,
        public ExtAppSecrets|null $extAppSecrets = null,
        public string|null        $extAppConnectRequest = null,
        public int|null           $migrationsToApply = null
    )
    {
    }
}
