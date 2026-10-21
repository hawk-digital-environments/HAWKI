<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserKeychainUpdateValuesRequest;
use App\Models\User;
use App\Models\UserKeychainValue;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use App\Services\Users\Keychain\Repositories\UserKeychainRepository;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class UserKeychainValueController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;

    #[Authorize('view-any', UserKeychainValue::class)]
    public function getPasskeyValidator(
        #[CurrentUser]
        ?User                               $user,
        UserKeychainRepository              $repository,
        FrontendMigrationUserdataRepository $migrationDataRepository
    ): JsonResponse
    {
        $publicKey = $repository->findFirstPublicKeyOfUser($user);
        if ($publicKey) {
            $validatingValue = $publicKey->value;
        } else {
            // What the heck!? Well, we need some kind of validating value to verify the passkey.
            // When the user is currently in the process of migrating upwards, we have no public key in the new system,
            // but the old data has already been removed. So we fetch the old keychain data from the migration userdata.
            // This userdata is technically not meant to be used for this, but it is the only way to test the passkey against something.
            $migrationData = $migrationDataRepository->findOneForMigrationAndUser(
                '2026_06_07_215609_after_passkey_upgrade_to_user_keychain_values',
                $user
            );
            if (!$migrationData) {
                abort(404, 'No passkey validator found for user');
            }
            $validatingValue = $migrationData->data['blob'] ?? null;
            if (!$validatingValue) {
                abort(404, 'No passkey validator found for user');
            }
        }

        return response()->json([
            'validator' => $validatingValue
        ]);
    }

    #[Authorize('update-batch', UserKeychainValue::class)]
    public function batchUpdate(
        UserKeychainUpdateValuesRequest $request,
        UserKeychainRepository          $repository,
    ): DataResponse
    {
        if ($request->isCleaning()) {
            $repository->dropAllForUser($request->user());
        }

        if ($request->hasNewPublicKey()) {
            $request->user()->update([
                'publicKey' => $request->getNewPublicKey()
            ]);
        }

        if ($request->hasSetList()) {
            $repository->setValues(
                $request->user(),
                ...$request->getSetList()
            );
        }

        if ($request->hasRemoveList()) {
            $repository->removeValues(
                $request->user(),
                ...$request->getRemoveList()
            );
        }

        return new DataResponse($repository->findAllOfUser($request->user()));
    }
}
