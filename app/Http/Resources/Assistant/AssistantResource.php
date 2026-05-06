<?php

declare(strict_types=1);

namespace App\Http\Resources\Assistant;

use App\Http\Resources\AiTool\AiToolResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Language\LanguageResource;
use App\Http\Resources\Organization\OrganizationResource;
use App\Http\Resources\Tag\TagResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\UserPrompt\UserPromptResource;
use App\Http\Resources\Version\VersionResource;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AssistantResource extends JsonApiResource
{
    public $attributes = [
        'name',
        'handle',
        'system_prompt',
        'greeting',
        'description',
        'detail_description',
        'allow_remix',
        'allow_model_select',
        'release_stage',
        'formality',
        'model',
        'model_length',
        'model_temp',
        'model_top_p',
        'created_at',
        'updated_at',
    ];

    public $relationships = [
        'language' => LanguageResource::class,
        'category' => CategoryResource::class,
        'user_prompts' => UserPromptResource::class,
        'ai_tools' => AiToolResource::class,
        'tags' => TagResource::class,
        'creator' => UserResource::class,
        'remix_creator' => UserResource::class,
        'remixed_assistant' => AssistantResource::class,
        'versions' => VersionResource::class,
        'organization' => OrganizationResource::class,
    ];
}
