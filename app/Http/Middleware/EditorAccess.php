<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

use App\Services\Chat\Room\Repositories\RoomRepository;
use App\Models\Member;

class EditorAccess
{
    public function __construct(
        private readonly RoomRepository $roomRepository
    )
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        // get the room (only rooms the user is a member of are found)
        $room = $this->roomRepository->findOneBySlugOrFail($request->route('slug'));

        if ($this->roomRepository->isMemberWithRole($room, $user, Member::ROLE_ADMIN, Member::ROLE_EDITOR)) {
            return $next($request);
        }

        // Optionally, you can redirect back or return a response with a 403 error.
        return response()->json(['response' => 'Forbidden'], 403);
    }
}
