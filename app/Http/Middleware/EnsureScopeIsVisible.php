<?php

namespace App\Http\Middleware;

use App\Contracts\PermissionScope;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "May you open this project at all?"
 *
 * Runs on every route that carries a project or a job site — forty-eight of
 * them today — rather than being repeated on each one, so a route added later
 * is guarded the moment it is written. It is the shell check and nothing more:
 * whether the person may open *this* project. What they may do once inside is
 * each module's own pass.
 *
 * Somebody company-wide passes on their role's `project.view`. Somebody
 * confined passes only where they hold a membership.
 */
class EnsureScopeIsVisible
{
    public function __construct(protected PermissionResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Until the project area has had its pass the resolver defers to the
        // old rules anyway; checking here would only add a second answer to
        // the same question.
        if (! AbilityCatalog::isSwept('project')) {
            return $next($request);
        }

        $scope = $this->scopeFrom($request);

        if ($scope === null || ! $request->user()) {
            return $next($request);
        }

        abort_unless(
            $this->resolver->allows($request->user(), 'project.view', $scope),
            403,
            __('You do not have access to this project.'),
        );

        return $next($request);
    }

    /**
     * The project or job site this request is about, if any. Route model
     * binding has usually resolved it already.
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
