<?php

declare(strict_types=1);

namespace App\Http\Resources\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'handle' => $this->handle,
            'system_prompt' => $this->system_prompt,
            'greeting' => $this->greeting,
            'description' => $this->description,
            'detail_description' => $this->detail_description,
            'allow_remix' => $this->allow_remix,
            'allow_model_select' => $this->allow_model_select,
            'language' => $this->language,
            'category' => $this->category,
            'review_stage' => $this->review_stage,
            'formality' => $this->formality,
            'model' => $this->model,
            'model_length' => $this->model_length,
            'model_temp' => $this->model_temp,
            'model_top_p' => $this->model_top_p,
            'creator_id' => $this->creator_id,
            'remixed_creator_id' => $this->remixed_creator_id,
            'remixed_assistant_id' => $this->remixed_assistant_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user_prompts' => $this->whenLoaded('userPrompts'),
            'ai_tools' => $this->whenLoaded('aiTools'),
            'tags' => $this->whenLoaded('tags'),
            'creator' => $this->whenLoaded('creator'),
            'versions' => $this->whenLoaded('versions'),
        ];
    }
}
