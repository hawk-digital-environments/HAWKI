<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class UsersController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\Update;

    #[Authorize('view', User::class)]
    public function handleMe(
        #[CurrentUser]
        ?User $user
    ): Responsable
    {
        if (!$user) {
            abort(401, 'Can only fetch the current user for authenticated users');
        }

        return new DataResponse($user);
    }

    /**
     * Replaces the current user's profile avatar and returns the new stored-file
     * identifier (for `connection.userinfo.avatar` style usage) plus its URL.
     */
    public function uploadAvatar(
        Request              $request,
        #[CurrentUser]
        ?User                $user,
        ProfileService       $profileService,
        AvatarStorageService $avatarStorage
    ): JsonResponse
    {
        if (!$user) {
            abort(401, 'Only authenticated users can upload an avatar');
        }

        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'max:' . max(1, intdiv($avatarStorage->getMaxFileSize(), 1024)),
                'mimetypes:' . implode(',', $avatarStorage->getAllowedMimeTypes()),
            ],
        ]);

        $url = $profileService->assignAvatar($validated['image']);
        $identifier = StoredFileIdentifier::tryFromUserAvatar($user);

        return response()->json([
            'avatar' => $identifier ? (string)$identifier : null,
            'url' => $url,
        ]);
    }

    /**
     * Deletes all server-side data of the current user (rooms, conversations,
     * keychain, tokens, backups) and sends them back to registration.
     */
    public function resetProfile(
        #[CurrentUser]
        ?User          $user,
        ProfileService $profileService
    ): JsonResponse
    {
        if (!$user) {
            abort(401, 'Only authenticated users can reset their profile');
        }

        $profileService->resetProfile();

        return response()->json([
            'redirectUri' => '/register',
        ]);
    }
}
