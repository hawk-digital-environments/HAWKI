<?php
declare(strict_types=1);


namespace App\Services\Auth\Contract;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An addon to the {@see AuthServiceInterface} that indicates the service supports logout redirection.
 * This allows the system to redirect users to a specific URL after they log out.
 */
interface AuthServiceWithLogoutRedirectInterface
{
    /**
     * SHOULD return the redirect response to be used after a user logs out.
     * If the service, for some reason is unable to provide a logout redirect, it MAY return null;
     * which will lead to the default behavior.
     * @param Request $request
     * @return RedirectResponse|null
     */
    public function getLogoutResponse(Request $request): ?RedirectResponse;
}
