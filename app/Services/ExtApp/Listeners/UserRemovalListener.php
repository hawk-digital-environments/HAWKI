<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Listeners;


use App\Events\ExtAppUserRemovedEvent;
use App\Events\UserRemovedEvent;
use App\Services\ExtApp\Db\AppUserDb;
use Psr\Log\LoggerInterface;

class UserRemovalListener
{
    public function __construct(
        protected AppUserDb       $appUserDb,
        protected LoggerInterface $log
    )
    {
    }
    
    public function handle(UserRemovedEvent $event): void
    {
        foreach ($this->appUserDb->findByUserId($event->user->id) as $appUser) {
            try {
                ExtAppUserRemovedEvent::dispatch($appUser);
                $appUser->delete();
            } catch (\Throwable $e) {
                $this->log->error(
                    'Failed to delete AppUser %s for User %s',
                    [$appUser->id, $event->user->id, 'exception' => $e]
                );
                throw $e;
            }
        }
    }
}
