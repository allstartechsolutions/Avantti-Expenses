<?php

namespace App\Http\Middleware;

use App\Contracts\PermissionScope;
use App\Models\JobSite;
use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who was on the screen, and where, when it broke.
 *
 * Sentry's own way of answering that is `send_default_pii`, which attaches the
 * IP address, the cookies and the whole request body. This application handles
 * money, client records and vendor details, so that flag stays off (see
 * config/sentry.php) and the useful half is attached here by hand instead:
 * which user, what they are allowed to see, and which project or job site the
 * request was about. No e-mail address, no request payload, no amounts.
 *
 * Runs last in the `web` group so routing and authentication have both
 * finished — the route parameters are bound and the session user is resolved.
 * Does nothing at all when the install has no DSN.
 */
class AttachSentryContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (empty(config('sentry.dsn'))) {
            return $next($request);
        }

        Integration::configureScope(function (Scope $scope) use ($request): void {
            $this->describeUser($scope, $request);
            $this->describeScope($scope, $request);

            $scope->setTag('app.locale', app()->getLocale());
        });

        return $next($request);
    }

    /**
     * Name rather than e-mail: enough for support to ring the right person,
     * without an address sitting in a third-party system. The two tags say
     * what this person could see, which is usually the first question asked
     * of a permissions bug.
     */
    protected function describeUser(Scope $scope, Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        $scope->setUser(UserDataBag::createFromArray([
            'id' => $user->id,
            'username' => $user->name,
        ]));

        $scope->setTag('user.role', $user->role?->name ?? 'none');
        $scope->setTag('user.access_scope', $user->effectiveAccessScope()->value);
        $scope->setTag('user.guest', $user->is_guest ? 'yes' : 'no');
    }

    /**
     * The project or job site the request is about, and — for a job site —
     * the project above it, so errors can be grouped either way.
     */
    protected function describeScope(Scope $scope, Request $request): void
    {
        $subject = $this->scopeFrom($request);

        if (! $subject) {
            return;
        }

        $scope->setTag("{$subject->scopeLevel()}.id", (string) $subject->getKey());
        $scope->setContext($subject->scopeLevel(), [
            'id' => $subject->getKey(),
            'name' => $subject->scopeLabel(),
        ]);

        $parent = $subject->parentScope();

        if ($parent instanceof PermissionScope) {
            $scope->setTag("{$parent->scopeLevel()}.id", (string) $parent->getKey());
        }
    }

    /**
     * Mirrors EnsureScopeIsVisible: route model binding has usually resolved
     * the scope already, and where it has not the id is looked up once.
     */
    protected function scopeFrom(Request $request): ?PermissionScope
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        foreach (['jobSite', 'job_site', 'jobsite', 'project'] as $parameter) {
            $value = $route->parameter($parameter);

            if ($value instanceof PermissionScope) {
                return $value;
            }

            if ($value !== null) {
                $model = $parameter === 'project' ? Project::find($value) : JobSite::find($value);

                if ($model) {
                    return $model;
                }
            }
        }

        return null;
    }
}
