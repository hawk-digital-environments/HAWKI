<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtApp;
use App\Models\ExtAppUser;
use App\Models\ExtAppUserRequest;
use DB;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class AppRemover
{
    public function __construct(
        private LoggerInterface $log
    )
    {
    }
    
    /**
     * Cleans up all data related to the given app.
     *
     * @param ExtApp $app The app whose related data is to be cleaned up.
     * @throws Throwable
     */
    public function remove(ExtApp $app): void
    {
        try {
            DB::transaction(static function () use ($app) {
                $app->users->each(function (ExtAppUser $appUser) {
                    $appUser->personalAccessToken()?->delete();
                    $appUser->delete();
                });
                $app->userRequests()->each(static fn(ExtAppUserRequest $request) => $request->delete());
                $app->appUser?->tokens()->delete();
                $app->appUser?->delete();
                $app->delete();
            });
        } catch (\Throwable $e) {
            $this->log->error(
                'Failed to delete app related data',
                [
                    'appId' => $app->id,
                    'exception' => $e
                ]
            );
            throw $e;
        }
    }
}
