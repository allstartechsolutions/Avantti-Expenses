<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Livewire\Access\AccessIndex;
use App\Livewire\User\UserAccess;
use App\Models\Client;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F0 — per-person company-wide access.
 *
 * Four notations pointed at one missing piece: away from a project there was no
 * way to hold, or withhold, anything for **one person**. P6 (a company-wide
 * ability could only come from a role), P13 (the approval ceiling had no
 * company-wide home, so a company-wide user had no ceiling at all), P19 (so the
 * same person could be stopped on a contract and then pay the same money from
 * the payments dashboard) and P34 (the money switch could not be taken off one
 * person either).
 *
 * The answer the owner chose: **exceptions that can add and take away**, and a
 * ceiling that lives on the role with a per-person override.
 *
 * The whole of it is built so that **an install that upgrades and sets nothing
 * behaves exactly as it did**: no rows, no ceilings, no change. Half of this
 * file is that promise.
 */
class PerPersonAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->project = Project::create([
            'project_name' => 'Tower',
            'client_id' => Client::create([
                'company_name' => 'Acme', 'contact_name' => 'C',
                'email' => 'c@example.test', 'created_by' => $this->admin->id,
            ])->id,
            'contact_person' => 'C',
            'email' => 'tower@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function resolver(): PermissionResolver
    {
        return tap(app(PermissionResolver::class))->flush();
    }

    /*
    |---------------------------------------------------------------------------
    | Nothing changes until somebody changes something
    |---------------------------------------------------------------------------
    */

    public function test_an_install_that_sets_nothing_answers_exactly_as_before(): void
    {
        $employee = $this->user('employee');
        $manager = $this->user('manager');

        // The seeded split, unchanged: an employee cannot delete an expense,
        // a manager can approve a requisition, neither is capped.
        $this->assertTrue($this->resolver()->allows($employee, 'expenses.create'));
        $this->assertFalse($this->resolver()->allows($employee, 'expenses.delete'));
        $this->assertTrue($this->resolver()->allows($manager, 'requisitions.approve'));

        $this->assertNull($this->resolver()->approvalLimit($employee));
        $this->assertNull($this->resolver()->approvalLimit($manager));
        $this->assertSame(0, \App\Models\UserAbility::count());
    }

    /*
    |---------------------------------------------------------------------------
    | P6 — one person, one exception, without inventing a role
    |---------------------------------------------------------------------------
    */

    public function test_one_person_can_be_given_something_their_role_does_not_have(): void
    {
        $employee = $this->user('employee');

        $this->assertFalse($this->resolver()->allows($employee, 'cost-codes.edit'));

        $employee->syncAbilityOverrides(['cost-codes.edit' => true]);

        $this->assertTrue($this->resolver()->allows($employee, 'cost-codes.edit'));

        // …and nobody else who shares the role moved with them.
        $this->assertFalse($this->resolver()->allows($this->user('employee'), 'cost-codes.edit'));
    }

    public function test_one_person_can_have_something_taken_away(): void
    {
        $manager = $this->user('manager');

        $this->assertTrue($this->resolver()->allows($manager, 'requisitions.approve'));

        $manager->syncAbilityOverrides(['requisitions.approve' => false]);

        $this->assertFalse($this->resolver()->allows($manager, 'requisitions.approve'));
        $this->assertTrue($this->resolver()->allows($this->user('manager'), 'requisitions.approve'));
    }

    public function test_an_exception_cannot_hobble_an_administrator(): void
    {
        // An administrator is answered before any of this is read. Letting a
        // row here appear to bind one would be a control that does nothing.
        $this->admin->syncAbilityOverrides(['expenses.view' => false]);

        $this->assertTrue($this->resolver()->allows($this->admin, 'expenses.view'));
    }

    public function test_an_exception_cannot_hand_a_guest_the_company(): void
    {
        $guest = $this->user('employee', ['is_guest' => true, 'access_scope' => AccessScope::ASSIGNED]);

        $guest->syncAbilityOverrides(['users.view' => true, 'cost-codes.edit' => true]);

        $this->assertFalse($this->resolver()->allows($guest, 'users.view'));
        $this->assertFalse($this->resolver()->allows($guest, 'cost-codes.edit'));
    }

    public function test_an_exception_cannot_survive_the_module_switch(): void
    {
        $employee = $this->user('employee');
        $employee->syncAbilityOverrides(['invoices.view' => true]);

        $this->assertTrue($this->resolver()->allows($employee, 'invoices.view'));

        \App\Models\ModuleAccess::create([
            'module_key' => 'invoices',
            'module_name' => 'Invoices',
            'is_enabled' => false,
            'is_core' => false,
            'created_by' => $this->admin->id,
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertFalse($this->resolver()->allows($employee, 'invoices.view'));
    }

    public function test_a_project_ability_taken_away_is_taken_away_on_every_project(): void
    {
        // Company-wide people answer from their role on a project they have no
        // membership on, so an exception has to reach there too — otherwise
        // "never allowed" would be a promise the project screens do not keep.
        $manager = $this->user('manager');

        $this->assertTrue($this->resolver()->allows($manager, 'expenses.edit', $this->project));

        $manager->syncAbilityOverrides(['expenses.edit' => false]);

        $this->assertFalse($this->resolver()->allows($manager, 'expenses.edit', $this->project));
    }

    public function test_a_membership_still_beats_the_exception_on_its_own_project(): void
    {
        // Specific beats general, the rule the whole engine runs on: being made
        // a member of one project is being that on that project.
        $person = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $person->syncAbilityOverrides(['expenses.edit' => false]);

        $membership = Membership::create([
            'user_id' => $person->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(['project.view', 'expenses.view', 'expenses.edit']);

        $this->assertTrue($this->resolver()->allows($person, 'expenses.edit', $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | P34 — the money switch, per person
    |---------------------------------------------------------------------------
    */

    public function test_money_can_be_taken_off_one_person_company_wide(): void
    {
        $manager = $this->user('manager');

        $this->assertTrue($this->resolver()->canSeeMoney($manager));

        $manager->syncAbilityOverrides([AbilityCatalog::financeAbility() => false]);

        $this->assertFalse($this->resolver()->canSeeMoney($manager));
        $this->assertTrue($this->resolver()->canSeeMoney($this->user('manager')));
    }

    /*
    |---------------------------------------------------------------------------
    | P13 and P19 — the ceiling away from a project
    |---------------------------------------------------------------------------
    */

    public function test_a_role_can_carry_a_ceiling_and_a_person_can_override_it(): void
    {
        $role = Role::where('name', 'manager')->firstOrFail();
        $role->update(['approval_limit' => 1_000_000]);   // R$ 10.000,00

        $follower = $this->user('manager');
        $trusted = $this->user('manager', ['approval_limit' => 5_000_000]);
        $capped = $this->user('manager', ['approval_limit' => 100_000]);

        $this->assertSame(1_000_000, $this->resolver()->approvalLimit($follower));
        $this->assertSame(5_000_000, $this->resolver()->approvalLimit($trusted));
        $this->assertSame(100_000, $this->resolver()->approvalLimit($capped));

        $this->assertTrue($this->resolver()->withinApprovalLimit($follower, 900_000));
        $this->assertFalse($this->resolver()->withinApprovalLimit($follower, 1_100_000));
        $this->assertTrue($this->resolver()->withinApprovalLimit($trusted, 1_100_000));
        $this->assertFalse($this->resolver()->withinApprovalLimit($capped, 200_000));
    }

    public function test_the_ceiling_now_binds_on_the_company_wide_screens_too(): void
    {
        // P19 in one case. Before F0 this person was stopped inside the project
        // and then had no ceiling at all on the payments dashboard.
        $person = $this->user('manager', ['approval_limit' => 1_000_000]);

        $this->assertFalse($this->resolver()->withinApprovalLimit($person, 5_000_000, $this->project));
        $this->assertFalse($this->resolver()->withinApprovalLimit($person, 5_000_000));
    }

    public function test_a_membership_ceiling_still_answers_for_its_own_project(): void
    {
        $person = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED, 'approval_limit' => 100_000]);

        $membership = Membership::create([
            'user_id' => $person->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'approval_limit' => 9_000_000,
        ]);
        $membership->syncAbilities(['project.view']);

        // Trusted with more on the project they were trusted with it on, and
        // held to their own ceiling everywhere else.
        $this->assertSame(9_000_000, $this->resolver()->approvalLimit($person, $this->project));
        $this->assertSame(100_000, $this->resolver()->approvalLimit($person));
    }

    public function test_an_administrator_is_never_capped(): void
    {
        Role::where('name', 'admin')->update(['approval_limit' => 1]);
        $this->admin->update(['approval_limit' => 1]);

        $this->assertNull($this->resolver()->approvalLimit($this->admin->fresh()));
    }

    /*
    |---------------------------------------------------------------------------
    | The screen
    |---------------------------------------------------------------------------
    */

    public function test_the_access_screen_is_held_to_the_permission_module_itself(): void
    {
        $target = $this->user('employee');

        // Being allowed to edit a user is not being allowed to hand out
        // abilities: that is `access`, the most sensitive grant there is.
        $this->actingAs($this->user('manager'))->get(route('users.access', $target))->assertForbidden();
        $this->actingAs($this->admin)->get(route('users.access', $target))->assertOk();
    }

    public function test_the_screen_saves_only_the_differences_from_the_role(): void
    {
        $target = $this->user('employee');

        $component = Livewire::actingAs($this->admin)->test(UserAccess::class, ['user' => $target]);

        // Untouched, an employee's screen is exactly their role, so saving it
        // writes nothing at all.
        $component->call('save')->assertHasNoErrors();
        $this->assertSame(0, $target->abilityOverrides()->count());

        // One tick on, one tick off — two rows and no more.
        $component->set('granted.cost-codes.edit', true)
            ->set('granted.expenses.create', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([
            'cost-codes.edit' => true,
            'expenses.create' => false,
        ], $target->fresh()->abilityOverrideMap());

        $this->assertTrue($this->resolver()->allows($target->fresh(), 'cost-codes.edit'));
        $this->assertFalse($this->resolver()->allows($target->fresh(), 'expenses.create'));
    }

    public function test_following_the_role_again_clears_every_exception(): void
    {
        $target = $this->user('employee');
        $target->syncAbilityOverrides(['cost-codes.edit' => true, 'expenses.create' => false]);
        $target->update(['approval_limit' => 500_000]);

        Livewire::actingAs($this->admin)
            ->test(UserAccess::class, ['user' => $target->fresh()])
            ->call('followRole')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([], $target->fresh()->abilityOverrideMap());
        $this->assertNull($target->fresh()->approval_limit);
    }

    public function test_the_screen_counts_the_exceptions_it_is_about_to_save(): void
    {
        $target = $this->user('employee');

        Livewire::actingAs($this->admin)
            ->test(UserAccess::class, ['user' => $target])
            ->assertSee('Follows the role exactly')
            ->set('granted.cost-codes.edit', true)
            ->assertSee('1 exception');
    }

    public function test_the_screen_refuses_to_pretend_it_binds_an_administrator(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UserAccess::class, ['user' => $this->user('admin')])
            ->assertSee('Administrators are allowed everything')
            ->call('save')
            ->assertForbidden();
    }

    public function test_the_screen_says_so_when_the_person_is_a_guest(): void
    {
        $guest = $this->user('employee', ['is_guest' => true, 'access_scope' => AccessScope::ASSIGNED]);

        Livewire::actingAs($this->admin)
            ->test(UserAccess::class, ['user' => $guest])
            ->assertSee('This person is a guest.');
    }

    public function test_saving_the_screen_writes_one_audit_line(): void
    {
        $target = $this->user('employee');

        Livewire::actingAs($this->admin)
            ->test(UserAccess::class, ['user' => $target])
            ->set('granted.cost-codes.edit', true)
            ->call('save');

        $audit = PermissionAudit::where('subject_user_id', $target->id)
            ->where('action', 'abilities-changed')
            ->firstOrFail();

        $this->assertSame(['cost-codes.edit' => true], $audit->after['overrides']);
        $this->assertSame($this->admin->id, $audit->actor_id);
    }

    /*
    |---------------------------------------------------------------------------
    | The role's ceiling on the role editor
    |---------------------------------------------------------------------------
    */

    public function test_the_role_editor_saves_a_ceiling_in_cents(): void
    {
        $role = Role::where('name', 'manager')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(AccessIndex::class)
            ->call('editRole', $role->id)
            ->assertSet('approvalLimit', '')
            ->set('approvalLimit', '10000.50')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1_000_050, $role->fresh()->approval_limit);

        // …and reads back as it was typed, not as 10000.50000000001.
        Livewire::actingAs($this->admin)
            ->test(AccessIndex::class)
            ->call('editRole', $role->id)
            ->assertSet('approvalLimit', '10000.5');
    }

    public function test_clearing_the_role_ceiling_means_no_ceiling(): void
    {
        $role = Role::where('name', 'manager')->firstOrFail();
        $role->update(['approval_limit' => 1_000_000]);

        Livewire::actingAs($this->admin)
            ->test(AccessIndex::class)
            ->call('editRole', $role->id)
            ->set('approvalLimit', '')
            ->call('save');

        $this->assertNull($role->fresh()->approval_limit);
        $this->assertNull($this->resolver()->approvalLimit($this->user('manager')));
    }

    public function test_the_seeded_roles_carry_no_ceiling(): void
    {
        // The whole point: an install that upgrades is not suddenly capped.
        foreach (Role::SYSTEM as $name) {
            $this->assertNull(Role::where('name', $name)->value('approval_limit'), $name);
        }
    }
}
