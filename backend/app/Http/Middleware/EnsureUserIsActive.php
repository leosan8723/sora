<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests authenticated with a token belonging to a deactivated
 * account. Deactivation must take effect immediately, even for routes that
 * do not carry the role middleware.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_active === false) {
            abort(403, 'This account is deactivated.');
        }

        return $next($request);
    }
}
