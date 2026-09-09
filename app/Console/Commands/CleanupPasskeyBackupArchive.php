<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Profile\Repositories\PasskeyBackupArchiveRepository;
use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Console\Command;

class CleanupPasskeyBackupArchive extends Command
{
    protected $signature = 'passkey-backups:cleanup-archive';

    protected $description = 'Remove archived duplicate passkey backups whose retention has expired.';

    public function handle(
        PasskeyBackupArchiveRepository $repository,
        CarbonClockInterface           $clock
    ): int
    {
        // Installations that have not run the archive migration (yet) have no table to clean.
        // Checking beats an every-night stack trace, and it keeps the command free of a config
        // switch that operators would have to know about.
        if (!$repository->hasArchiveTable()) {
            $this->info('No passkey backup archive table present, nothing to clean up.');

            return self::SUCCESS;
        }

        $deleted = $repository->deleteExpired($clock->now());

        $this->info(sprintf(
            '%d expired archived passkey backup(s) deleted, %d remaining.',
            $deleted,
            $repository->countAll()
        ));

        return self::SUCCESS;
    }
}
