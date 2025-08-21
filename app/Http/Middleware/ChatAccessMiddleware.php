<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ChatAccessMiddleware
{
    /**
     * Handle an incoming request.
     * Check if user has access to chat functionality
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $chatType  Either 'chat' for AI chat or 'groupchat' for group chat
     */
    public function handle(Request $request, Closure $next, string $chatType = 'chat'): Response
    {
        // Ensure user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Authentication required'], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Determine which permission to check
        $permission = match ($chatType) {
            'chat' => 'chat.access',
            'groupchat' => 'groupchat.access',
            default => throw new \InvalidArgumentException("Invalid chat type: {$chatType}")
        };

        // Check system settings for groupchat
        if ($chatType === 'groupchat' && !config('app.groupchat_active', true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Feature disabled',
                    'message' => 'Group chat is currently disabled'
                ], 403);
            }
            
            // For web requests, show 403 error page
            abort(403, 'Group chat is currently disabled');
        }

        // Check if user has the required permission
        if (!$user->hasAccess($permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => "No Permission for {$chatType}"
                ], 403);
            }
            
            // For web requests, show 403 error page
            abort(403, "No Permission for {$chatType}");
        }

        return $next($request);
    }
}
