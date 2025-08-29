<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtAppConnectionResource extends JsonResource
{
    public function __construct(
        protected array  $websocket,
        protected string $baseUrl,
        protected string $aiHandle,
        protected array  $aiModels,
        protected array  $salt,
        protected array  $userinfo,
        protected array  $userSecrets,
    )
    {
        parent::__construct(null);
    }
    
    public function toArray(Request $request): array
    {
        return [
            'websocket' => $this->websocket,
            'base_url' => $this->baseUrl,
            'ai_handle' => $this->aiHandle,
            'ai_models' => $this->aiModels,
            'salt' => $this->salt,
            'userinfo' => $this->userinfo,
            'user_secrets' => $this->userSecrets,
        ];
    }
}
