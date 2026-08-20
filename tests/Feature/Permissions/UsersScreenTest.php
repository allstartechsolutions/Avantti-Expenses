<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\UserStatus;
use App\Livewire\User\UserEdit;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Users screen after M1: guarded by abilities rather than the `admin`
 * middleware, and the place where one person's project access can differ from
 * everybody else holding their role.
 */
class UsersScreenTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Now granted rather than hard-coded
    |---------------------------------------------------------------------------
    */

    public function test_the_answer_is_the_same_as_the_admin_middleware_gave(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('users.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('users.edit', $employee))->assertOk();

        $this->actingAs($employee)->get(route('users.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('users.create'))->assertForbidden();
        $this->actingAs($employee)->get(route('users.edit', $this->admin))->assertForbidden();
    }

    public function test_each_action_can_now_be_granted_separately(): void
    {
        $role = Role::create(['name' => 'hr']);
        $role->syncAbilities(['users.view', 'users.edit']);
        $hr = User::factory()->create(['role_id' => $role->id]);
        $someone = $this->user('employee');

        $this->actingAs($hr)->get(route('users.index'))->assertOk();
        $this->actingAs($hr)->get(route('users.show', $someone))->assertOk();
        $this->actingAs($hr)->get(route('users.edit', $someone))->assertOk();

        // …but not create, and not suspend.
        $this->actingAs($hr)->get(route('users.create'))->assertForbidden();

        Livewire::actingAs($hr)
            ->test(UserEdit::class, ['user' => $someone])
            ->set('status', UserStatus::SUSPENDED->value)
            ->call('updateUser')
            ->assertForbidden();

        $this->assertSame(UserStatus::ACTIVE, $someone->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | The per-person override
    |---------------------------------------------------------------------------
    */

    public function test_a_person_follows_their_role_until_somebody_says_otherwise(): void
    {
        $employee = $this->user('employee');

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $employee])
            ->assertSet('accessScope', '');

        $this->assertTrue($employee->followsRoleScope());
        $this->assertFalse($employee->isConfined());
    }

    public function test_one_person_can_be_confined_without_touching_their_role(): void
    {
        $confined = $this->user('employee');
        $colleague = $this->user('employee');

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $confined])
            ->set('accessScope', 'assigned')
            ->call('updateUser');

        $this->assertSame(AccessScope::ASSIGNED, $confined->fresh()->access_scope);
        $this->assertTrue($confined->fresh()->isConfined());

        // The role, and everybody else holding it, is untouched.
        $this->assertSame(AccessScope::COMPANY, Role::where('name', 'employee')->first()->access_scope);
        $this->assertFalse($colleague->fresh()->isConfined());
    }

    public function test_one_person_can_be_widened_when_their_role_is_confined(): void
    {
        Role::where('name', 'employee')->first()->update(['access_scope' => AccessScope::ASSIGNED]);

        $lead = $this->user('employee');
        $this->assertTrue($lead->isConfined(), 'They follow the role to begin with.');

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $lead])
            ->set('accessScope', 'company')
            ->call('updateUser');

        $this->assertFalse($lead->fresh()->isConfined());
        $this->assertFalse($lead->fresh()->followsRoleScope());
    }

    public function test_changing_somebody_back_to_following_the_role(): void
    {
        $person = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $person])
            ->assertSet('accessScope', 'assigned')
            ->set('accessScope', '')
            ->call('updateUser');

        $this->assertNull($person->fresh()->access_scope);
        $this->assertTrue($person->fresh()->followsRoleScope());
    }

    public function test_a_guest_cannot_be_widened(): void
    {
        $guest = $this->user('employee', ['is_guest' => true]);

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $guest])
            ->set('accessScope', 'company')
            ->call('updateUser');

        $this->assertSame(AccessScope::ASSIGNED, $guest->fresh()->access_scope);
        $this->assertTrue($guest->fresh()->isConfined());
    }

    public function test_a_scope_change_is_recorded(): void
    {
        $person = $this->user('employee');

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $person])
            ->set('accessScope', 'assigned')
            ->call('updateUser');

        $audit = PermissionAudit::where('subject_type', 'user')->where('action', 'scope-changed')->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame($person->id, $audit->subject_user_id);
        $this->assertSame($this->admin->id, $audit->actor_id);
        $this->assertSame('company', $audit->before['scope']);
        $this->assertSame('assigned', $audit->after['scope']);
    }

    public function test_a_scope_change_takes_effect_immediately(): void
    {
        $person = $this->user('employee');
        $project = $this->makeProject();

        // Company-wide to begin with: the role reaches every project.
        $this->assertTrue(app(PermissionResolver::class)->allows($person, 'expenses.view', $project));

        Livewire::actingAs($this->admin)
            ->test(UserEdit::class, ['user' => $person])
            ->set('accessScope', 'assigned')
            ->call('updateUser');

        // Confined and on nothing: denied on the next check, with no cache to
        // go stale.
        $this->assertFalse(app(PermissionResolver::class)->allows($person->fresh(), 'expenses.view', $project));
    }

    /*
    |---------------------------------------------------------------------------
    | The access panel
    |---------------------------------------------------------------------------
    */

    public function test_the_detail_page_shows_what_somebody_is_confined_to(): void
    {
        $project = $this->makeProject();
        $person = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $person->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'can_see_money' => false,
            'title' => 'Engenheiro',
        ]);
        $membership->syncAbilities(['expenses.view', 'documents.view']);

        $this->actingAs($this->admin)
            ->get(route('users.show', $person))
            ->assertOk()
            ->assertSee(__('Access'))
            ->assertSee($project->project_name)
            ->assertSee(__('Only the ones they are added to'))
            ->assertSee(__('No monetary figures'))
            ->assertSee('Engenheiro');
    }

    public function test_the_detail_page_warns_when_a_confined_person_is_on_nothing(): void
    {
        $person = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $this->actingAs($this->admin)
            ->get(route('users.show', $person))
            ->assertOk()
            ->assertSee(__('Not added to anything yet — so once the project screens are converted, this person will see nothing.'));
    }

    public function test_the_not_enforced_warning_disappears_once_the_area_is_converted(): void
    {
        // The warning is written to remove itself: M2 converted the project
        // area, so the screen stops saying the lists ignore this setting —
        // because they no longer do.
        $person = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $this->actingAs($this->admin)
            ->get(route('users.show', $person))
            ->assertOk()
            ->assertDontSee(__('Recorded but not enforced yet: the project screens have not been converted, so every project is still listed to everybody.'));
    }

    protected function makeProject(): Project
    {
        return Project::firstOrCreate(
            ['project_name' => 'Users Screen Project'],
            [
                'client_id' => Client::firstOrCreate(
                    ['company_name' => 'Users Client'],
                    ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
                )->id,
                'contact_person' => 'C',
                'email' => 'p@example.test',
                'created_by' => $this->admin->id,
            ],
        );
    }
}
