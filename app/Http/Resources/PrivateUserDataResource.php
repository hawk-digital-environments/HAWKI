<?php

namespace App\Http\Resources;

use App\Models\PrivateUserData;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
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
            'value' => (string)new SymmetricCryptoValue(
                $this->resource->KCIV,
                $this->resource->KCTAG,
                $this->resource->keychain
            )
        ];
    }
}
