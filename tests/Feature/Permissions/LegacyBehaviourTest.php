<?php

namespace Tests\Feature\Permissions;

use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E2's proof: the permission engine is in place and **nothing has moved**.
 *
 * Every screen the application has, opened by each of the three roles, must
 * answer exactly as it answers today. The table below is that answer, recorded
 * before the engine was wired up.
 *
 * This is also the regression net for the eighteen module passes that follow.
 * A pass that deliberately changes who may open a screen changes one line here,
 * in the same commit, and the change is then visible in review. A pass that
 * changes it by accident fails.
 */
class LegacyBehaviourTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes whose answer is not 200 for all three roles.
     *
     * Anything not listed is expected to open for everybody, which is itself
     * the finding that started this module: most of the application is behind
     * `auth` and nothing else.
     */
    protected const EXPECTED = [
        // --- admin middleware on the route -------------------------------
        'users.index' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'users.create' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'cost-codes.templates.index' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'cost-codes.templates.create' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'system-settings.index' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.sales-tax' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.expenses' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.expenses.pdf.view' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.expenses.pdf.download' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.accounts-payable' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.accounts-payable.pdf.view' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.accounts-payable.pdf.download' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.company-financials' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.company-financials.pdf.view' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.company-financials.pdf.download' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.payment-details' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.payment-details.pdf.view' => ['admin' => 200, 'manager' => 403, 'employee' => 403],
        'reports.payment-details.pdf.download' => ['admin' => 200, 'manager' => 403, 'employee' => 403],

        // --- new in E4: guarded by ability:access.view, which only an
        //     administrator holds until somebody grants it -------------------
        'access.index' => ['admin' => 200, 'manager' => 403, 'employee' => 403],

        // --- authorizeAdmin() in the component's mount() ------------------
        'vendors.duplicates' => ['admin' => 200, 'manager' => 403, 'employee' => 403],

        // --- inline is_admin || is_manager --------------------------------
        'meeting-series.index' => ['admin' => 200, 'manager' => 200, 'employee' => 403],
        'meetings.create' => ['admin' => 200, 'manager' => 200, 'employee' => 403],
        'documentation.create' => ['admin' => 200, 'manager' => 200, 'employee' => 403],

        // --- not permission related ---------------------------------------
        // No file with that id in a fresh database.
        'files.show' => ['admin' => 404, 'manager' => 404, 'employee' => 404],
        'files.download' => ['admin' => 404, 'manager' => 404, 'employee' => 404],
        // Redirects unless two-factor is being set up.
        'two-factor.show' => ['admin' => 302, 'manager' => 302, 'employee' => 302],
    ];

    /**
     * Routes that cannot run under sqlite because the query uses a MySQL
     * function (DATE_FORMAT). They behave normally in production; there is
     * nothing here for this module to prove.
     */
    protected const MYSQL_ONLY = [
        'reports.payment-schedule',
        'reports.payment-schedule.pdf.view',
        'reports.payment-schedule.pdf.download',
    ];

    protected array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->users[$role] = User::factory()->create([
                'role_id' => Role::where('name', $role)->value('id'),
            ]);
        }
    }

    /**
     * Every page in the application, opened by every role.
     */
    public function test_every_screen_answers_exactly_as_it_did_before_the_engine(): void
    {
        $checked = 0;

        foreach ($this->screens() as $name => $url) {
            if (in_array($name, self::MYSQL_ONLY, true)) {
                continue;
            }

            $expected = self::EXPECTED[$name] ?? ['admin' => 200, 'manager' => 200, 'employee' => 200];

            foreach ($this->users as $role => $user) {
                $status = $this->actingAs($user)->get($url)->getStatusCode();

                $this->assertSame(
                    $expected[$role],
                    $status,
                    "Route '{$name}' answered {$status} for {$role}, expected {$expected[$role]}. ".
                    'If that change is deliberate, update the table in LegacyBehaviourTest.',
                );

                $checked++;
            }
        }

        // A guard against the enumeration silently finding nothing.
        $this->assertGreaterThan(150, $checked, 'Too few screens were checked.');
    }

    /**
     * The areas whose modules have had their permission pass. Each pass adds
     * its own line here, in the same commit that flips the flag.
     */
    protected const CONVERTED = ['users', 'access', 'team', 'project', 'projects', 'company', 'settings', 'expenses', 'income', 'budget', 'cost-codes', 'requisitions', 'quotations', 'purchase-orders', 'change-orders', 'contracts', 'payments', 'documents', 'tasks', 'meetings', 'daily-reports', 'estimates', 'invoices', 'clients', 'vendors', 'catalog', 'reports', 'project-report', 'dashboard', 'documentation'];

    public function test_only_the_converted_areas_are_swept(): void
    {
        // As of F2 that is all of them, and the legacy bridge is deleted. The
        // list stays because a module added later starts unswept, and the
        // permission matrix marks it "not enforced yet" until its pass lands.

        $swept = array_values(array_diff(array_keys(AbilityCatalog::areas()), AbilityCatalog::unsweptAreas()));

        sort($swept);
        $expected = self::CONVERTED;
        sort($expected);

        $this->assertSame(
            $expected,
            $swept,
            'An area was marked swept without its pass being recorded here.',
        );

        // The gate is live for catalogue abilities…
        $this->assertTrue($this->users['admin']->can('expenses.create'));
        $this->assertTrue($this->users['employee']->can('expenses.create'));
        $this->assertFalse($this->users['employee']->can('expenses.delete'));

        // …and leaves everything that is not ours alone.
        $this->assertFalse($this->users['admin']->can('some-ability-we-do-not-own'));
    }

    public function test_the_ability_middleware_guards_a_route(): void
    {
        Route::middleware(['web', 'auth', 'ability:expenses.delete'])
            ->get('/__test/ability', fn () => 'ok')
            ->name('test.ability');

        $this->actingAs($this->users['admin'])->get('/__test/ability')->assertOk();
        $this->actingAs($this->users['manager'])->get('/__test/ability')->assertForbidden();
        $this->actingAs($this->users['employee'])->get('/__test/ability')->assertForbidden();
    }

    /**
     * Every GET route that needs no parameters and sits behind `auth`.
     *
     * @return array<string, string>
     */
    protected function screens(): array
    {
        $screens = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if (! $name
                || ! in_array('GET', $route->methods(), true)
                || str_contains($route->uri(), '{')
                || ! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $screens[$name] = route($name);
        }

        ksort($screens);

        return $screens;
    }
}
