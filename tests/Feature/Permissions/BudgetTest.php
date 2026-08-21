<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Budget\BudgetEdit;
use App\Livewire\Budget\BudgetShow;
use App\Livewire\CostCode\CostCodeTemplateIndex;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Client;
use App\Models\CostCodeTemplate;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
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
 * M6 — Budget & Cost Codes.
 *
 * Two halves that are deliberately not the same shape:
 *
 *  - **Budgets** live on a project or a job site, so every one of their grants
 *    can be given company-wide on a role, on a permission template, on one
 *    project, or to one person on one project.
 *  - **Cost code templates** are the company-wide library a budget is built
 *    from. They belong to no project, so they are held by role and appear in
 *    neither project editor. That is the point of them: one chart of accounts,
 *    used everywhere.
 *
 * And the thing this pass builds rather than merely guards: `budget.lock`.
 */
class BudgetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->project = $this->makeProject('Ours');
        $this->site = $this->makeSite($this->project, 'Site A');

        $this->budget = Budget::create([
            'project_id' => $this->project->id,
            'name' => 'Project budget',
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

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Budget Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-bud@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-bud@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeItem(array $attributes = []): BudgetItem
    {
        return BudgetItem::create(array_merge([
            'budget_id' => $this->budget->id,
            'code' => '01-'.str()->random(4),
            'name' => 'Site prep',
            'budgeted_amount' => 1000,
            'sort_order' => 1,
        ], $attributes));
    }

    protected function memberOf(Project|JobSite $scope, array $abilities, bool $money = true): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
            'can_see_money' => $money,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | The screens answer as they did
    |---------------------------------------------------------------------------
    */

    public function test_the_budget_screens_answer_as_they_did_for_every_company_wide_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.budget', $this->project))->assertOk();
            $this->actingAs($user)->get(route('budgets.show', $this->budget))->assertOk();
            $this->actingAs($user)->get(route('budgets.cost-grid', $this->budget))->assertOk();
        }
    }

    public function test_seeing_a_budget_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.budget', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('budgets.show', $this->budget))->assertForbidden();
        $this->actingAs($blind)->get(route('budgets.cost-grid', $this->budget))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Every budget grant is reachable at all four levels — the owner's rule
    |---------------------------------------------------------------------------
    */

    public function test_budget_lock_and_delete_are_grantable_on_a_role(): void
    {
        $locker = $this->roleWith(['project.view', 'projects.view', 'budget.view', 'budget.lock']);

        Livewire::actingAs($locker)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('lockBudget')
            ->assertOk();

        $this->assertTrue($this->budget->fresh()->isLocked());
    }

    public function test_budget_lock_and_delete_are_grantable_on_one_project_only(): void
    {
        $other = $this->makeProject('Elsewhere');
        $otherBudget = Budget::create([
            'project_id' => $other->id,
            'name' => 'Other budget',
            'created_by' => $this->admin->id,
        ]);

        // Held on THIS project, and nowhere else.
        $locker = $this->memberOf($this->project, ['budget.view', 'budget.lock']);

        Livewire::actingAs($locker)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('lockBudget')
            ->assertOk();

        $this->assertTrue($this->budget->fresh()->isLocked());

        $this->actingAs($locker)->get(route('budgets.show', $otherBudget))->assertForbidden();
    }

    public function test_budget_lock_is_grantable_to_one_person_on_one_job_site(): void
    {
        $siteBudget = Budget::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'name' => 'Site budget',
            'created_by' => $this->admin->id,
        ]);

        $siteLocker = $this->memberOf($this->site, ['budget.view', 'budget.lock']);

        Livewire::actingAs($siteLocker)
            ->test(BudgetShow::class, ['budget' => $siteBudget])
            ->call('lockBudget')
            ->assertOk();

        $this->assertTrue($siteBudget->fresh()->isLocked());

        // …and it does not reach the project's own budget.
        $this->actingAs($siteLocker)->get(route('budgets.show', $this->budget))->assertForbidden();
    }

    public function test_budget_lock_is_grantable_through_a_permission_template(): void
    {
        $template = PermissionTemplate::create([
            'key' => 'budget-owner',
            'name' => 'Budget owner',
            'level' => 'project',
            'can_see_money' => true,
        ]);
        $template->syncAbilities(['project.view', 'budget.view', 'budget.lock']);

        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'permission_template_id' => $template->id,
            'can_see_money' => true,
        ]);
        $membership->syncAbilities($template->abilities());

        app(PermissionResolver::class)->flush();

        Livewire::actingAs($user)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('lockBudget')
            ->assertOk();

        $this->assertTrue($this->budget->fresh()->isLocked());
    }

    public function test_no_budget_ability_is_hard_wired_to_an_administrator(): void
    {
        // Every one of the five, held by a plain employee through a role, works.
        $everything = $this->roleWith([
            'project.view', 'projects.view',
            'budget.view', 'budget.create', 'budget.edit', 'budget.delete', 'budget.lock',
        ]);

        $item = $this->makeItem();

        Livewire::actingAs($everything)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('openAddForm')
            ->assertOk()
            ->call('deleteItem', $item->id)
            ->assertOk()
            ->call('lockBudget')
            ->assertOk()
            ->call('unlockBudget')
            ->assertOk();

        $this->assertNull($item->fresh());
        $this->assertFalse($this->budget->fresh()->isLocked());
    }

    /*
    |---------------------------------------------------------------------------
    | Locking — the feature this pass builds
    |---------------------------------------------------------------------------
    */

    public function test_locking_needs_the_grant_and_the_button_is_hidden_without_it(): void
    {
        $editor = $this->memberOf($this->project, ['budget.view', 'budget.edit', 'budget.create']);

        $this->actingAs($editor)->get(route('budgets.show', $this->budget))
            ->assertOk()
            ->assertDontSee('lockBudget');

        Livewire::actingAs($editor)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('lockBudget')
            ->assertForbidden();

        $this->assertFalse($this->budget->fresh()->isLocked());
    }

    public function test_a_locked_budget_refuses_every_change_to_the_plan(): void
    {
        $item = $this->makeItem();

        $this->budget->lock($this->admin);
        $this->budget->refresh();

        // Even an administrator, who holds every ability there is.
        foreach ([
            ['openAddForm', []],
            ['openEditForm', [$item->id]],
            ['deleteItem', [$item->id]],
            ['toggleDefaultItem', [$item->id]],
        ] as [$action, $args]) {
            Livewire::actingAs($this->admin)
                ->test(BudgetShow::class, ['budget' => $this->budget])
                ->call($action, ...$args)
                ->assertForbidden();
        }

        $this->assertNotNull($item->fresh());

        // The budget's own record, and its deletion, are frozen too.
        $this->actingAs($this->admin)->get(route('budgets.edit', $this->budget))->assertOk();

        Livewire::actingAs($this->admin)
            ->test(BudgetEdit::class, ['budget' => $this->budget])
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test(BudgetEdit::class, ['budget' => $this->budget])
            ->call('deleteBudget')
            ->assertForbidden();

        $this->assertNotNull($this->budget->fresh());
    }

    public function test_a_locked_budget_still_shows_its_figures_and_says_it_is_locked(): void
    {
        $this->makeItem();
        $this->budget->lock($this->admin);

        $this->actingAs($this->admin)->get(route('budgets.show', $this->budget))
            ->assertOk()
            ->assertSee(__('This budget is locked.'))
            ->assertSee('Site prep')
            ->assertDontSee('openAddForm');
    }

    public function test_unlocking_reopens_the_plan(): void
    {
        $this->budget->lock($this->admin);

        Livewire::actingAs($this->admin)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('unlockBudget')
            ->assertOk();

        $this->assertFalse($this->budget->fresh()->isLocked());

        Livewire::actingAs($this->admin)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('openAddForm')
            ->assertOk();
    }

    public function test_every_lock_and_unlock_is_kept_with_who_and_when(): void
    {
        $locker = $this->roleWith(['project.view', 'projects.view', 'budget.view', 'budget.lock']);

        $this->budget->lock($locker, 'Baseline approved');
        $this->budget->unlock($this->admin, 'Client added a floor');
        $this->budget->lock($locker);

        $history = $this->budget->fresh()->lockHistories()->orderBy('id')->get();

        $this->assertCount(3, $history);
        $this->assertSame(['locked', 'unlocked', 'locked'], $history->pluck('action')->all());
        $this->assertSame($locker->id, $history[0]->user_id);
        $this->assertSame('Baseline approved', $history[0]->reason);
        $this->assertSame($this->admin->id, $history[1]->user_id);

        // …and the current state names the person who set it.
        $this->assertSame($locker->id, $this->budget->fresh()->locked_by);
    }

    public function test_locking_twice_changes_nothing(): void
    {
        $this->budget->lock($this->admin);
        $lockedAt = $this->budget->fresh()->locked_at;

        $this->budget->fresh()->lock($this->admin);

        $this->assertCount(1, $this->budget->fresh()->lockHistories);
        $this->assertEquals($lockedAt, $this->budget->fresh()->locked_at);
    }

    /*
    |---------------------------------------------------------------------------
    | The ordinary grants
    |---------------------------------------------------------------------------
    */

    public function test_adding_editing_and_deleting_cost_codes_are_separate_grants(): void
    {
        $item = $this->makeItem();
        $reader = $this->memberOf($this->project, ['budget.view']);

        foreach ([
            ['openAddForm', []],
            ['openEditForm', [$item->id]],
            ['deleteItem', [$item->id]],
        ] as [$action, $args]) {
            Livewire::actingAs($reader)
                ->test(BudgetShow::class, ['budget' => $this->budget])
                ->call($action, ...$args)
                ->assertForbidden();
        }

        // Create alone opens the add form but not the edit one.
        $creator = $this->memberOf($this->project, ['budget.view', 'budget.create']);

        Livewire::actingAs($creator)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('openAddForm')
            ->assertOk();

        Livewire::actingAs($creator)
            ->test(BudgetShow::class, ['budget' => $this->budget])
            ->call('openEditForm', $item->id)
            ->assertForbidden();
    }

    public function test_deleting_a_budget_needs_its_own_grant(): void
    {
        $editor = $this->memberOf($this->project, ['budget.view', 'budget.edit']);

        Livewire::actingAs($editor)
            ->test(BudgetEdit::class, ['budget' => $this->budget])
            ->call('deleteBudget')
            ->assertForbidden();

        $this->assertNotNull($this->budget->fresh());
    }

    public function test_a_cost_code_of_another_budget_cannot_be_reached_through_this_screen(): void
    {
        $other = Budget::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'name' => 'Site budget',
            'created_by' => $this->admin->id,
        ]);

        $foreign = BudgetItem::create([
            'budget_id' => $other->id,
            'code' => '99-9999',
            'name' => 'Not this budget',
            'budgeted_amount' => 5,
            'sort_order' => 1,
        ]);

        foreach (['openEditForm', 'deleteItem', 'toggleDefaultItem'] as $action) {
            try {
                Livewire::actingAs($this->admin)
                    ->test(BudgetShow::class, ['budget' => $this->budget])
                    ->call($action, $foreign->id);

                $this->fail("{$action} reached a cost code of another budget.");
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                // Right answer.
            }
        }

        $this->assertNotNull($foreign->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | Cost code templates — the global library
    |---------------------------------------------------------------------------
    */

    public function test_the_templates_screen_answers_exactly_as_the_admin_middleware_did(): void
    {
        $this->actingAs($this->user('admin'))->get(route('cost-codes.templates.index'))->assertOk();
        $this->actingAs($this->user('manager'))->get(route('cost-codes.templates.index'))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('cost-codes.templates.index'))->assertForbidden();
    }

    public function test_the_templates_screen_can_be_given_to_somebody_who_is_not_an_administrator(): void
    {
        $keeper = $this->roleWith(['cost-codes.view', 'cost-codes.create', 'cost-codes.edit']);

        $this->actingAs($keeper)->get(route('cost-codes.templates.index'))->assertOk();
        $this->actingAs($keeper)->get(route('cost-codes.templates.create'))->assertOk();

        // …without the destructive half.
        $template = CostCodeTemplate::create(['name' => 'Standard', 'created_by' => $this->admin->id]);

        Livewire::actingAs($keeper)
            ->test(CostCodeTemplateIndex::class)
            ->call('deleteTemplate', $template->id)
            ->assertForbidden();

        $this->assertNotNull($template->fresh());
    }

    public function test_a_reader_of_the_templates_sees_no_write_controls(): void
    {
        CostCodeTemplate::create(['name' => 'Standard', 'created_by' => $this->admin->id]);

        $reader = $this->roleWith(['cost-codes.view']);

        $this->actingAs($reader)->get(route('cost-codes.templates.index'))
            ->assertOk()
            ->assertDontSee(route('cost-codes.templates.create'))
            ->assertDontSee('deleteTemplate(');

        $this->actingAs($reader)->get(route('cost-codes.templates.create'))->assertForbidden();
    }

    public function test_the_templates_are_company_wide_and_belong_to_no_project(): void
    {
        // A project membership granting every budget ability does not reach the
        // company-wide template library: it is one chart of accounts, held by
        // role, not per project.
        $projectBudgetOwner = $this->memberOf($this->project, [
            'budget.view', 'budget.create', 'budget.edit', 'budget.delete', 'budget.lock',
        ]);

        $this->actingAs($projectBudgetOwner)->get(route('cost-codes.templates.index'))->assertForbidden();

        $this->assertSame(['global'], \App\Services\AbilityCatalog::area('cost-codes')['levels']);
    }

    /*
    |---------------------------------------------------------------------------
    | Money
    |---------------------------------------------------------------------------
    */

    public function test_the_budget_area_is_declared_as_money_and_scoped_to_both_levels(): void
    {
        $area = \App\Services\AbilityCatalog::area('budget');

        $this->assertTrue($area['money']);
        $this->assertSame(['project', 'job_site'], $area['levels']);
    }
}
