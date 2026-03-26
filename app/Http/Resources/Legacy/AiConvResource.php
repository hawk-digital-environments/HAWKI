<?php

namespace App\Http\Resources\Legacy;

use App\Models\AiConv;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiConv */
class AiConvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->conv_name,
            'slug' => $this->slug,
            'system_prompt' => $this->system_prompt,
            'messages' => $this->messages->toResourceCollection(AiConvMsgResource::class),
        ];
    }
}
