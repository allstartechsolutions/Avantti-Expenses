<?php

namespace Tests\Feature\Permissions;

use App\Enums\MembershipStatus;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded role abilities are supposed to reproduce what the application
 * enforces TODAY, so that flipping an area to `swept` is a no-op for staff.
 * These are the line-by-line checks behind that claim.
 */
class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
    }

    public function test_admin_holds_no_ability_rows_because_it_bypasses_them(): void
    {
        $this->assertSame(0, Role::where('name', 'admin')->first()->abilityRows()->count());
    }

    /**
     * @dataProvider todaysRules
     */
    public function test_seeded_roles_reproduce_todays_rules(string $role, string $ability, bool $expected): void
    {
        $abilities = Role::where('name', $role)->first()->abilities();

        $this->assertSame(
            $expected,
            in_array($ability, $abilities, true),
            "Role '{$role}' should ".($expected ? 'hold' : 'not hold')." '{$ability}'.",
        );
    }

    public static function todaysRules(): array
    {
        return [
            // -- manager: admin OR manager today ---------------------------
            'manager reviews requisitions' => ['manager', 'requisitions.approve', true],
            'manager awards a round' => ['manager', 'quotations.award', true],
            'manager converts a round' => ['manager', 'quotations.convert', true],
            'manager manages documents' => ['manager', 'documents.create', true],
            'manager shares a document' => ['manager', 'documents.share', true],
            'manager sees internal documents' => ['manager', 'documents.see_internal', true],
            'manager manages meeting series' => ['manager', 'meetings.manage_series', true],

            // -- manager: admin-only today ---------------------------------
            'manager cannot edit a paid expense' => ['manager', 'expenses.edit_paid', false],
            'manager cannot delete an expense' => ['manager', 'expenses.delete', false],
            'manager cannot delete a document' => ['manager', 'documents.delete', false],
            'manager cannot merge vendors' => ['manager', 'vendors.merge', false],
            'manager cannot reach users' => ['manager', 'users.view', false],
            'manager cannot reach reports' => ['manager', 'reports.view', false],
            'manager cannot reach settings' => ['manager', 'settings.view', false],
            'manager cannot reach cost code templates' => ['manager', 'cost-codes.view', false],
            'manager cannot archive a project' => ['manager', 'project.archive', false],

            // -- manager: new abilities start closed -----------------------
            'manager cannot manage access' => ['manager', 'access.manage', false],
            'manager cannot lock a budget' => ['manager', 'budget.lock', false],
            'manager cannot refund a payment' => ['manager', 'payments.refund', false],

            // -- employee: daily work is open today ------------------------
            'employee keys in an expense' => ['employee', 'expenses.create', true],
            'employee edits an expense' => ['employee', 'expenses.edit', true],
            'employee raises a requisition' => ['employee', 'requisitions.create', true],
            'employee submits a requisition' => ['employee', 'requisitions.submit', true],
            'employee duplicates a requisition' => ['employee', 'requisitions.duplicate', true],
            'employee runs a quotation round' => ['employee', 'quotations.create', true],
            'employee reads documents' => ['employee', 'documents.view', true],
            'employee sees monetary figures' => ['employee', 'finance.view_amounts', true],

            // -- employee: ungated today, tightened in the module's own pass
            'employee deletes a change order (ungated today, M10)' => ['employee', 'change-orders.delete', true],
            'employee creates an estimate (ungated today, M15)' => ['employee', 'estimates.create', true],

            // -- employee: admin or manager today --------------------------
            'employee cannot approve a requisition' => ['employee', 'requisitions.approve', false],
            'employee cannot award a round' => ['employee', 'quotations.award', false],
            'employee cannot manage documents' => ['employee', 'documents.create', false],
            'employee cannot share a document' => ['employee', 'documents.share', false],
            'employee cannot see internal documents' => ['employee', 'documents.see_internal', false],
            'employee cannot manage meeting series' => ['employee', 'meetings.manage_series', false],
            'employee cannot delete an expense' => ['employee', 'expenses.delete', false],
        ];
    }

    public function test_every_seeded_grant_names_a_real_ability(): void
    {
        $known = array_merge(AbilityCatalog::abilities(), [AbilityCatalog::financeAbility()]);

        foreach (Role::with('abilityRows')->get() as $role) {
            foreach ($role->abilities() as $ability) {
                $this->assertContains($ability, $known, "Role '{$role->name}' grants unknown '{$ability}'.");
            }
        }

        foreach (PermissionTemplate::with('abilityRows')->get() as $template) {
            foreach ($template->abilities() as $ability) {
                $this->assertContains($ability, $known, "Template '{$template->key}' grants unknown '{$ability}'.");
                $this->assertTrue(
                    AbilityCatalog::isGrantableAt($ability, $template->level),
                    "Template '{$template->key}' grants '{$ability}', which cannot be held at level '{$template->level}'.",
                );
            }
        }
    }

    public function test_system_templates_are_created_for_both_levels_including_guests(): void
    {
        // 8 since the collaboration module added "Projetista (external)",
        // which is also the third guest template.
        $this->assertSame(8, PermissionTemplate::where('is_system', true)->count());
        $this->assertTrue(PermissionTemplate::forLevel('project')->forStaff()->exists());
        $this->assertTrue(PermissionTemplate::forLevel('job_site')->forStaff()->exists());
        $this->assertSame(3, PermissionTemplate::forGuests()->count());

        // A guest template must never carry money or a company-wide ability.
        foreach (PermissionTemplate::forGuests()->with('abilityRows')->get() as $guest) {
            $this->assertFalse($guest->can_see_money);
        }
    }

    public function test_running_the_seeder_again_changes_nothing(): void
    {
        $before = [
            \App\Models\RoleAbility::count(),
            \App\Models\PermissionTemplateAbility::count(),
            PermissionTemplate::count(),
        ];

        app(PermissionSeeder::class)->run();

        $this->assertSame($before, [
            \App\Models\RoleAbility::count(),
            \App\Models\PermissionTemplateAbility::count(),
            PermissionTemplate::count(),
        ]);
    }

    public function test_sync_never_hands_back_an_ability_somebody_deliberately_revoked(): void
    {
        $role = Role::where('name', 'employee')->first();

        // An administrator empties an area outright — the case that broke this
        // in testing: "holds nothing here" was read as "has never seen this".
        $kept = array_values(array_filter(
            $role->abilities(),
            fn ($ability) => ! str_starts_with($ability, 'expenses.'),
        ));

        $role->syncAbilities($kept);
        $role->unsetRelation('abilityRows');

        app(PermissionSeeder::class)->grantAbilitiesOfNewAreas();

        $role->refresh()->unsetRelation('abilityRows');

        $this->assertEqualsCanonicalizing($kept, $role->abilities(), 'A revoked area was re-granted.');
        $this->assertNotContains('expenses.view', $role->abilities());
    }

    public function test_sync_does_hand_over_an_area_that_is_genuinely_new(): void
    {
        $role = Role::where('name', 'employee')->first();

        // Pretend Expenses had not been invented when this role was seeded.
        $role->update([
            'seeded_areas' => array_values(array_diff($role->seededAreas(), ['expenses'])),
        ]);
        $role->syncAbilities(array_values(array_filter(
            $role->abilities(),
            fn ($ability) => ! str_starts_with($ability, 'expenses.'),
        )));
        $role->unsetRelation('abilityRows');

        $added = app(PermissionSeeder::class)->grantAbilitiesOfNewAreas();

        $role->refresh()->unsetRelation('abilityRows');

        $this->assertContains('expenses.view', $role->abilities());
        $this->assertContains('expenses.create', $role->abilities());
        $this->assertArrayHasKey('employee', $added);

        // …and only once. A second run must be silent.
        $this->assertSame([], app(PermissionSeeder::class)->grantAbilitiesOfNewAreas());
    }

    public function test_seeding_records_which_areas_a_role_has_been_offered(): void
    {
        $role = Role::where('name', 'manager')->first();

        $this->assertContains('expenses', $role->seededAreas());
        $this->assertContains('documents', $role->seededAreas());
    }

    public function test_the_project_manager_and_supervisor_become_real_memberships(): void
    {
        $manager = User::factory()->create();
        $supervisor = User::factory()->create();
        $project = $this->makeProject($manager, 'Backfill Test', ['project_manager_id' => $manager->id]);

        $jobSite = JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => 'Site A',
            'contact_person' => 'Site Contact',
            'email' => 'site@example.test',
            'supervisor_id' => $supervisor->id,
            'created_by' => $manager->id,
        ]);

        app(PermissionSeeder::class)->backfillMemberships();

        $projectMembership = Membership::where('user_id', $manager->id)
            ->where('scopeable_type', Project::class)
            ->where('scopeable_id', $project->id)
            ->first();

        $this->assertNotNull($projectMembership);
        $this->assertSame(MembershipStatus::ACTIVE, $projectMembership->status);
        $this->assertSame('Project Manager', $projectMembership->template->name);
        $this->assertContains('expenses.create', $projectMembership->abilities());

        $siteMembership = Membership::where('user_id', $supervisor->id)
            ->where('scopeable_type', JobSite::class)
            ->where('scopeable_id', $jobSite->id)
            ->first();

        $this->assertNotNull($siteMembership);
        $this->assertSame('Site Supervisor', $siteMembership->template->name);
        $this->assertFalse($siteMembership->can_see_money);

        // Idempotent: a second run must not duplicate either membership.
        app(PermissionSeeder::class)->backfillMemberships();
        $this->assertSame(2, Membership::count());
    }

    public function test_a_membership_reports_custom_once_it_diverges_from_its_template(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProject($user, 'Label Test');

        $template = PermissionTemplate::where('key', 'procurement')->first();

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'permission_template_id' => $template->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $membership->syncAbilities($template->abilities());
        $this->assertSame('Procurement', $membership->accessLabel());

        $membership->syncAbilities(array_slice($template->abilities(), 0, 3));
        $this->assertSame('Custom (based on Procurement)', $membership->accessLabel());
    }

    public function test_existing_users_stay_company_wide(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->fresh()->isCompanyWide());
        $this->assertFalse($user->fresh()->isConfined());
        $this->assertFalse($user->fresh()->is_guest);
    }

    /** The smallest project the schema will accept, with its client. */
    protected function makeProject(User $owner, string $name, array $attributes = []): Project
    {
        $client = Client::create([
            'company_name' => $name.' Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $owner->id,
        ]);

        return Project::create(array_merge([
            'project_name' => $name,
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $owner->id,
        ], $attributes));
    }
}
