<?php

namespace App\Http\Middleware;

use App\Helpers\KeycloakHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Role
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!KeycloakHelper::hasRealmRole([...$roles, 'access_all'])) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
