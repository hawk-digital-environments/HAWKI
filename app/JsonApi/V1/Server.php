<?php

declare(strict_types=1);

namespace App\JsonApi\V1;

use App\JsonApi\V1\AiModelDescriptions\AiModelDescriptionSchema;
use App\JsonApi\V1\AiModelFlags\AiModelFlagSchema;
use App\JsonApi\V1\AiModels\AiModelSchema;
use App\JsonApi\V1\AiProviders\AiProviderSchema;
use App\JsonApi\V1\AiToolCapabilities\AiToolCapabilitySchema;
use App\JsonApi\V1\AiTools\AiToolSchema;
use App\JsonApi\V1\AssistantAvatars\AssistantAvatarSchema;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\AssistantSettings\AssistantSettingSchema;
use App\JsonApi\V1\AssistantSettingValues\AssistantSettingValueSchema;
use App\JsonApi\V1\Attachments\AttachmentSchema;
use App\JsonApi\V1\AssistantCategories\AssistantCategorySchema;
use App\JsonApi\V1\AssistantFeedback\AssistantFeedbackSchema;
use App\JsonApi\V1\AssistantReviews\AssistantReviewSchema;
use App\JsonApi\V1\AssistantTags\AssistantTagSchema;
use App\JsonApi\V1\AssistantUserPrompts\AssistantUserPromptSchema;
use App\JsonApi\V1\AssistantVersions\AssistantVersionSchema;
use App\JsonApi\V1\Configs\ConfigSchema;
use App\JsonApi\V1\Connections\ConnectionSchema;
use App\JsonApi\V1\ExtApps\ExtAppSchema;

use App\JsonApi\V1\McpServers\McpServerSchema;
use App\JsonApi\V1\Migrations\MigrationSchema;
use App\JsonApi\V1\Organizations\OrganizationSchema;

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

    public function serving(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function baseUri(): string
    {
        return '/api' . self::BASE_URL_PREFIX;
    }

    protected function allSchemas(): array
    {
        return [
            AiModelDescriptionSchema::class,
            AiModelFlagSchema::class,
            AiModelSchema::class,
            AiProviderSchema::class,
            AiToolCapabilitySchema::class,
            AiToolSchema::class,
            AttachmentSchema::class,
            ConfigSchema::class,
            ConnectionSchema::class,
            ExtAppSchema::class,
            McpServerSchema::class,
            MigrationSchema::class,
            RoomMemberSchema::class,
            RoomMessagesSchema::class,
            RoomSchema::class,
            SystemModelSchema::class,
            SystemPromptSchema::class,
            TranslationLabelSchema::class,
            UserKeychainValueSchema::class,
            UserSchema::class,
            AssistantSchema::class,
            AssistantAvatarSchema::class,
            AssistantSettingSchema::class,
            AssistantSettingValueSchema::class,
            AssistantCategorySchema::class,
            AssistantFeedbackSchema::class,
            AssistantReviewSchema::class,
            AssistantTagSchema::class,
            AssistantUserPromptSchema::class,
            AssistantVersionSchema::class,
            OrganizationSchema::class,
        ];
    }
}
