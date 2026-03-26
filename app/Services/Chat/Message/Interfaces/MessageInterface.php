<?php

namespace App\Services\Chat\Message\Interfaces;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;

interface MessageInterface
{
    public function create(AiConv|Room $conv, array $data, User $user): AiConvMsg|Message;

    public function update(AiConv|Room $conv, array $data): AiConvMsg|Message;

    public function delete(AiConv|Room $conv, array $data): bool;

    public function assignID(AiConv|Room $conv, int $threadId): string;
}
