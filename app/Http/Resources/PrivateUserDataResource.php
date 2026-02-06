<?php

namespace App\Http\Resources;

use App\Models\PrivateUserData;
use App\Services\Encryption\EncryptionUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrivateUserDataResource extends JsonResource
{
    /**
     * @var PrivateUserData
     */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'value' => (string)EncryptionUtils::symmetricCryptoValueFromStrings(
                $this->resource->KCIV,
                $this->resource->KCTAG,
                $this->resource->keychain
            )
        ];
    }
}
