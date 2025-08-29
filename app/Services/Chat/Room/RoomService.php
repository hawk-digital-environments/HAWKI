<?php

namespace App\Services\Chat\Room;

use App\Services\Chat\Room\Traits\RoomFunctions;
use App\Services\Chat\Room\Traits\RoomMembers;
use App\Services\Chat\Room\Traits\RoomMessages;

use App\Services\Storage\AvatarStorageService;

class RoomService{

    use RoomFunctions;
    use RoomMembers;
    use RoomMessages;


    protected $avatarStorage;
    public function __construct() {
        $this->avatarStorage = app(AvatarStorageService::class);
    }
}
