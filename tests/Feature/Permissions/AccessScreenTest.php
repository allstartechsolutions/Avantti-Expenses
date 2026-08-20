<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Livewire\Access\AccessIndex;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Who can open it
    |---------------------------------------------------------------------------
    */

    public function test_only_people_holding_the_ability_can_open_it(): void
    {
        $this->actingAs($this->user('admin'))->get(route('access.index'))->assertOk();
        $this->actingAs($this->user('manager'))->get(route('access.index'))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('access.index'))->assertForbidden();
    }

    public function test_it_lists_every_role_with_its_counts(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('access.index'))
            ->assertOk()
            ->assertSee('manager')
            ->assertSee('employee')
            ->assertSee(__('Allowed everything'));
    }

    public function test_the_matrix_renders_both_sections_with_their_areas(): void
    {
        $employee = Role::where('name', 'employee')->first();

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employee->id)
            ->assertSee(__('Company-wide screens'))
            ->assertSee(__('Projects and job sites'))
            ->assertSee(__('See monetary figures'))
            // an area from each section, and an action that is not CRUD
            ->assertSee(__('Estimates'))
            ->assertSee(__('Change Orders'))
            ->assertSee(__('Mark as paid'))
            // the honest label on an area whose module has not been converted
            ->assertSee(__('Not enforced yet'));
    }

    public function test_the_matrix_can_be_filtered(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', Role::where('name', 'employee')->value('id'))
            ->set('matrixSearch', 'expen')
            // The area name alone is not a safe needle — the roles list behind
            // the modal shows the same names as chips. The row's own control is.
            ->assertSee("toggleArea('expenses'", false)
            ->assertDontSee("toggleArea('change-orders'", false);
    }

    public function test_the_screen_reads_in_portuguese(): void
    {
        app()->setLocale('pt_BR');

        try {
            $this->actingAs($this->user('admin'))
                ->get(route('access.index'))
                ->assertOk()
                ->assertSee('Perfis e Acessos')
                ->assertSee('Permitido tudo')
                ->assertSee('Alterações recentes de acesso');
        } finally {
            app()->setLocale('en');
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Editing
    |---------------------------------------------------------------------------
    */

    public function test_editing_a_role_loads_what_it_currently_holds(): void
    {
        $manager = Role::where('name', 'manager')->first();

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $manager->id)
            ->assertSet('name', 'manager')
            ->assertSet('showRoleModal', true)
            ->assertDispatched('open-modal', 'role-modal')
            ->assertSet('granted.requisitions.approve', true)
            ->assertSet('granted.users.view', null);
    }

    public function test_granting_and_revoking_is_saved_and_recorded(): void
    {
        $admin = $this->user('admin');
        $employee = Role::where('name', 'employee')->first();

        $this->assertNotContains('requisitions.approve', $employee->abilities());

        Livewire::actingAs($admin)
            ->test(AccessIndex::class)
            ->call('editRole', $employee->id)
            ->set('granted.requisitions.approve', true)
            ->set('granted.expenses.create', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showRoleModal', false)
            ->assertDispatched('close-modal', 'role-modal');

        $employee->refresh()->unsetRelation('abilityRows');

        $this->assertContains('requisitions.approve', $employee->abilities());
        $this->assertNotContains('expenses.create', $employee->abilities());

        $audit = PermissionAudit::where('subject_type', 'role')->latest('id')->first();
        $this->assertSame('updated', $audit->action);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertStringContainsString('employee', $audit->summary);
        $this->assertContains('expenses.create', $audit->before['abilities']);
        $this->assertNotContains('expenses.create', $audit->after['abilities']);
    }

    public function test_an_ability_that_does_not_exist_cannot_be_granted(): void
    {
        $employee = Role::where('name', 'employee')->first();

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employee->id)
            // The granted array arrives from the browser; a made-up key must
            // not become a row in role_abilities.
            ->set('granted.made-up.ability', true)
            ->call('save')
            ->assertHasNoErrors();

        $employee->refresh()->unsetRelation('abilityRows');

        $this->assertNotContains('made-up.ability', $employee->abilities());

        foreach ($employee->abilities() as $ability) {
            $this->assertTrue(
                AbilityCatalog::has($ability) || $ability === AbilityCatalog::financeAbility(),
                "Role holds unknown ability '{$ability}'.",
            );
        }
    }

    public function test_the_money_switch_is_its_own_flag_and_survives_the_ability_filter(): void
    {
        $employee = Role::where('name', 'employee')->first();
        $finance = AbilityCatalog::financeAbility();

        // It loads from the role…
        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employee->id)
            ->assertSet('seeMoney', true)
            ->set('seeMoney', false)
            ->call('save');

        $employee->refresh()->unsetRelation('abilityRows');
        $this->assertNotContains($finance, $employee->abilities());

        // …and back on again. It is not an area, so it would otherwise be
        // dropped by the catalogue filter on the way out.
        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employee->id)
            ->assertSet('seeMoney', false)
            ->set('seeMoney', true)
            ->call('save');

        $employee->refresh()->unsetRelation('abilityRows');
        $this->assertContains($finance, $employee->abilities());
    }

    public function test_grant_all_and_clear_work_on_an_area_and_a_section(): void
    {
        $employee = Role::where('name', 'employee')->first();

        $component = Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employee->id)
            ->call('toggleArea', 'expenses', true);

        foreach (AbilityCatalog::abilitiesForArea('expenses') as $ability) {
            $component->assertSet('granted.'.$ability, true);
        }

        $component->call('toggleArea', 'expenses', false);

        foreach (AbilityCatalog::abilitiesForArea('expenses') as $ability) {
            $component->assertSet('granted.'.$ability, null);
        }

        $component->call('toggleSection', 'global', true)
            ->assertSet('granted.users.view', true)
            ->assertSet('granted.settings.manage_modules', true)
            ->call('toggleSection', 'global', false)
            ->assertSet('granted.users.view', null);
    }

    /*
    |---------------------------------------------------------------------------
    | "Only the projects they are added to"
    |---------------------------------------------------------------------------
    */

    public function test_a_role_can_confine_everybody_who_holds_it(): void
    {
        $employeeRole = Role::where('name', 'employee')->first();
        $employee = $this->user('employee');

        // Today: sees everything, because the role says so and the user
        // follows the role.
        $this->assertTrue($employee->followsRoleScope());
        $this->assertFalse($employee->isConfined());

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employeeRole->id)
            ->assertSet('accessScope', 'company')
            ->set('accessScope', 'assigned')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(AccessScope::ASSIGNED, $employeeRole->fresh()->access_scope);
        $this->assertTrue($employee->fresh()->isConfined());
    }

    public function test_a_person_with_their_own_setting_keeps_it(): void
    {
        $employeeRole = Role::where('name', 'employee')->first();

        $follower = $this->user('employee');
        $overridden = User::factory()->create([
            'role_id' => $employeeRole->id,
            'access_scope' => AccessScope::COMPANY,   // set on the user itself
        ]);

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employeeRole->id)
            ->set('accessScope', 'assigned')
            ->call('save');

        $this->assertTrue($follower->fresh()->isConfined(), 'Somebody following the role must follow it.');
        $this->assertFalse($overridden->fresh()->isConfined(), 'Somebody with their own setting keeps it.');
    }

    public function test_confining_a_role_takes_effect_in_the_resolver(): void
    {
        $employeeRole = Role::where('name', 'employee')->first();
        $employee = $this->user('employee');

        // Unswept areas answer from the role for a company-wide user…
        $this->assertTrue(app(PermissionResolver::class)->allows($employee, 'expenses.create'));

        $employeeRole->update(['access_scope' => AccessScope::ASSIGNED]);
        app(PermissionResolver::class)->flush();

        // …and deny a confined one outright, which is what the bridge is for.
        $this->assertFalse(app(PermissionResolver::class)->allows($employee->fresh(), 'expenses.create'));
    }

    public function test_the_screen_says_how_many_people_a_confinement_would_affect(): void
    {
        $employeeRole = Role::where('name', 'employee')->first();
        $this->user('employee');
        $this->user('employee');

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $employeeRole->id)
            ->assertSet('followersOfEditedRole', 2)
            ->set('accessScope', 'assigned')
            ->assertSee(__('Only the ones they are added to'))
            ->assertSee('2 people hold this role and follow it');
    }

    public function test_a_guest_is_confined_whatever_their_role_says(): void
    {
        $guest = $this->user('employee', ['is_guest' => true]);

        $this->assertSame(AccessScope::COMPANY, $guest->role->access_scope);
        $this->assertTrue($guest->isConfined());
        $this->assertFalse($guest->followsRoleScope());
    }

    public function test_the_admin_role_can_never_be_confined(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $adminRole->id)
            ->set('accessScope', 'assigned')
            ->call('save');

        $this->assertSame(AccessScope::COMPANY, $adminRole->fresh()->access_scope);
    }

    /*
    |---------------------------------------------------------------------------
    | The rules of the screen
    |---------------------------------------------------------------------------
    */

    public function test_the_admin_role_is_shown_but_not_editable(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $adminRole->id)
            ->assertSet('editingAdmin', true)
            // Even if the browser sends grants, an admin role holds no rows:
            // it is allowed everything before they are read.
            ->set('granted.expenses.view', true)
            ->call('save');

        $this->assertSame(0, $adminRole->abilityRows()->count());
    }

    public function test_a_built_in_role_cannot_be_renamed_or_deleted(): void
    {
        $manager = Role::where('name', 'manager')->first();

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $manager->id)
            ->set('name', 'gerente')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame('manager', $manager->fresh()->name);

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('deleteRole', $manager->id);

        $this->assertNotNull(Role::find($manager->id));
    }

    public function test_a_role_somebody_holds_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'procurement', 'description' => 'Buys things']);
        User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('deleteRole', $role->id);

        $this->assertNotNull(Role::find($role->id));
    }

    public function test_a_custom_role_can_be_created_and_deleted(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('newRole')
            ->set('name', 'Procurement')
            ->set('description', 'Buys, does not approve')
            ->set('granted.requisitions.create', true)
            ->set('granted.quotations.create', true)
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::where('name', 'Procurement')->first();

        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(
            ['requisitions.create', 'quotations.create'],
            $role->abilities(),
        );

        $created = PermissionAudit::where('subject_type', 'role')->where('action', 'created')->latest('id')->first();
        $this->assertStringContainsString('Procurement', $created->summary);

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('deleteRole', $role->id);

        $this->assertNull(Role::find($role->id));
        $this->assertSame(0, \App\Models\RoleAbility::where('role_id', $role->id)->count());
    }

    public function test_a_role_name_cannot_be_taken_twice(): void
    {
        Role::create(['name' => 'procurement']);

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('newRole')
            ->set('name', 'procurement')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_a_grant_takes_effect_immediately(): void
    {
        $employee = $this->user('employee');
        $role = $employee->role;

        $this->assertFalse($employee->can('requisitions.approve'));

        Livewire::actingAs($this->user('admin'))
            ->test(AccessIndex::class)
            ->call('editRole', $role->id)
            ->set('granted.requisitions.approve', true)
            ->call('save');

        // No cross-request cache: the next check sees the new grant.
        $this->assertTrue($employee->fresh()->can('requisitions.approve'));
    }

    public function test_somebody_who_can_only_view_cannot_save(): void
    {
        // A custom role holding access.view but not access.manage.
        $role = Role::create(['name' => 'auditor']);
        $role->syncAbilities(['access.view']);

        $auditor = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($auditor)->get(route('access.index'))->assertOk();

        Livewire::actingAs($auditor)
            ->test(AccessIndex::class)
            ->call('editRole', Role::where('name', 'employee')->value('id'))
            ->set('granted.expenses.delete', true)
            ->call('save')
            ->assertForbidden();
    }
}
