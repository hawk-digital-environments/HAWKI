<?php

namespace App\Services\Chat\Room;

use App\Services\Chat\Room\Traits\RoomFunctions;
use App\Services\Chat\Room\Traits\RoomMembers;
use App\Services\Chat\Room\Traits\RoomMessages;

use App\Services\Storage\AvatarStorageService;
use App\Services\Chat\Message\MessageHandlerFactory;

class RoomService{

    use RoomFunctions;
    use RoomMembers;
    use RoomMessages;


    protected $avatarStorage;
    protected $messageHandler;
    public function __construct(

    ) {
        $this->avatarStorage = app(AvatarStorageService::class);
        $this->messageHandler = MessageHandlerFactory::create('group');
    }
}
