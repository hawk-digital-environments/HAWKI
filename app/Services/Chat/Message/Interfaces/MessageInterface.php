<?php

namespace App\Services\Chat\Message\Interfaces;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\Message;

interface MessageInterface
{
    public function create(AiConv|Room $room, array $data): AiConvMsg|Message;

    public function update(AiConv|Room $room, array $data): AiConvMsg|Message;

    public function delete(AiConv|Room $room, array $data): bool;

    public function assignID(AiConv|Room $room, int $threadId): string;
}

