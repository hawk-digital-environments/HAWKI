<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLocaleRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\Translation\Exception\SettingUnavailableLocaleException;
use App\Services\Translation\LocaleService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
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
        User $user
    ): Responsable
    {
        return new DataResponse($user);
    }

    /**
     * Replaces the current user's profile avatar and returns the new stored-file
     * identifier (for `connection.userinfo.avatar` style usage) plus its URL.
     */
    #[Authorize('view', User::class)]
    public function uploadAvatar(
        UploadAvatarRequest $request,
        #[CurrentUser]
        User                $user,
        ProfileService      $profileService
    ): JsonResponse
    {
        $url = $profileService->assignAvatar($request->validated('image'));
        $identifier = StoredFileIdentifier::tryFromUserAvatar($user);

        return response()->json([
            'avatar' => $identifier,
            'url' => $url,
        ]);
    }

    /**
     * Deletes all server-side data of the current user (rooms, conversations,
     * keychain, tokens, backups) and sends them back to registration.
     */
    #[Authorize('view', User::class)]
    public function resetProfile(ProfileService $profileService): JsonResponse
    {
        $profileService->resetProfile();

        return response()->json([
            'redirectUri' => '/register',
        ]);
    }

    /**
     * Persists the current user's preferred locale while retaining the session
     * and cookie fallbacks used by unauthenticated and legacy flows.
     */
    #[Authorize('view', User::class)]
    public function storeLocale(StoreLocaleRequest $request, LocaleService $localeService): JsonResponse
    {
        try {
            $localeService->setCurrentLocale($request->validated('locale'), true);
        } catch (SettingUnavailableLocaleException) {
            abort(422, 'The requested locale is not available.');
        }

        return response()->json([
            'locale' => $localeService->getCurrentLocale()->lang,
        ]);
    }
}
