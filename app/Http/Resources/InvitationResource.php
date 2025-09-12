<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use App\Services\Encryption\EncryptionUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * @var Invitation
     */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'room_id' => $this->resource->room_id,
            'room_slug' => $this->resource->room->slug,
            'invitation' => (string)EncryptionUtils::symmetricCryptoValueFromStrings(
                $this->resource->iv,
                $this->resource->tag,
                $this->resource->invitation
            ),
        ];
    }
}
