<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Room;
use App\Models\Member;
use Illuminate\Support\Facades\Log;

class EditorAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        // get the room
        $room = Room::where('slug', $request->route('slug'))->firstOrFail();
        // find member model
        $member = $room->members()->where('user_id', $user->id)->first();

        if($member->hasRole('admin') || $member->hasRole('editor')){
            return $next($request);
        }

        // Optionally, you can redirect back or return a response with a 403 error.
        return response()->json(['response' => 'Forbidden'], 403);
    }
}
