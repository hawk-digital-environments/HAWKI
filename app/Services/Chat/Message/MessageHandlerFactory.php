<?php

namespace App\Services\Chat\Message;

use App\Services\Chat\Message\Interfaces\MessageInterface;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;

class MessageHandlerFactory{

    public static function create(string $type): ?MessageInterface
    {
        return match ($type) {
            'private' => app(PrivateMessageHandler::class),
            'group'   => app(GroupMessageHandler::class),
            default   => null,
        };
    }

}
