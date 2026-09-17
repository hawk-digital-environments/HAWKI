<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RejectDisabledAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_if($user instanceof \App\Models\User && $user->admin_disabled, 403, __('admin.account_disabled'));
        return $next($request);
    }
}
