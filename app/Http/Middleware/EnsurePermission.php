<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Section-level RBAC for the /manage back-office. Derives the section from the route name
 * (manage.<section>.*) and denies `admin` staff who lack that permission. super_admin & hq bypass.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $name = optional($request->route())->getName();

        if ($user && $name && str_starts_with($name, 'manage.')) {
            $segment = explode('.', substr($name, strlen('manage.')))[0];
            $ability = Permissions::ROUTE_MAP[$segment] ?? null;

            if ($ability && ! $user->hasAccess($ability)) {
                abort(403, 'You do not have access to this section.');
            }
        }

        return $next($request);
    }
}
