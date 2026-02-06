<?php

namespace App\Providers;

use App\Services\AI\SyncLog\AiModelHandler;
use App\Services\AI\SyncLog\SystemPromptHandler;
use App\Services\Chat\Room\SyncLog\InvitationHandler;
use App\Services\Chat\Room\SyncLog\MemberHandler;
use App\Services\Chat\Room\SyncLog\RoomAiWritingHandler;
use App\Services\Chat\Room\SyncLog\RoomHandler;
use App\Services\Chat\Room\SyncLog\RoomMessageHandler;
use App\Services\SyncLog\SyncLogTracker;
use App\Services\User\Keychain\SyncLog\UserKeychainValueHandler;
use App\Services\User\SyncLog\UserHandler;
use App\Services\User\SyncLog\UserRemovalHandler;
use Illuminate\Support\ServiceProvider;

class SyncLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            RoomHandler::class,
            UserHandler::class,
            UserRemovalHandler::class,
            MemberHandler::class,
            InvitationHandler::class,
            RoomMessageHandler::class,
            RoomAiWritingHandler::class,
            AiModelHandler::class,
            SystemPromptHandler::class,
            UserKeychainValueHandler::class
        ], 'syncLog.handler');
    }
    
    public function boot(): void
    {
        $this->app->get(SyncLogTracker::class)->registerListeners();
    }
}
