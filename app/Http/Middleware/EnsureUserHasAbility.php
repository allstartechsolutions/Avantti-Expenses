<?php

namespace App\Http\Middleware;

use App\Models\JobSite;
use App\Models\Project;
use App\Services\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard: `->middleware('ability:expenses.view')` for a company-wide
 * screen, `->middleware('ability:expenses.view,project')` for a scoped one,
 * where the second argument names the route parameter holding the project or
 * job site.
 *
 * Belt and braces with the component's own authorizeAbility() call — the
 * middleware stops the page loading, the component stops the actions.
 */
class EnsureUserHasAbility
{
    public function __construct(protected PermissionResolver $resolver) {}

    public function handle(Request $request, Closure $next, string $ability, ?string $scopeParameter = null): Response
    {
        $scope = $scopeParameter ? $this->scopeFrom($request, $scopeParameter) : null;

        abort_unless(
            $this->resolver->allows($request->user(), $ability, $scope),
            403,
            __('You do not have permission to open that page.'),
        );

        return $next($request);
    }

    /**
     * Route model binding usually hands us the model already; a bare id is
     * resolved here so the middleware works either way.
     */
    protected function scopeFrom(Request $request, string $parameter): Project|JobSite|null
    {
        $value = $request->route($parameter);

        if ($value instanceof Project || $value instanceof JobSite) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        return match ($parameter) {
            'project' => Project::find($value),
            'jobSite', 'job_site', 'jobsite' => JobSite::find($value),
            default => null,
        };
    }
}
