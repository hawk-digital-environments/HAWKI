<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Announcements;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

/**
 * Validation for PATCH requests on the `announcements` resource. The only
 * writable attributes are the `seen` / `accepted` transition flags; at least one
 * must be present.
 */
class AnnouncementRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     *
     * At least one transition flag must be present (`required_without` both ways);
     * other writable-looking attributes (e.g. `title`) carry no rule, never reach
     * the write path and are ignored.
     */
    public function rules(): array
    {
        return [
            'seen' => ['required_without:accepted', 'boolean'],
            'accepted' => ['required_without:seen', 'boolean'],
        ];
    }
}
