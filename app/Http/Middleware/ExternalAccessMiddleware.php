<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalAccessMiddleware
{
    private const MESSAGE_MAP = [
        'default' => 'External communication is not allowed. Please contact the administration for more information.',
        'apps' => 'External apps are not allowed. Please contact the administration for more information.',
        'chat' => 'External chat is not allowed. Please contact the administration for more information.',
    ];

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            if (!config("external_access.{$feature}", false)) {
                return response()->json([
                    'response' => self::MESSAGE_MAP[$feature] ?? self::MESSAGE_MAP['default']
                ], 403);
            }
        }
        return $next($request);
    }
}
