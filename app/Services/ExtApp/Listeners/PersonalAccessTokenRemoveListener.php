<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Listeners;


use App\Events\ExtAppUserRemovedEvent;
use App\Events\PersonalAccessTokenRemovedEvent;
use App\Services\ExtApp\Db\AppUserDb;
use Psr\Log\LoggerInterface;
use Throwable;

class PersonalAccessTokenRemoveListener
{
    public function __construct(
        protected AppUserDb       $appUserDb,
        protected LoggerInterface $log
    )
    {
    }
    
    /**
     * @throws Throwable
     */
    public function handle(PersonalAccessTokenRemovedEvent $event): void
    {
        try {
            $appUser = $this->appUserDb->findByToken($event->token);
            if ($appUser !== null) {
                ExtAppUserRemovedEvent::dispatch($appUser);
                $appUser->delete();
            }
        } catch (\Throwable $e) {
            $this->log->error(
                'Failed to delete AppUser for PersonalAccessToken',
                ['exception' => $e]
            );
            throw $e;
        }
    }
}
