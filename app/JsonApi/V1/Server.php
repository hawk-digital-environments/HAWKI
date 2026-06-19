<?php

declare(strict_types=1);

namespace App\JsonApi\V1;

use App\JsonApi\V1\AiModels\AiModelSchema;
use App\JsonApi\V1\AiProviders\AiProviderSchema;
use App\JsonApi\V1\AiToolCapabilities\AiToolCapabilitySchema;
use App\JsonApi\V1\AiTools\AiToolSchema;
use App\JsonApi\V1\Attachments\AttachmentSchema;
use App\JsonApi\V1\Configs\ConfigSchema;
use App\JsonApi\V1\Connections\ConnectionSchema;
use App\JsonApi\V1\ExtApps\ExtAppSchema;
use App\JsonApi\V1\McpServers\McpServerSchema;
use App\JsonApi\V1\Migrations\MigrationSchema;
use App\JsonApi\V1\RoomMember\RoomMemberSchema;
use App\JsonApi\V1\RoomMessages\RoomMessagesSchema;
use App\JsonApi\V1\Rooms\RoomSchema;
use App\JsonApi\V1\SystemModels\SystemModelSchema;
use App\JsonApi\V1\SystemPrompts\SystemPromptSchema;
use App\JsonApi\V1\TranslationLabels\TranslationLabelSchema;
use App\JsonApi\V1\UserKeychainValues\UserKeychainValueSchema;
use App\JsonApi\V1\Users\UserSchema;
use LaravelJsonApi\Core\Server\Server as BaseServer;

class Server extends BaseServer
{

    public const string BASE_URL_PREFIX = '/hawki/v1';

    /**
     * @inheritDoc
     */
    protected function baseUri(): string
    {
        return '/api' . self::BASE_URL_PREFIX;
    }

    public function serving(): void
    {
    }

    protected function allSchemas(): array
    {
        return [
            ConfigSchema::class,
            ConnectionSchema::class,
            ExtAppSchema::class,
            TranslationLabelSchema::class,
            UserSchema::class,
            AiToolSchema::class,
            AiToolCapabilitySchema::class,
            McpServerSchema::class,
            AiModelSchema::class,
            AiProviderSchema::class,
            SystemModelSchema::class,
            SystemPromptSchema::class,
            RoomSchema::class,
            RoomMemberSchema::class,
            RoomMessagesSchema::class,
            AttachmentSchema::class,
            UserKeychainValueSchema::class,
            MigrationSchema::class
        ];
    }
}
