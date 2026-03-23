<?php

namespace App\Http\Middleware;

use App\Models\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        $modules = config('modules');

        if (empty($modules)) {
            return $next($request);
        }

        foreach ($modules as $key => $module) {
            if (!empty($module['is_core'])) {
                continue;
            }

            foreach ($module['route_prefixes'] as $prefix) {
                if (Str::is($prefix, $routeName)) {
                    if (!ModuleAccess::isEnabled($key)) {
                        abort(403, 'This module is currently disabled.');
                    }

                    return $next($request);
                }
            }
        }

        return $next($request);
    }
}
