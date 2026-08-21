<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteIncome;
use App\Livewire\Project\ProjectIncome;
use App\Models\Client;
use App\Models\Income;
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
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M5 — Income.
 *
 * Mirrors M4 on both levels, and adds the one thing expenses has no equivalent
 * of: `income.distribute`, the split of one payment across several job sites.
 * Splitting is held apart from creating because it decides which site's report
 * the money lands on, which is a different act from recording that it arrived.
 */
class IncomeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected JobSite $siblingSite;

    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->project = $this->makeProject('Ours');
        $this->otherProject = $this->makeProject('Theirs');

        $this->site = $this->makeSite($this->project, 'Site A');
        $this->siblingSite = $this->makeSite($this->project, 'Site B');
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
                ['company_name' => 'Income Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-inc@example.test',
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
            'email' => str($name)->slug().'-inc@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeIncome(array $attributes = []): Income
    {
        return Income::create(array_merge([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'income_date' => now()->toDateString(),
            'title' => 'Measurement 1',
            'amount' => 5000,
            'status' => 'received',
            'created_by' => $this->admin->id,
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

    public function test_the_income_screens_answer_as_they_did_for_every_company_wide_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.income', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.income', $this->site))->assertOk();
        }
    }

    public function test_seeing_income_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.income', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.income', $this->site))->assertForbidden();
    }

    public function test_the_income_tab_disappears_for_somebody_without_the_ability(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);
        $reader = $this->roleWith(['project.view', 'projects.view', 'income.view']);

        $this->actingAs($blind)->get(route('projects.overview', $this->project))
            ->assertOk()
            ->assertDontSee(route('projects.income', $this->project));

        $this->actingAs($reader)->get(route('projects.overview', $this->project))
            ->assertOk()
            ->assertSee(route('projects.income', $this->project));
    }

    public function test_a_job_site_member_reaches_their_site_and_not_the_project_screen(): void
    {
        $member = $this->memberOf($this->site, ['income.view']);

        $this->actingAs($member)->get(route('jobsites.income', $this->site))->assertOk();
        $this->actingAs($member)->get(route('projects.income', $this->project))->assertForbidden();
        $this->actingAs($member)->get(route('jobsites.income', $this->siblingSite))->assertForbidden();
    }

    public function test_a_guest_holds_no_income_whatever_their_membership_says(): void
    {
        $template = PermissionTemplate::where('key', 'client-project')->first();

        $guest = $this->user('employee', ['is_guest' => true, 'access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $guest->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'permission_template_id' => $template->id,
            'can_see_money' => false,
        ]);
        $membership->syncAbilities($template->abilities());

        app(PermissionResolver::class)->flush();

        $this->actingAs($guest)->get(route('projects.income', $this->project))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Create, edit, delete — the button and the action behind it
    |---------------------------------------------------------------------------
    */

    public function test_the_add_income_button_is_not_shown_to_somebody_who_may_not_add_one(): void
    {
        $reader = $this->roleWith(['project.view', 'projects.view', 'income.view']);

        $this->actingAs($reader)->get(route('projects.income', $this->project))
            ->assertOk()
            ->assertDontSee('openAddModal');

        $this->actingAs($reader)->get(route('jobsites.income', $this->site))
            ->assertOk()
            ->assertDontSee('openAddModal');
    }

    public function test_adding_income_is_refused_without_the_grant_on_both_levels(): void
    {
        $reader = $this->memberOf($this->site, ['income.view']);
        $projectReader = $this->memberOf($this->project, ['income.view']);

        Livewire::actingAs($projectReader)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertForbidden();

        Livewire::actingAs($projectReader)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->call('saveIncome')
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('openAddModal')
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('saveIncome')
            ->assertForbidden();
    }

    public function test_editing_needs_its_own_grant_and_the_button_is_hidden_without_it(): void
    {
        $income = $this->makeIncome();
        $reader = $this->memberOf($this->site, ['income.view']);

        $this->actingAs($reader)->get(route('jobsites.income', $this->site))
            ->assertOk()
            ->assertDontSee('openEditModal('.$income->id.')');

        Livewire::actingAs($reader)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('openEditModal', $income->id)
            ->assertForbidden();
    }

    public function test_deleting_needs_its_own_grant_and_the_button_is_hidden_without_it(): void
    {
        $income = $this->makeIncome();
        $editor = $this->memberOf($this->site, ['income.view', 'income.create', 'income.edit']);

        $this->actingAs($editor)->get(route('jobsites.income', $this->site))
            ->assertOk()
            ->assertDontSee('deleteIncome('.$income->id.')');

        Livewire::actingAs($editor)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('deleteIncome', $income->id)
            ->assertForbidden();

        $this->assertNotNull($income->fresh());
    }

    public function test_booking_expected_money_as_received_needs_the_edit_grant(): void
    {
        $income = $this->makeIncome([
            'status' => 'expected',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $reader = $this->memberOf($this->site, ['income.view']);

        $this->actingAs($reader)->get(route('jobsites.income', $this->site))
            ->assertOk()
            ->assertDontSee('markReceived('.$income->id.')');

        Livewire::actingAs($reader)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('markReceived', $income->id)
            ->assertForbidden();

        $this->assertTrue($income->fresh()->isExpected());

        // …and with it, the booking goes through.
        $editor = $this->memberOf($this->site, ['income.view', 'income.edit']);

        Livewire::actingAs($editor)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('markReceived', $income->id)
            ->assertOk();

        $this->assertTrue($income->fresh()->isReceived());
    }

    /*
    |---------------------------------------------------------------------------
    | Distribute — the grant expenses has no equivalent of
    |---------------------------------------------------------------------------
    */

    public function test_splitting_across_locations_is_a_grant_of_its_own(): void
    {
        $recorder = $this->memberOf($this->project, ['income.view', 'income.create']);   // no distribute

        // The split option is not offered…
        $this->actingAs($recorder)->get(route('projects.income', $this->project))
            ->assertOk()
            ->assertDontSee(__('Split across locations'));

        // …the mode cannot be switched…
        Livewire::actingAs($recorder)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->set('income_location_mode', 'split')
            ->assertForbidden();

        // …and neither can the bulk helpers behind it.
        foreach (['splitEvenly', 'clearAllShares', 'toggleAllSites'] as $action) {
            Livewire::actingAs($recorder)
                ->test(ProjectIncome::class, ['project' => $this->project])
                ->call($action)
                ->assertForbidden();
        }
    }

    public function test_a_save_that_splits_is_refused_without_the_distribute_grant(): void
    {
        $recorder = $this->memberOf($this->project, ['income.view', 'income.create']);

        Livewire::actingAs($recorder)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->set('income_title', 'Measurement 2')
            ->set('income_amount', 1000)
            ->set('income_date', now()->toDateString())
            ->set('income_status', 'received')
            ->call('saveIncome')
            ->assertOk();   // a single-location save is fine

        $this->assertSame(1, Income::where('project_id', $this->project->id)->count());

        // The mode is set on the component directly, bypassing the guarded
        // updater — the save must still refuse.
        Livewire::actingAs($recorder)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->set('income_title', 'Measurement 3')
            ->set('income_amount', 1000)
            ->set('income_date', now()->toDateString())
            ->set('income_status', 'received')
            ->set('income_location_mode', 'split')
            ->assertForbidden();
    }

    public function test_the_distribute_grant_lets_a_member_split_a_payment(): void
    {
        $distributor = $this->memberOf(
            $this->project,
            ['income.view', 'income.create', 'income.distribute'],
        );

        Livewire::actingAs($distributor)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('income_title', 'Split payment')
            ->set('income_amount', 1000)
            ->set('income_date', now()->toDateString())
            ->set('income_status', 'received')
            ->set('income_location_mode', 'split')
            ->call('toggleAllSites')
            ->call('splitEvenly')
            ->call('saveIncome')
            ->assertOk()
            ->assertHasNoErrors();

        $income = Income::where('title', 'Split payment')->firstOrFail();

        $this->assertTrue($income->isDistributed());
        $this->assertEqualsWithDelta(1000.0, (float) $income->distributions->sum('amount'), 0.01);
    }

    /*
    |---------------------------------------------------------------------------
    | Where the income lands
    |---------------------------------------------------------------------------
    */

    public function test_a_project_member_may_record_against_any_of_its_sites(): void
    {
        $member = $this->memberOf($this->project, ['income.view', 'income.create']);

        Livewire::actingAs($member)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->set('income_title', 'Site money')
            ->set('income_amount', 250)
            ->set('income_date', now()->toDateString())
            ->set('income_status', 'received')
            ->set('income_job_site_id', $this->siblingSite->id)
            ->call('saveIncome')
            ->assertOk()
            ->assertHasNoErrors();

        $this->assertSame(
            $this->siblingSite->id,
            Income::where('title', 'Site money')->value('job_site_id'),
        );
    }

    public function test_income_of_another_project_cannot_be_reached_through_this_screen(): void
    {
        $foreign = $this->makeIncome([
            'project_id' => $this->otherProject->id,
            'job_site_id' => null,
            'title' => 'Not yours',
        ]);

        foreach (['deleteIncome', 'openViewModal', 'openEditModal', 'markReceived'] as $action) {
            try {
                Livewire::actingAs($this->admin)
                    ->test(ProjectIncome::class, ['project' => $this->project])
                    ->call($action, $foreign->id);

                $this->fail("{$action} reached income of another project.");
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                // Right answer: the lookup never leaves this project.
            }
        }

        $this->assertNotNull($foreign->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | Attachments
    |---------------------------------------------------------------------------
    */

    public function test_an_income_attachment_is_only_served_to_somebody_who_may_see_it(): void
    {
        Storage::fake('local');
        Storage::put('income/proof.pdf', 'x');

        $income = $this->makeIncome();
        $income->attachments()->create([
            'file_path' => 'income/proof.pdf',
            'original_name' => 'proof.pdf',
            'uploaded_by' => $this->admin->id,
        ]);

        $reader = $this->memberOf($this->site, ['income.view']);
        $outsider = $this->memberOf($this->siblingSite, ['income.view']);

        $this->actingAs($reader)->get(route('files.show', ['path' => 'income/proof.pdf']))->assertOk();
        $this->actingAs($outsider)->get(route('files.show', ['path' => 'income/proof.pdf']))->assertForbidden();
    }

    public function test_an_income_file_no_record_claims_is_not_served_at_all(): void
    {
        Storage::fake('local');
        Storage::put('income/orphan.pdf', 'x');

        $this->actingAs($this->admin)
            ->get(route('files.show', ['path' => 'income/orphan.pdf']))
            ->assertNotFound();
    }

    /*
    |---------------------------------------------------------------------------
    | Money — the same rule as M4
    |---------------------------------------------------------------------------
    */

    public function test_hiding_money_hides_the_totals_and_leaves_the_income_amounts(): void
    {
        $this->makeIncome(['amount' => 4321.99]);

        $withMoney = $this->memberOf($this->site, ['income.view'], money: true);
        $withoutMoney = $this->memberOf($this->site, ['income.view'], money: false);

        $amount = \Number::currency(4321.99, config('app.currency'), config('app.locale'));

        $this->actingAs($withMoney)->get(route('jobsites.income', $this->site))
            ->assertOk()
            ->assertSee($amount)
            ->assertDontSee(__('Totals are hidden for your access on this project.'));

        $this->actingAs($withoutMoney)->get(route('jobsites.income', $this->site))
            ->assertOk()
            ->assertSee($amount)
            ->assertSee(__('Totals are hidden for your access on this project.'));
    }

    /*
    |---------------------------------------------------------------------------
    | What the templates hand out
    |---------------------------------------------------------------------------
    */

    public function test_the_seeded_templates_grant_the_expected_income_actions(): void
    {
        $expected = [
            'project-manager' => ['income.view', 'income.create', 'income.edit', 'income.distribute'],
            'procurement' => [],
            'accounting' => ['income.view'],
            'client-project' => [],
            'site-supervisor' => [],
            'site-team' => [],
        ];

        foreach ($expected as $key => $abilities) {
            $template = PermissionTemplate::where('key', $key)->firstOrFail();

            $held = array_values(array_filter(
                $template->abilities(),
                fn ($a) => str_starts_with($a, 'income.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong income actions.");
        }
    }

    public function test_no_template_hands_out_income_delete(): void
    {
        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('income.delete', $template->abilities(), $template->key);
        }
    }
}
