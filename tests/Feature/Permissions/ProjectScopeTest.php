<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Livewire\Project\ProjectIndex;
use App\Livewire\Shared\HeaderSearch;
use App\Models\Client;
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
 * M2 — the project and job-site shell.
 *
 * Which projects somebody sees in a list, and which they may open. What they
 * may do *inside* one is each module's own pass; this file deliberately proves
 * that too, so the boundary is on the record.
 */
class ProjectScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $theirs;

    protected Project $someoneElses;

    protected JobSite $theirSite;

    protected JobSite $otherSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->theirs = $this->makeProject('Theirs');
        $this->someoneElses = $this->makeProject('Someone Elses');

        $this->theirSite = $this->makeSite($this->theirs, 'Their Site');
        $this->otherSite = $this->makeSite($this->someoneElses, 'Other Site');
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Scope Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => \App\Enums\ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => \App\Enums\JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function confinedMemberOf(Project|JobSite $scope, array $abilities = ['project.view']): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities($abilities);

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | The list
    |---------------------------------------------------------------------------
    */

    public function test_a_confined_member_sees_only_their_own_project_in_the_list(): void
    {
        $member = $this->confinedMemberOf($this->theirs);

        Livewire::actingAs($member)
            ->test(ProjectIndex::class)
            ->assertSee('Theirs')
            ->assertDontSee('Someone Elses');
    }

    public function test_everybody_else_still_sees_every_project(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            Livewire::actingAs($this->user($role))
                ->test(ProjectIndex::class)
                ->assertSee('Theirs')
                ->assertSee('Someone Elses');
        }
    }

    public function test_being_on_a_job_site_shows_its_project_too(): void
    {
        // Otherwise the breadcrumbs of the site they *can* open are nonsense.
        $member = $this->confinedMemberOf($this->theirSite);

        $this->assertEqualsCanonicalizing(
            ['Theirs'],
            Project::visibleTo($member)->pluck('project_name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['Their Site'],
            JobSite::visibleTo($member)->pluck('job_site_name')->all(),
        );
    }

    public function test_a_project_membership_shows_every_job_site_under_it(): void
    {
        $second = $this->makeSite($this->theirs, 'Their Second Site');
        $member = $this->confinedMemberOf($this->theirs);

        $this->assertEqualsCanonicalizing(
            ['Their Site', 'Their Second Site'],
            JobSite::visibleTo($member)->pluck('job_site_name')->all(),
        );
    }

    public function test_a_confined_person_on_nothing_sees_nothing(): void
    {
        $nobody = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $this->assertSame(0, Project::visibleTo($nobody)->count());
        $this->assertSame(0, JobSite::visibleTo($nobody)->count());
    }

    public function test_a_suspended_membership_takes_the_project_away(): void
    {
        $member = $this->confinedMemberOf($this->theirs);

        $this->assertSame(1, Project::visibleTo($member)->count());

        Membership::where('user_id', $member->id)->update(['status' => MembershipStatus::SUSPENDED]);
        app(PermissionResolver::class)->flush();

        $this->assertSame(0, Project::visibleTo($member->fresh())->count());
    }

    /*
    |---------------------------------------------------------------------------
    | The door
    |---------------------------------------------------------------------------
    */

    public function test_a_project_they_are_not_on_cannot_be_opened_by_url(): void
    {
        $member = $this->confinedMemberOf($this->theirs);

        $this->actingAs($member)->get(route('projects.overview', $this->theirs))->assertOk();
        $this->actingAs($member)->get(route('projects.overview', $this->someoneElses))->assertForbidden();
    }

    public function test_every_tab_of_a_project_they_are_not_on_is_shut(): void
    {
        // The guard is middleware on every route carrying a project, so a tab
        // added later is covered without anybody remembering to guard it.
        $member = $this->confinedMemberOf($this->theirs);

        foreach ([
            'projects.expenses', 'projects.budget', 'projects.income', 'projects.documents',
            'projects.contracts', 'projects.report', 'projects.jobsites', 'projects.team',
        ] as $route) {
            $this->actingAs($member)
                ->get(route($route, $this->someoneElses))
                ->assertForbidden();
        }
    }

    public function test_a_job_site_of_another_project_is_shut(): void
    {
        $member = $this->confinedMemberOf($this->theirs);

        $this->actingAs($member)->get(route('jobsites.overview', $this->theirSite))->assertOk();
        $this->actingAs($member)->get(route('jobsites.overview', $this->otherSite))->assertForbidden();
    }

    public function test_the_pdf_of_a_project_they_are_not_on_is_shut(): void
    {
        // The report PDFs carry a project, so the same middleware covers them
        // even though the reports module has not had its pass.
        $member = $this->confinedMemberOf($this->theirs);

        $this->actingAs($member)
            ->get(route('projects.report.pdf.view', $this->someoneElses))
            ->assertForbidden();
    }

    public function test_being_added_to_a_project_is_enough_to_open_it(): void
    {
        // A membership that forgot project.view — or was backfilled before the
        // area existed — must not lock somebody out of their own project.
        $member = $this->confinedMemberOf($this->theirs, ['expenses.view']);

        $this->actingAs($member)->get(route('projects.overview', $this->theirs))->assertOk();
    }

    /*
    |---------------------------------------------------------------------------
    | The search
    |---------------------------------------------------------------------------
    */

    public function test_the_header_search_cannot_be_used_to_enumerate_projects(): void
    {
        // N9: the search was the easiest way to see the names, clients and
        // addresses of records somebody was never meant to reach.
        $member = $this->confinedMemberOf($this->theirs);

        Livewire::actingAs($member)
            ->test(HeaderSearch::class)
            ->set('search', 'Elses')
            ->assertDontSee('Someone Elses');

        Livewire::actingAs($member)
            ->test(HeaderSearch::class)
            ->set('search', 'Theirs')
            ->assertSee('Theirs');
    }

    /*
    |---------------------------------------------------------------------------
    | Buttons nobody can use are not shown
    |---------------------------------------------------------------------------
    */

    public function test_the_add_project_button_is_only_shown_to_people_who_may_add_one(): void
    {
        // Reported by the owner: the guard worked and answered 403, but the
        // button was still there to be clicked. A button you cannot use should
        // not be on the screen.
        $role = Role::create(['name' => 'reader']);
        $role->syncAbilities(['projects.view', 'project.view']);
        $reader = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($reader)
            ->test(ProjectIndex::class)
            ->assertDontSee(__('Add Project'));

        Livewire::actingAs($this->admin)
            ->test(ProjectIndex::class)
            ->assertSee(__('Add Project'));
    }

    public function test_the_edit_and_delete_controls_follow_the_abilities(): void
    {
        $role = Role::create(['name' => 'reader-two']);
        $role->syncAbilities(['projects.view', 'project.view']);
        $reader = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($reader)
            ->get(route('projects.overview', $this->theirs))
            ->assertOk()
            ->assertDontSee(__('Edit Project'));

        $this->actingAs($this->admin)
            ->get(route('projects.overview', $this->theirs))
            ->assertOk()
            ->assertSee(__('Edit Project'));
    }

    public function test_the_add_job_site_button_needs_the_project_edit_ability(): void
    {
        $role = Role::create(['name' => 'reader-three']);
        $role->syncAbilities(['projects.view', 'project.view']);
        $reader = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($reader)
            ->get(route('projects.jobsites', $this->theirs))
            ->assertOk()
            ->assertDontSee(__('Add Job Site'));

        $this->actingAs($this->admin)
            ->get(route('projects.jobsites', $this->theirs))
            ->assertOk()
            ->assertSee(__('Add Job Site'));
    }

    public function test_the_delete_button_in_the_project_header_is_hidden_and_guarded(): void
    {
        // Reported by the owner. It was worse than a stray button: the delete
        // actions on both overview screens had no guard at all, so an employee
        // could genuinely delete a project or a job site.
        $role = Role::create(['name' => 'no-delete']);
        $role->syncAbilities(['projects.view', 'project.view', 'project.edit']);
        $member = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($member)
            ->get(route('projects.overview', $this->theirs))
            ->assertOk()
            // The word "Delete" appears elsewhere on the page; the button's
            // own action is the honest needle.
            ->assertDontSee('wire:click="confirmDeleteProject"', false);

        Livewire::actingAs($member)
            ->test(\App\Livewire\Project\ProjectOverview::class, ['project' => $this->theirs])
            ->call('confirmDeleteProject')
            ->assertForbidden();

        Livewire::actingAs($member)
            ->test(\App\Livewire\Project\ProjectOverview::class, ['project' => $this->theirs])
            ->call('deleteProject')
            ->assertForbidden();

        $this->assertNotNull(Project::find($this->theirs->id), 'The project must still be there.');
    }

    public function test_the_delete_button_on_a_job_site_is_hidden_and_guarded(): void
    {
        $role = Role::create(['name' => 'no-delete-site']);
        $role->syncAbilities(['projects.view', 'project.view', 'project.edit']);
        $member = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($member)
            ->get(route('jobsites.overview', $this->theirSite))
            ->assertOk()
            ->assertDontSee('wire:click="confirmDeleteJobSite"', false);

        Livewire::actingAs($member)
            ->test(\App\Livewire\JobSite\JobSiteOverview::class, ['jobSite' => $this->theirSite])
            ->call('deleteJobSite')
            ->assertForbidden();

        $this->assertNotNull(JobSite::find($this->theirSite->id));
    }

    public function test_an_administrator_still_has_both_delete_buttons(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.overview', $this->theirs))
            ->assertOk()
            ->assertSee('wire:click="confirmDeleteProject"', false);

        $this->actingAs($this->admin)
            ->get(route('jobsites.overview', $this->theirSite))
            ->assertOk()
            ->assertSee('wire:click="confirmDeleteJobSite"', false);
    }

    public function test_the_job_site_actions_behind_those_buttons_are_guarded_too(): void
    {
        // Hiding the button is not the guard: the wire:click behind it can be
        // invoked directly.
        $role = Role::create(['name' => 'reader-four']);
        $role->syncAbilities(['projects.view', 'project.view']);
        $reader = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($reader)
            ->test(\App\Livewire\Project\ProjectJobSites::class, ['project' => $this->theirs])
            ->call('openJobSiteForm')
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(\App\Livewire\Project\ProjectJobSites::class, ['project' => $this->theirs])
            ->call('editJobSite', $this->theirSite->id)
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The boundary of this pass
    |---------------------------------------------------------------------------
    */

    public function test_this_pass_does_not_decide_what_happens_inside_a_project(): void
    {
        // Deliberate, and recorded so nobody mistakes M2 for more than it is:
        // a member with nothing but project.view still reaches every tab of a
        // module that has not had its pass. M12, M14 and the rest close those.
        $member = $this->confinedMemberOf($this->theirs, ['project.view']);

        $this->actingAs($member)->get(route('projects.daily-reports', $this->theirs))->assertOk();

        // Expenses (M4), Budget (M6) and Change Orders (M10) no longer —
        // swept, and this membership grants none of them.
        $this->actingAs($member)->get(route('projects.expenses', $this->theirs))->assertForbidden();
        $this->actingAs($member)->get(route('projects.budget', $this->theirs))->assertForbidden();
        $this->actingAs($member)->get(route('projects.change-orders', $this->theirs))->assertForbidden();
        $this->actingAs($member)->get(route('projects.contracts', $this->theirs))->assertForbidden();
    }

    public function test_a_guest_reaches_their_project_and_nothing_else(): void
    {
        $template = PermissionTemplate::where('key', 'client-project')->first();

        $guest = $this->user('employee', ['is_guest' => true]);
        $membership = Membership::create([
            'user_id' => $guest->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->theirs->id,
            'permission_template_id' => $template->id,
            'can_see_money' => false,
        ]);
        $membership->syncAbilities($template->abilities());
        app(PermissionResolver::class)->flush();

        $this->actingAs($guest)->get(route('projects.overview', $this->theirs))->assertOk();
        $this->actingAs($guest)->get(route('projects.overview', $this->someoneElses))->assertForbidden();
        $this->actingAs($guest)->get(route('projects.index'))->assertForbidden();
    }
}
