<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AppAccessMiddleware
{
    public const APP_TOKEN_SCOPE = 'externalAccess:app';

    /**
     * The endpoint requires an app user.
     * MAY be used for web authentication or API access.
     */
    public const TYPE_ENFORCED = 'enforced';

    /**
     * The endpoint does not allow app users.
     * MAY be used for web authentication or API access.
     */
    public const TYPE_DECLINED = 'declined';

    /**
     * The endpoint requires an app user with a valid token.
     * The token must have the 'externalAccess:app' scope.
     * This is typically used for API access where the app user must authenticate with a token.
     */
    public const TYPE_ENFORCED_TOKEN = 'enforcedToken';

    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (!$request->user()) {
            if ($type === self::TYPE_DECLINED) {
                return $next($request); // Allow access if app users are not required
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Please log in to continue.'
            ], 401);
        }

        $isApp = $request->user()->employeetype === 'app';
        if (($type === self::TYPE_ENFORCED || $type === self::TYPE_ENFORCED_TOKEN) && !$isApp) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint requires an app token.'
            ], 403);
        }

        if ($type === self::TYPE_DECLINED && $isApp) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint does not allow apps.'
            ], 403);
        }

        if (($type === self::TYPE_ENFORCED_TOKEN) && !$request->user()->tokenCan(self::APP_TOKEN_SCOPE)) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint requires an app token.'
            ], 403);
        }

        return $next($request);
    }
}
