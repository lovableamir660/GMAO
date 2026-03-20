<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetSiteContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->current_site_id) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($user->current_site_id);
        }

        return $next($request);
    }
}
