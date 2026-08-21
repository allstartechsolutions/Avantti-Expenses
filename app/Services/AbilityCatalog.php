<?php

namespace App\Services;

use Illuminate\Support\Arr;

/**
 * The read side of config/permissions.php.
 *
 * Everything that needs to know what abilities exist — the permission matrix,
 * the resolver, the navigation builder, the seeders — asks this class rather
 * than reading the config array itself, so the shorthand in that file (a bare
 * 'view' versus 'pay' => ['name' => ...]) is normalised in exactly one place.
 *
 * Nothing here touches the database: this is the catalogue, not the grants.
 */
class AbilityCatalog
{
    /** @var array<string, array>|null Normalised areas, built once per request. */
    protected static ?array $areas = null;

    /**
     * Every area, normalised.
     *
     * @return array<string, array{key:string,name:string,module:?string,levels:array,money:bool,swept:bool,nav:?array,actions:array}>
     */
    public static function areas(): array
    {
        if (static::$areas !== null) {
            return static::$areas;
        }

        $areas = [];

        foreach (config('permissions.areas', []) as $key => $area) {
            $areas[$key] = [
                'key' => $key,
                'name' => $area['name'] ?? $key,
                'module' => $area['module'] ?? null,
                'levels' => $area['levels'] ?? ['global'],
                'money' => (bool) ($area['money'] ?? false),
                'swept' => (bool) ($area['swept'] ?? false),
                'nav' => $area['nav'] ?? null,
                'actions' => static::normaliseActions($key, $area['actions'] ?? []),
            ];
        }

        return static::$areas = $areas;
    }

    /**
     * Turn the config shorthand into one shape:
     * ['view' => ['key' => 'view', 'ability' => 'expenses.view', 'name' => 'View',
     *             'sensitive' => false, 'limited' => false]]
     */
    protected static function normaliseActions(string $areaKey, array $actions): array
    {
        $labels = config('permissions.actions', []);
        $normalised = [];

        foreach ($actions as $key => $value) {
            // 'view'  →  key is numeric, value is the action name
            // 'pay' => ['name' => 'Mark as paid']
            $action = is_int($key) ? $value : $key;
            $meta = is_array($value) ? $value : [];

            $normalised[$action] = [
                'key' => $action,
                'ability' => "{$areaKey}.{$action}",
                'name' => $meta['name'] ?? ($labels[$action] ?? ucfirst(str_replace('_', ' ', $action))),
                'sensitive' => (bool) ($meta['sensitive'] ?? false),
                'limited' => (bool) ($meta['limited'] ?? false),
            ];
        }

        return $normalised;
    }

    public static function area(string $key): ?array
    {
        return static::areas()[$key] ?? null;
    }

    /** Every ability that exists, as `area.action`. @return array<int, string> */
    public static function abilities(): array
    {
        $abilities = [];

        foreach (static::areas() as $area) {
            foreach ($area['actions'] as $action) {
                $abilities[] = $action['ability'];
            }
        }

        return $abilities;
    }

    public static function has(string $ability): bool
    {
        [$area, $action] = static::split($ability);

        return isset(static::areas()[$area]['actions'][$action]);
    }

    /** @return array{0: string, 1: string} [area, action] */
    public static function split(string $ability): array
    {
        $parts = explode('.', $ability, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public static function action(string $ability): ?array
    {
        [$area, $action] = static::split($ability);

        return static::areas()[$area]['actions'][$action] ?? null;
    }

    /** "Expenses · Mark as paid" — for the audit trail and the matrix tooltips. */
    public static function label(string $ability): string
    {
        [$areaKey] = static::split($ability);
        $area = static::area($areaKey);
        $action = static::action($ability);

        if (! $area || ! $action) {
            return $ability;
        }

        return __($area['name']).' · '.__($action['name']);
    }

    /** Areas that can be granted at this level: global, project or job_site. */
    public static function areasForLevel(string $level): array
    {
        return array_filter(
            static::areas(),
            fn ($area) => in_array($level, $area['levels'], true),
        );
    }

    public static function isGrantableAt(string $ability, string $level): bool
    {
        [$areaKey] = static::split($ability);

        return static::has($ability)
            && in_array($level, static::area($areaKey)['levels'], true);
    }

    /**
     * THE LEGACY BRIDGE (docs/permissions-module-plan.md §9.1). False until the
     * area has had its permission pass, which is what lets the modules be
     * converted and deployed one at a time.
     */
    public static function isSwept(string $areaOrAbility): bool
    {
        [$areaKey] = static::split($areaOrAbility);

        return (bool) (static::area($areaKey)['swept'] ?? false);
    }

    /** Areas still waiting for their pass — the build's own progress bar. */
    public static function unsweptAreas(): array
    {
        return array_keys(array_filter(static::areas(), fn ($area) => ! $area['swept']));
    }

    /** The module in config/modules.php that must be enabled for this area. */
    public static function moduleFor(string $areaOrAbility): ?string
    {
        [$areaKey] = static::split($areaOrAbility);

        return static::area($areaKey)['module'] ?? null;
    }

    /**
     * Whether this area puts monetary figures on screen.
     *
     * It is a label, not a masking rule. What `can_see_money` actually hides is
     * ROLL-UPS — totals, budgets, margins — and it hides them wherever they are
     * rendered, through `<x-ui.money rollup>`; the amount on a single record is
     * not a secret from somebody allowed to open that record (M4). So an area
     * can be flagged here and have nothing masked on it, which is the case for
     * the catalog: an item's cost is its own field. Who sees the catalog at all
     * is `catalog.view`, and since F0 that can be taken off one person.
     */
    public static function showsMoney(string $areaOrAbility): bool
    {
        [$areaKey] = static::split($areaOrAbility);

        return (bool) (static::area($areaKey)['money'] ?? false);
    }

    public static function isSensitive(string $ability): bool
    {
        return (bool) (static::action($ability)['sensitive'] ?? false);
    }

    /** Actions capped by a membership's approval_limit. */
    public static function isLimited(string $ability): bool
    {
        return (bool) (static::action($ability)['limited'] ?? false);
    }

    /** The company-wide money-visibility ability, from the config. */
    public static function financeAbility(): string
    {
        return config('permissions.finance_ability', 'finance.view_amounts');
    }

    /** Sidebar groups in their configured order. */
    public static function groups(): array
    {
        $groups = config('permissions.groups', []);

        uasort($groups, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $groups;
    }

    /**
     * Drop anything that is not a real ability. Used everywhere a list of
     * abilities arrives from a form, a template or an invitation payload.
     *
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    public static function filter(array $abilities, ?string $level = null): array
    {
        return array_values(array_filter(
            array_unique(Arr::flatten($abilities)),
            fn ($ability) => is_string($ability)
                && static::has($ability)
                && ($level === null || static::isGrantableAt($ability, $level)),
        ));
    }

    /** Every ability of one area, optionally restricted to a level. */
    public static function abilitiesForArea(string $areaKey): array
    {
        return array_column(static::area($areaKey)['actions'] ?? [], 'ability');
    }

    /** Test seam: forget the normalised catalogue. */
    public static function flush(): void
    {
        static::$areas = null;
    }
}
