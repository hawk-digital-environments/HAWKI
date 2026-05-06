<?php

declare(strict_types=1);

namespace App\Http\Resources\AssistantReview;

use App\Http\Resources\Assistant\AssistantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AssistantReviewResource extends JsonApiResource
{
    public $attributes = [
        'status',
        'reason',
        'created_at',
        'updated_at',
    ];

    public $relationships = [
        'assistant' => AssistantResource::class,
    ];

    public function toType(Request $request): string
    {
        return 'reviews';
    }
}
