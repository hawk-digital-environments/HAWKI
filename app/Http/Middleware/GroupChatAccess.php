<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GroupChatAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/login');
        }

        // Check if group chat is globally enabled
        if (! config('app.groupchat_active', false)) {
            abort(403, 'Group chat functionality is currently disabled. Please contact the administration for more information.');
        }

        // Check if user has groupchat access permission
        if (! $user->hasAccess('groupchat.access')) {
            abort(403, 'Access denied. You do not have permission to access group chat functionality.');
        }

        return $next($request);
    }
}
