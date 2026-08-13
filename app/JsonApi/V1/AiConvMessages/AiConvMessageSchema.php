<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiConvMessages;

use App\Models\AiConvMsg;
use App\Services\Encryption\EncryptionUtils;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Read-only resource for the messages of a private AI conversation.
 *
 * It is serialized when a conversation is fetched with `?include=messages`
 * and as the response of the message actions on the ai-convs resource; the
 * messages themselves are written through those actions, never through this
 * resource directly.
 */
class AiConvMessageSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     */
    public static string $model = AiConvMsg::class;

    /**
     * Get the resource fields.
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('message_id'),
            Str::make('message_role'),
            Str::make('model'),
            Boolean::make('completion'),
            ArrayHash::make('metadata'),
            // The encrypted message body in the portable "iv:tag:ciphertext"
            // string format used by the room-messages resource as well.
            Str::make('content')->extractUsing(static function (AiConvMsg $message) {
                return (string)EncryptionUtils::symmetricCryptoValueFromStrings(
                    $message->iv,
                    $message->tag,
                    $message->content
                );
            }),
            ArrayHash::make('author')->extractUsing(static function (AiConvMsg $message) {
                $user = $message->user;
                return [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => app(AvatarStorageService::class)
                        ->retrieve(StoredFileIdentifier::tryFromUserAvatar($user))?->getUrl(),
                ];
            }),
            // Attachment metadata is embedded because it is always displayed
            // together with the message; the file itself is served through the
            // storage proxy using the identifier.
            ArrayList::make('attachments')->extractUsing(static function (AiConvMsg $message) {
                return $message->attachments->map(static fn($attachment) => [
                    'uuid' => $attachment->uuid,
                    'name' => $attachment->name,
                    'category' => $attachment->category,
                    'type' => $attachment->type,
                    'mime' => $attachment->mime,
                    'identifier' => (string)StoredFileIdentifier::fromAttachment($attachment),
                ])->values()->all();
            }),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
        ];
    }

    /**
     * Get the resource filters.
     */
    public function filters(): array
    {
        return [];
    }
}
