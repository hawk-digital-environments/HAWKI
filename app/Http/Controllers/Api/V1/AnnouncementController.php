<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MarkAnnouncementRequest;
use App\Models\User;
use App\Services\Announcements\Repositories\UserAnnouncementRepository;
use App\Services\Announcements\Values\AnnouncementForUser;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AnnouncementController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;

    #[Authorize('markSeen', AnnouncementForUser::class)]
    public function markSeen(
        #[CurrentUser]
        ?User                      $user,
        MarkAnnouncementRequest    $request,
        UserAnnouncementRepository $repository
    )
    {
        $announcement = $repository->markSeen($user, $request->getAnnouncementId());
        if (!$announcement) {
            abort(404, 'Announcement not found');
        }

        return new DataResponse($announcement);
    }

    #[Authorize('markAccepted', AnnouncementForUser::class)]
    public function markAccepted(
        #[CurrentUser]
        ?User                      $user,
        MarkAnnouncementRequest    $request,
        UserAnnouncementRepository $repository
    )
    {
        $announcement = $repository->markAccepted($user, $request->getAnnouncementId());
        if (!$announcement) {
            abort(404, 'Announcement not found');
        }

        return new DataResponse($announcement);
    }
}
