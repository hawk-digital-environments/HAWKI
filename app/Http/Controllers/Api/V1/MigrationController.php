<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyFrontendMigrationRequest;
use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\User;
use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use App\Services\Frontend\Migrations\Values\MigrationToApply;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class MigrationController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;

    #[Authorize('applyMigration', FrontendMigration::class)]
    public function markMigrationAsApplied(
        #[CurrentUser]
        ?User                               $user,
        ApplyFrontendMigrationRequest       $request,
        FrontendMigrationRepository         $migrationRepository,
        FrontendMigrationUserdataRepository $userdataRepository,
        AppliedFrontendMigrationRepository  $appliedRepository
    )
    {
        $migration = $migrationRepository->findOneByName($request->getMigrationName());
        if (!$migration) {
            abort(404, 'Migration not found');
        }

        $userData = $userdataRepository->findOneForMigrationAndUser($migration, $user);
        $userData?->delete();

        $appliedRepository->applyForUser($migration, $user);

        return new DataResponse([new MigrationToApply(
            $migration->migration_name,
            $userData->data ?? []
        )]);
    }
}
