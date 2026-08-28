<?php

namespace Tests\Feature\Permissions;

use App\Services\AbilityCatalog;
use Tests\TestCase;

/**
 * The catalogue is the contract every other part of the permission module is
 * written against, so it is checked for shape rather than for content: a typo
 * in config/permissions.php should fail here and not three modules later.
 */
class AbilityCatalogTest extends TestCase
{
    public function test_every_area_declares_a_module_that_exists(): void
    {
        $modules = array_keys(config('modules'));

        foreach (AbilityCatalog::areas() as $key => $area) {
            $this->assertNotNull($area['module'], "Area '{$key}' declares no module.");
            $this->assertContains(
                $area['module'],
                $modules,
                "Area '{$key}' names module '{$area['module']}', which is not in config/modules.php.",
            );
        }
    }

    public function test_every_area_declares_valid_levels_and_at_least_one_action(): void
    {
        foreach (AbilityCatalog::areas() as $key => $area) {
            $this->assertNotEmpty($area['levels'], "Area '{$key}' declares no levels.");
            $this->assertNotEmpty($area['actions'], "Area '{$key}' declares no actions.");

            foreach ($area['levels'] as $level) {
                $this->assertContains(
                    $level,
                    ['global', 'project', 'job_site'],
                    "Area '{$key}' declares unknown level '{$level}'.",
                );
            }
        }
    }

    public function test_every_action_normalises_to_a_labelled_ability(): void
    {
        foreach (AbilityCatalog::areas() as $key => $area) {
            foreach ($area['actions'] as $action) {
                $this->assertSame("{$key}.{$action['key']}", $action['ability']);
                $this->assertNotEmpty($action['name'], "Ability '{$action['ability']}' has no label.");
                $this->assertTrue(AbilityCatalog::has($action['ability']));
            }
        }
    }

    public function test_every_menu_entry_is_wired_to_something_real(): void
    {
        $groups = config('permissions.groups');
        $keys = [];

        foreach (config('permissions.menu') as $entry) {
            $key = $entry['key'];

            $this->assertNotContains($key, $keys, "Menu entry '{$key}' is declared twice.");
            $keys[] = $key;

            $this->assertTrue(
                AbilityCatalog::has($entry['ability']),
                "Menu entry '{$key}' names ability '{$entry['ability']}', which is not in the catalogue.",
            );

            $this->assertNotNull(
                app('router')->getRoutes()->getByName($entry['route']),
                "Menu entry '{$key}' points at route '{$entry['route']}', which does not exist.",
            );

            $this->assertNotEmpty($entry['icon'], "Menu entry '{$key}' has no icon.");

            if ($entry['group'] !== null) {
                $this->assertArrayHasKey(
                    $entry['group'],
                    $groups,
                    "Menu entry '{$key}' is in group '{$entry['group']}', which is not declared.",
                );
            }
        }
    }

    public function test_every_tab_is_wired_to_something_real(): void
    {
        foreach (config('permissions.tabs') as $tab) {
            $key = $tab['key'];

            $this->assertTrue(
                AbilityCatalog::has($tab['ability']),
                "Tab '{$key}' names ability '{$tab['ability']}', which is not in the catalogue.",
            );

            foreach (['project_route' => 'project', 'job_site_route' => 'job_site'] as $routeKey => $level) {
                if (! $tab[$routeKey]) {
                    continue;
                }

                $this->assertNotNull(
                    app('router')->getRoutes()->getByName($tab[$routeKey]),
                    "Tab '{$key}' points at route '{$tab[$routeKey]}', which does not exist.",
                );

                $this->assertTrue(
                    AbilityCatalog::isGrantableAt($tab['ability'], $level),
                    "Tab '{$key}' is shown at {$level} level, but '{$tab['ability']}' cannot be granted there.",
                );
            }
        }
    }

    public function test_groups_and_top_level_items_share_one_ordering_space(): void
    {
        // Otherwise a group and an item can claim the same slot and the menu
        // order becomes whatever usort felt like.
        $orders = array_column(config('permissions.groups'), 'order');

        foreach (config('permissions.menu') as $entry) {
            if (($entry['group'] ?? null) === null && ! ($entry['header'] ?? false)) {
                $orders[] = $entry['order'];
            }
        }

        $this->assertSame(count($orders), count(array_unique($orders)), 'Two menu entries claim the same order.');
    }

    public function test_abilities_are_unique(): void
    {
        $abilities = AbilityCatalog::abilities();

        $this->assertSame(
            count($abilities),
            count(array_unique($abilities)),
            'The catalogue declares the same ability twice.',
        );
    }

