<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\JsonApi\Resources\HasActionLinks;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Resources\JsonApiResource;

class AssistantResource extends JsonApiResource
{
    use HasActionLinks;

    public function links($request): Links
    {
        return $this->actionLinks($request);
    }
}
