<?php

namespace App\JsonApi\V1\RoomMessages;

use App\Models\Message;
use App\Services\Encryption\EncryptionUtils;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class RoomMessagesSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Message::class;

    protected ?array $defaultPagination = ['size' => 100];

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Number::make('room_id'),
            Number::make('member_id'),
            Boolean::make('has_thread'),
            Number::make('thread_id'),
            // In the frontend it is much more efficient to have an array of user IDs who have read the message
            // instead of the member ids, because like this we can efficiently check if the current user has read the message,
            // and we can also easily show the avatars of the users who have read the message.
            ArrayList::make('read_by')->extractUsing(function (Message $message) {
                $readByMembers = json_decode($message->reader_signs, true, 512, JSON_THROW_ON_ERROR);
                if ($readByMembers === null) {
                    return [];
                }
                $roomMembers = $message->room?->members->keyBy('id');
                if ($roomMembers === null) {
                    return [];
                }
                $userIds = [];
                foreach ($readByMembers as $memberId) {
                    $member = $roomMembers->get($memberId);
                    if ($member !== null) {
                        $userIds[] = $member->user_id;
                    }
                }
                return $userIds;
            }),
            Str::make('model'),
            Str::make('content')->extractUsing(function (Message $message) {
                return EncryptionUtils::symmetricCryptoValueFromStrings(
                    $message->iv,
                    $message->tag,
                    $message->content
                );
            }),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            HasOne::make('user')->readOnly(),
            HasOne::make('member')->type('room-members')->readOnly(),
            HasMany::make('attachments')->type('attachments')->readOnly()
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
            // WhereIdIn::make($this)
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
