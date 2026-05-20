<?php

declare(strict_types=1);

namespace App\JsonApi\V1;

use App\JsonApi\V1\AiModels\AiModelSchema;
use App\JsonApi\V1\AiProviders\AiProviderSchema;
use App\JsonApi\V1\AiTools\AiToolSchema;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Categories\CategorySchema;
use App\JsonApi\V1\Languages\LanguageSchema;
use App\JsonApi\V1\McpServers\McpServerSchema;
use App\JsonApi\V1\Organizations\OrganizationSchema;
use App\JsonApi\V1\Reviews\ReviewSchema;
use App\JsonApi\V1\Tags\TagSchema;
use App\JsonApi\V1\UserPrompts\UserPromptSchema;
use App\JsonApi\V1\Users\UserSchema;
use App\JsonApi\V1\Versions\VersionSchema;
use LaravelJsonApi\Core\Server\Server as BaseServer;

class Server extends BaseServer
{
    protected string $baseUri = '/api';

    public function serving(): void
    {
        // no-op
    }

    protected function allSchemas(): array
    {
        return [
            AssistantSchema::class,
            CategorySchema::class,
            LanguageSchema::class,
            UserSchema::class,
            TagSchema::class,
            UserPromptSchema::class,
            AiToolSchema::class,
            McpServerSchema::class,
            AiModelSchema::class,
            AiProviderSchema::class,
            ReviewSchema::class,
            VersionSchema::class,
            OrganizationSchema::class,
        ];
    }
}
