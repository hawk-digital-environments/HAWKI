<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations;

use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Connection;

#[Singleton]
readonly class FrontendMigrationBuilder
{
    public function __construct(
        private Connection                          $connection,
        private UserRepository                      $userRepository,
        private FrontendMigrationRepository         $repository,
        private FrontendMigrationUserdataRepository $userdataRepository,
    )
    {
    }

    public function register(
        string        $migrationName,
        \Closure|null $userDataFinder = null
    ): void
    {
        $migrationName = $this->getRealMigrationName($migrationName);
        $this->connection->transaction(function () use ($migrationName, $userDataFinder) {
            $migration = $this->repository->insert(
                $migrationName,
                (bool)$userDataFinder
            );

            if ($userDataFinder) {
                $this->userdataRepository->dropAllForMigration($migration);
                foreach ($this->userRepository->findAll() as $user) {
                    $data = $userDataFinder($user, $this->connection);

                    if (empty($data)) {
                        continue;
                    }

                    if (!is_array($data)) {
                        // @todo exception
                        throw new \RuntimeException(sprintf(
                            'User data finder closure for migration "%s" must return an array or null/false.',
                            $migrationName
                        ));
                    }

                    $this->userdataRepository->insert($user, $migration, $data);
                }
            }
        });
    }

    private function getRealMigrationName(string $migrationName): string
    {
        return pathinfo($migrationName, PATHINFO_FILENAME);
    }
}
