<?php

namespace App\JsonApi\V1\Announcements;

use App\Services\Announcements\Values\AnnouncementForUser;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property AnnouncementForUser $resource
 */
class AnnouncementResource extends JsonApiResource
{
    /**
     * Returns a unique id to identify this resource
     */
    public function id(): string
    {
        return (string)$this->resource->id;
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return [
            'title' => $this->resource->title,
            'type' => $this->resource->type,
            'is_global' => $this->resource->isGlobal,
            'is_forced' => $this->resource->isForced,
            'anchor' => $this->resource->anchor,
            'starts_at' => $this->resource->startsAt?->toJSON(),
            'expires_at' => $this->resource->expiresAt?->toJSON(),
            'is_active' => $this->resource->isActive,
            'content' => $this->resource->content,
            'seen_at' => $this->resource->seenAt?->toJSON(),
            'accepted_at' => $this->resource->acceptedAt?->toJSON(),
            'seen_count' => $this->resource->seenCount,
        ];
    }

    /**
     * Get the resource's relationships.
     *
     * @param Request|null $request
     */
    public function relationships($request): iterable
    {
        return [
        ];
    }
}
