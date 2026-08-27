<?php

namespace App\Services;

use App\Models\JobSite;
use App\Models\ModuleAccess;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

/**
 * Builds every menu in the application from config/permissions.php.
 *
 * The sidebar used to be six hundred lines of hand-written markup with
 * `@admin` and `ModuleAccess::isEnabled()` sprinkled through it, which is
 * exactly how the settings gear ended up being shown to people whose own route
 * would answer 403. Sprinkling `@can` through it the same way would drift the
 * same way, so the menu is generated instead: an entry exists because the
 * catalogue declares it, and it is shown because this class allowed it.
 *
 * An entry survives when all three hold:
 *
 *   1. its route exists,
 *   2. the module it belongs to is switched on for this customer,
 *   3. the person holds its ability — resolved through PermissionResolver,
 *      which means the legacy bridge still applies while a module is unswept.
 *
 * A group whose children are all gone is dropped rather than rendered empty.
 */
class Navigation
{
    public function __construct(protected PermissionResolver $resolver) {}

    /*
    |---------------------------------------------------------------------------
    | The left menu
    |---------------------------------------------------------------------------
    */

    /**
     * The sidebar: a flat, ordered list of items and groups.
     *
     * [
     *   ['type' => 'item',  'key' => 'dashboard', 'name' => …, 'url' => …, 'icon' => …, 'active' => bool],
     *   ['type' => 'group', 'key' => 'company',   'name' => …, 'icon' => …, 'active' => bool, 'items' => [...]],
     * ]
     */
    public function sidebar(?User $user): array
    {
        $entries = $this->visibleEntries($user, header: false);
        $groups = AbilityCatalog::groups();
        $built = [];

        foreach ($entries as $entry) {
            $groupKey = $entry['group'] ?? null;

            if ($groupKey === null) {
                $built[] = ['type' => 'item', 'order' => $entry['order']] + $entry;

                continue;
            }

            if (! isset($built[$groupKey])) {
                $group = $groups[$groupKey] ?? ['name' => $groupKey, 'order' => 999];

                $built[$groupKey] = [
                    'type' => 'group',
                    'key' => $groupKey,
                    'name' => $group['name'],
                    'icon' => $group['icon'] ?? null,
                    'order' => $group['order'] ?? 999,
                    'active' => $this->matchesRoute($group['active'] ?? []),
                    'items' => [],
                ];
            }

            $built[$groupKey]['items'][] = $entry;
        }

        usort($built, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values($built);
    }

    /** The entries that live in the top bar rather than the sidebar. */
    public function header(?User $user): array
    {
        return array_values($this->visibleEntries($user, header: true));
    }

    /**
     * Every menu entry this person may see, in order.
     *
     * @return array<int, array>
     */
    protected function visibleEntries(?User $user, bool $header): array
    {
        $entries = [];

        foreach (config('permissions.menu', []) as $entry) {
            if ((bool) ($entry['header'] ?? false) !== $header) {
                continue;
            }

            if (! $this->allowed($user, $entry['ability'], $entry['route'])) {
                continue;
            }

            $entries[] = [
                'key' => $entry['key'],
                'name' => $entry['name'],
                'group' => $entry['group'] ?? null,
                'order' => $entry['order'] ?? 999,
                'route' => $entry['route'],
                'url' => route($entry['route']),
                'icon' => $entry['icon'] ?? null,
                'ability' => $entry['ability'],
                'active' => $this->matchesRoute($entry['active'] ?? [$entry['route']]),
            ];
        }

        usort($entries, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $entries;
    }

    /*
    |---------------------------------------------------------------------------
    | The project and job-site tabs
    |---------------------------------------------------------------------------
    */

    /**
     * The flat, ordered list of tabs this person may open on a project.
     *
     * This is the source the grouped bar is built from, and it is what the
     * tests pin. Screens render `projectTabBar()`.
     *
     * @return array<int, array>
     */
    public function projectTabs(?User $user, Project $project): array
    {
        return $this->tabs($user, $project, 'project');
    }

    /** @return array<int, array> */
    public function jobSiteTabs(?User $user, JobSite $jobSite): array
    {
        return $this->tabs($user, $jobSite, 'job_site');
    }

    /**
     * The same tabs, arranged into the bar that is actually rendered.
     *
     * @return array<int, array>
     */
    public function projectTabBar(?User $user, Project $project, ?string $active = null): array
    {
        return $this->tabBar($this->projectTabs($user, $project), $active);
    }

    /** @return array<int, array> */
    public function jobSiteTabBar(?User $user, JobSite $jobSite, ?string $active = null): array
    {
        return $this->tabBar($this->jobSiteTabs($user, $jobSite), $active);
    }

    /**
     * Fold a flat tab list into flat entries and dropdown groups.
     *
     * Same shape as `sidebar()`:
     *
     * [
     *   ['type' => 'item',  'key' => 'overview',  'name' => …, 'route' => …, 'icon' => …, 'active' => bool],
     *   ['type' => 'group', 'key' => 'financial', 'name' => …, 'icon' => …, 'active' => bool, 'items' => [...]],
     * ]
     *
     * A group nobody can see is already gone — its tabs never arrived. A group
     * left holding one tab is flattened back into the bar: a dropdown that
     * opens onto a single line is a worse click than the tab itself.
     *
     * @param  array<int, array>  $tabs
     * @return array<int, array>
     */
    protected function tabBar(array $tabs, ?string $active): array
    {
        $groups = config('permissions.tab_groups', []);
        $built = [];

        foreach ($tabs as $tab) {
            $tab['active'] = $active !== null && $tab['key'] === $active;
            $groupKey = $tab['group'] ?? null;

            if ($groupKey === null || ! isset($groups[$groupKey])) {
                $built[] = ['type' => 'item', 'order' => $tab['order']] + $tab;

                continue;
            }

            if (! isset($built[$groupKey])) {
                $group = $groups[$groupKey];

                $built[$groupKey] = [
                    'type' => 'group',
                    'key' => $groupKey,
                    'name' => $this->label("navigation.groups.{$groupKey}", $group['name']),
                    'icon' => $group['icon'] ?? null,
                    'order' => $group['order'] ?? 999,
                    'active' => false,
                    'items' => [],
                ];
            }

            $built[$groupKey]['items'][] = $tab;
            $built[$groupKey]['active'] = $built[$groupKey]['active'] || $tab['active'];
        }

        foreach ($built as $key => $entry) {
            if ($entry['type'] === 'group' && count($entry['items']) === 1) {
                $only = $entry['items'][0];
                $built[$key] = ['type' => 'item', 'order' => $entry['order']] + $only;
            }
        }

        usort($built, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values($built);
    }

    protected function tabs(?User $user, Project|JobSite $scope, string $level): array
    {
        $routeKey = "{$level}_route";
        $orderKey = "{$level}_order";
        $tabs = [];

        foreach (config('permissions.tabs', []) as $tab) {
            $route = $tab[$routeKey] ?? null;

            if (! $route) {
                continue;   // a tab that only exists at the other level
            }

            if (! $this->allowed($user, $tab['ability'], $route, $scope)) {
                continue;
            }

            $tabs[] = [
                'key' => $tab['key'],
                'name' => $this->label("navigation.tabs.{$tab['key']}", $tab['name']),
                'group' => $tab['group'] ?? null,
                'route' => $route,
                'icon' => $tab['icon'] ?? null,
                'ability' => $tab['ability'],
                'order' => $tab[$orderKey] ?? 999,
            ];
        }

        usort($tabs, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $tabs;
    }

    /*
    |---------------------------------------------------------------------------
    | The three conditions
    |---------------------------------------------------------------------------
    */

    /**
     * The label of one tab on its own, for breadcrumbs and page headings.
     *
     * The breadcrumbs used to print `ucwords(str_replace('-', ' ', $active))`,
     * which is why a Brazilian user reading "Ordens de Compra" in the bar got
     * "Purchase Orders" in the breadcrumb above it.
     */
    public function tabLabel(string $key): string
    {
        return $this->label("navigation.tabs.{$key}", ucwords(str_replace('-', ' ', $key)));
    }

    /**
     * A menu label, read from lang/{locale}/navigation.php.
     *
     * The menus keep their wording in their own small file rather than in the
     * global JSON, so renaming a tab is two lines in two readable files. A key
     * that has not been written yet falls back to the English `name` in
     * config/permissions.php, which still goes through the JSON translator —
     * so nothing is ever rendered as a raw `navigation.tabs.…` string.
     */
    protected function label(string $key, string $fallback): string
    {
        return Lang::has($key) ? __($key) : __($fallback);
    }

    protected function allowed(?User $user, string $ability, string $route, mixed $scope = null): bool
    {
        if (! Route::has($route)) {
            return false;
        }

        if (! $this->moduleEnabled($ability)) {
            return false;
        }

        return $this->resolver->allows($user, $ability, $scope);
    }

    protected function moduleEnabled(string $ability): bool
    {
        $module = AbilityCatalog::moduleFor($ability);

        return $module === null || ModuleAccess::isEnabled($module);
    }

    /** @param  array<int, string>  $patterns */
    protected function matchesRoute(array $patterns): bool
    {
        return $patterns !== [] && request()->routeIs(...$patterns);
    }
}
