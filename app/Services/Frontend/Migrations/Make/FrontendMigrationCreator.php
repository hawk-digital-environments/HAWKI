<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use App\Services\System\Time\Clock;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

readonly class FrontendMigrationCreator
{
    public const string RUN_TYPE_AFTER_LOGIN = 'after_login';
    public const string RUN_TYPE_AFTER_PASSKEY = 'after_passkey';

    public function __construct(
        private BackendMigrationCreator $phpMigrationCreator,
        private JsMigrationCreator      $jsMigrationCreator,
        private ClockInterface          $clock = new Clock()
    )
    {
    }

    public function create(string $name, string $runType): array
    {
        $migrationName = $this->clock->now()->format('Y_m_d_His') . '_' . Str::snake($runType) . '_' . Str::snake($name);

        $phpMigrationPath = $this->phpMigrationCreator->create($migrationName, database_path('migrations'));
        $jsMigrationPath = $this->jsMigrationCreator->create(
            $migrationName,
            resource_path('js/migrations/' . Str::snake($runType))
        );

        return [$phpMigrationPath, $jsMigrationPath];
    }
}
