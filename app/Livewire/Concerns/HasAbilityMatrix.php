<?php

namespace App\Livewire\Concerns;

use App\Services\AbilityCatalog;

/**
 * The ability matrix, shared by every screen that hands abilities out — the
 * role editor, the template editor, and the member editor on the Team tab.
 *
 * The state is nested as [area => [action => true]] rather than keyed by
 * "area.action", because `wire:model="granted.expenses.create"` binds a *path*:
 * Livewire reads the dots as nesting, so a flat key of that name would never be
 * written. It is flattened back into abilities — through the catalogue, so a
 * checkbox name invented in the browser cannot become a grant — on the way out.
 */
trait HasAbilityMatrix
{
    /** [area => [action => true]] */
    public array $granted = [];

    /** Narrows the matrix; 138 abilities is a lot to scroll. */
    public string $matrixSearch = '';

    /**
     * The catalogue arranged the way the screen reads it.
     *
     * @param  string|null  $level  'project' or 'job_site' for a membership or a
     *                              template — only what can be held there, in the
     *                              order that level's own tab bar uses, so the
     *                              matrix reads like the screens the person will
     *                              actually see. Null is the role editor, which is
     *                              company-wide and shows everything.
     */
    protected function buildMatrix(?string $level = null): array
    {
        return $level === null
            ? $this->companyWideMatrix()
            : $this->scopedMatrix($level);
    }

    /**
     * The role editor: everything, split into the company-wide screens and the
     * things that live on a project.
     */
    protected function companyWideMatrix(): array
    {
        $sections = [
            'global' => [
                'key' => 'global',
                'name' => __('Company-wide screens'),
                'hint' => __('The left menu and everything behind it.'),
                'areas' => [],
            ],
            'scoped' => [
                'key' => 'scoped',
                'name' => __('Projects and job sites'),
                'hint' => __('Granted here, these apply on every project. Granting them to one project only is done on that project\'s Team tab.'),
                'areas' => [],
            ],
        ];

        foreach (AbilityCatalog::areas() as $key => $area) {
            if (! $this->matchesMatrixSearch($key, $area)) {
                continue;
            }

            $scoped = in_array('project', $area['levels'], true)
                || in_array('job_site', $area['levels'], true);

            $sections[$scoped ? 'scoped' : 'global']['areas'][] = $this->matrixArea($key, $area);
        }

        return array_values($sections);
    }

    /**
     * A project or job-site editor: only what can be held there, and **only
     * the modules that level actually has**. A project has no Users screen and
     * no Estimates, so neither is offered; the tabs it does have are listed in
     * the order they appear on the tab bar.
     *
     * Anything scoped to the level but not shown as a tab there — the minutes
     * covering a project, say — is kept in a second section that says so,
     * rather than sitting unexplained among the tabs.
     */
    protected function scopedMatrix(string $level): array
    {
        $tabOrder = $this->tabOrderFor($level);

        $tabbed = [
            'key' => 'tabs',
            'name' => $level === 'job_site'
                ? __('What they may do on this job site')
                : __('What they may do on this project'),
            'hint' => $level === 'job_site'
                ? __('One row per tab of this job site. Anything not ticked is not on their menu at all.')
                : __('One row per tab of this project. Anything not ticked is not on their menu at all — and it covers every job site under the project unless that site gives them something of its own.'),
            'areas' => [],
        ];

        $related = [
            'key' => 'related',
            'name' => __('Related screens'),
            'hint' => $level === 'job_site'
                ? __('Not tabs on a job site, but what this person sees there is decided here.')
                : __('Not tabs on a project, but what this person sees there is decided here.'),
            'areas' => [],
        ];

        foreach (AbilityCatalog::areasForLevel($level) as $key => $area) {
            if (! $this->matchesMatrixSearch($key, $area)) {
                continue;
            }

            $row = $this->matrixArea($key, $area);

            if (isset($tabOrder[$key])) {
                $row['order'] = $tabOrder[$key];
                $tabbed['areas'][] = $row;
            } else {
                $related['areas'][] = $row;
            }
        }

        usort($tabbed['areas'], fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values(array_filter(
            [$tabbed, $related],
            fn ($section) => $section['areas'] !== [] || $this->matrixSearch !== '',
        ));
    }

    /**
     * area key => its position on that level's tab bar.
     *
     * @return array<string, int>
     */
    protected function tabOrderFor(string $level): array
    {
        $routeKey = "{$level}_route";
        $orderKey = "{$level}_order";
        $order = [];

        foreach (config('permissions.tabs', []) as $tab) {
            if (! ($tab[$routeKey] ?? null)) {
                continue;
            }

            [$area] = AbilityCatalog::split($tab['ability']);

            // Two tabs can share an area (Overview and Job Sites are both the
            // project area); the first one wins its place in the order.
            $order[$area] ??= $tab[$orderKey] ?? 999;
        }

        return $order;
    }

    protected function matchesMatrixSearch(string $key, array $area): bool
    {
        $needle = trim(mb_strtolower($this->matrixSearch));

        return $needle === ''
            || str_contains(mb_strtolower(__($area['name']).' '.$key), $needle);
    }

    protected function matrixArea(string $key, array $area): array
    {
        return [
            'key' => $key,
            'name' => __($area['name']),
            'module' => $area['module'],
            'money' => $area['money'],
            'enforced' => $area['swept'],
            'order' => 999,
            'actions' => array_values($area['actions']),
        ];
    }

    public function toggleArea(string $areaKey, bool $on): void
    {
        foreach (AbilityCatalog::abilitiesForArea($areaKey) as $ability) {
            $this->setGrant($ability, $on);
        }
    }

    public function toggleSection(string $sectionKey, bool $on): void
    {
        foreach ($this->matrix as $section) {
            if ($section['key'] !== $sectionKey) {
                continue;
            }

            foreach ($section['areas'] as $area) {
                $this->toggleArea($area['key'], $on);
            }
        }
    }

    protected function setGrant(string $ability, bool $on): void
    {
        [$area, $action] = AbilityCatalog::split($ability);

        if ($on) {
            $this->granted[$area][$action] = true;

            return;
        }

        unset($this->granted[$area][$action]);
    }

    /**
     * The editor's state as a flat list of real abilities.
     *
     * @return array<int, string>
     */
    protected function grantedAbilities(?string $level = null): array
    {
        $abilities = [];

        foreach ($this->granted as $area => $actions) {
            if (! is_array($actions)) {
                continue;
            }

            foreach ($actions as $action => $on) {
                if ($on) {
                    $abilities[] = "{$area}.{$action}";
                }
            }
        }

        return AbilityCatalog::filter($abilities, $level);
    }

    /** Load an existing ability list into the editor. */
    protected function loadGrants(array $abilities): void
    {
        $this->granted = [];

        foreach ($abilities as $ability) {
            [$area, $action] = AbilityCatalog::split($ability);
            $this->granted[$area][$action] = true;
        }
    }
}
