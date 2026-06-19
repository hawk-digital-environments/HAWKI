<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations;


use App\Models\FrontendMigrations\FrontendMigration;
use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use Illuminate\Support\Collection;

class ApplicableMigrationManager
{
    public function __construct(
        private AppliedFrontendMigrationRepository  $appliedMigrationsRepository,
        private FrontendMigrationRepository         $migrationRepository,
        private FrontendMigrationUserdataRepository $migrationUserdataRepository
    )
    {

    }

    public function getMigrationsToApply(): Collection
    {
        $appliedMigrations = $this->appliedMigrationsRepository->findAll()->pluck('migration_id')->toArray();
        $missingMigrations = $this->migrationRepository->findAllWithout($appliedMigrations);
        $migrationData = $this->migrationUserdataRepository->findAll();

        return $missingMigrations->map(function (FrontendMigration $migration) use ($migrationData) {
            $data = null;
            if ($migration->has_userdata) {
                $data = $migrationData->where('migration_id', $migration->id)->pluck('data', 'user_id')->toArray();
            }
            return new Values\MigrationToApply($migration->migration_name, $data);
        });
    }


}
