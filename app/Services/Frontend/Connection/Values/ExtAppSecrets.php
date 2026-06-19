<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;


readonly class ExtAppSecrets
{
    public function __construct(
        public string $passkey,
        public string $apiToken,
        public string $privateKey,
    )
    {
    }
}
