<?php

namespace App\Http\Resources;

use App\Models\Invitation;
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
            'room_id' => $this->resource->room_id,
            'invitation' => $this->resource->invitation,
            'iv' => $this->resource->iv,
            'tag' => $this->resource->tag,
        ];
    }
}