    public function test_filter_drops_unknown_abilities_and_wrong_levels(): void
    {
        $this->assertSame(
            ['expenses.view'],
            AbilityCatalog::filter(['expenses.view', 'nonsense.action', '']),
        );

        // budget is project / job_site only, so it cannot be granted globally.
        $this->assertSame(
            ['clients.view'],
            AbilityCatalog::filter(['clients.view', 'budget.lock'], 'global'),
        );
    }

    public function test_the_catalogue_still_reports_which_areas_are_unswept(): void
    {
        // As of F2 the legacy bridge is deleted. The reporting stays, and is
        // what a module added later leans on: it starts unswept, and the
        // permission matrix marks it "not enforced yet" until its pass is done.
        // AREAS_UNDER_CONSTRUCTION is exactly that set — empty it and this goes
        // back to proving the whole catalogue is enforced.
        $this->assertSame(
            self::AREAS_UNDER_CONSTRUCTION,
            array_values(AbilityCatalog::unsweptAreas()),
        );

        config()->set('permissions.areas.expenses.swept', false);
        AbilityCatalog::flush();

        try {
            // Catalogue order, so expenses comes before the areas still
            // being built further down the file.
            $this->assertSame(
                array_merge(['expenses'], self::AREAS_UNDER_CONSTRUCTION),
                array_values(AbilityCatalog::unsweptAreas()),
            );
            $this->assertFalse(AbilityCatalog::isSwept('expenses'));
            $this->assertFalse(AbilityCatalog::isSwept('expenses.view'));
        } finally {
            config()->set('permissions.areas.expenses.swept', true);
            AbilityCatalog::flush();
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Every declared ability actually does something
    |---------------------------------------------------------------------------
    */

    /**
     * A grant that enforces nothing is a lie told by the permission matrix.
     *
     * `swept` tracks whole areas, and nothing has ever checked an individual
     * action — which is how six abilities came to sit in the catalogue,
     * showing as enforced, doing nothing at all: somebody could hand one out
     * and change no behaviour whatsoever.
     *
     * "Enforced" means the ability string appears somewhere that can refuse a
     * request: `app/`, `routes/`, or a view. A seeder handing it out and a test
     * asserting who holds it do NOT count — those describe who has it, not what
     * it stops.
     *
     * When this fails, the fix is to guard the action or to remove the
     * declaration. Adding a name to the allow-list below is the third option
     * and needs a reason written beside it.
     */
    public function test_every_declared_ability_is_enforced_somewhere(): void
    {
        /*
         * Built from a variable rather than written out, so a literal search
         * cannot see them. Each is enforced in
         * App\Livewire\Concerns\SignsAndDistributes via
         * `$this->areaKey().'.<action>'`, where areaKey() returns 'rfis' on
         * RfiShow and 'approvals' on ApprovalShow.
         */
        $builtDynamically = [
            'rfis.answer', 'rfis.distribute', 'rfis.export',
            'approvals.respond', 'approvals.distribute', 'approvals.export',
        ];

        $source = '';

        foreach (['app', 'routes', 'resources/views'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir))) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $source .= file_get_contents($file->getPathname())."\n";
                }
            }
        }

        $unenforced = [];

        foreach (AbilityCatalog::abilities() as $ability) {
            if (in_array($ability, $builtDynamically, true)) {
                continue;
            }

            if (! str_contains($source, $ability)) {
                $unenforced[] = $ability;
            }
        }

        sort($unenforced);

        $this->assertSame(
            [],
            $unenforced,
            "These abilities are declared in the catalogue and enforced nowhere. Each one shows as a real "
            ."permission on the access screens and does nothing when granted. Guard the action, or remove "
            ."the declaration:\n  - ".implode("\n  - ", $unenforced),
        );
    }

    /**
     * The dynamic list above is a hole in the check, so it is pinned shut.
     *
     * Every entry has to be genuinely built from `areaKey()`; without this a
     * name dropped into that array would silence a real finding.
     */
    public function test_the_dynamic_ability_exemptions_are_really_built_that_way(): void
    {
        $concern = str_replace(["\n", ' '], '', file_get_contents(
            app_path('Livewire/Concerns/SignsAndDistributes.php')
        ));

        // `distribute` and `export` are the same word on both documents, so
        // they are appended straight to the area key.
        foreach (['distribute', 'export'] as $action) {
            $this->assertStringContainsString(
                "areaKey().'.".$action."'",
                $concern,
                "The exemption for `{$action}` claims it is built from areaKey(); it is not.",
            );
        }

        // An RFI is *answered* and an approval is *responded to*, so those two
        // are chosen by a ternary on the same key.
        $this->assertStringContainsString("areaKey().'.'.(", $concern);
        $this->assertStringContainsString("'answer':'respond'", $concern);
    }
}
