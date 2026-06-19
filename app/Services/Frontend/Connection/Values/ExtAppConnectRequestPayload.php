<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;


use App\Utils\Casts\AbstractCastableObject;

class ExtAppConnectRequestPayload extends AbstractCastableObject
{
    public readonly int $appId;
    public readonly string $version;
    public readonly string $extAppUserId;
}
