<?php

namespace App\Services\Chat\Message\Interfaces;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\Message;

interface MessageInterface
{
    public function create(array $data, string $slug);
    public function update(array $data, string $slug);
    public function delete(array $data, string $slug);

    public function assignID(AiConv|Room $room, int $threadID);
}

