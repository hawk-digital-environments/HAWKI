<?php
declare(strict_types=1);


namespace App\JsonApi\V1\ExtApps;


use App\Services\ExtApp\ConnectRequestCrypto;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Schema\IdEncoder;
use LaravelJsonApi\Eloquent\Fields\ID;

class ExtAppId extends ID implements IdEncoder
{
    use ServiceLocatorTrait;

    /**
     * @inheritDoc
     */
    public function encode($modelKey): string
    {
        return (string)$modelKey;
    }

    /**
     * @inheritDoc
     */
    public function decode(string $resourceId): int|null
    {
        if (is_numeric($resourceId)) {
            return (int)$resourceId;
        }

        $payload = $this->getService(ConnectRequestCrypto::class)->decryptPayload($resourceId);

        if (!$payload) {
            abort(400, 'The connect request is invalid or has expired.');
        }

        return $payload->appId ?? null;
    }
}
