<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectTasks;
use App\Livewire\Task\MyTasks;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M13 — Tasks & Meetings.
 *
 * Two things make this pass different from the twelve before it.
 *
 * **A task's scope is not the screen's.** My Tasks is a cross-project list, so
 * every grant is asked about the *task* rather than the page, and the list is
 * filtered rather than guarded — there is no route to hang a scope on.
 *
 * **A meeting has no project at all.** It spans several through its items, so
 * its grants are asked without a scope; for somebody confined the resolver
 * answers from their memberships taken together.
 */
class TaskMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Project $otherProject;

    protected JobSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
        $this->otherProject = $this->makeProject('Theirs');
        $this->site = $this->makeSite($this->project, 'Site A');
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
                ['company_name' => 'Task Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-tk@example.test',
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
            'email' => str($name)->slug().'-tk@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeTask(array $attributes = []): Task
    {
        return Task::create(array_merge([
            'uuid' => (string) str()->uuid(),
            'title' => 'Fix the gate',
            'project_id' => $this->project->id,
            'owner_id' => $this->admin->id,
            'priority' => 'normal',
            'status' => 'open',
            'progress' => 0,
        ], $attributes));
    }

    protected function memberOf(Project|JobSite $scope, array $abilities): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
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

    public function test_the_task_screens_answer_as_they_did_for_every_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('tasks.mine'))->assertOk();
            $this->actingAs($user)->get(route('projects.tasks', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.tasks', $this->site))->assertOk();
        }
    }

    public function test_seeing_tasks_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('tasks.mine'))->assertForbidden();
        $this->actingAs($blind)->get(route('projects.tasks', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.tasks', $this->site))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | A task's scope is the task's, not the screen's
    |---------------------------------------------------------------------------
    */

    public function test_a_task_on_another_project_is_refused_even_from_my_tasks(): void
    {
        $foreign = $this->makeTask(['project_id' => $this->otherProject->id]);

        $member = $this->memberOf($this->project, ['tasks.view', 'tasks.edit']);

        foreach (['viewTask', 'editTask'] as $action) {
            Livewire::actingAs($member)
                ->test(MyTasks::class)
                ->call($action, $foreign->id)
                ->assertForbidden();
        }
    }

    public function test_my_tasks_shows_only_the_projects_a_confined_person_holds(): void
    {
        $member = $this->memberOf($this->project, ['tasks.view']);

        $here = $this->makeTask(['owner_id' => $member->id, 'title' => 'Ours']);
        $there = $this->makeTask([
            'owner_id' => $member->id,
            'project_id' => $this->otherProject->id,
            'title' => 'Theirs',
        ]);
        $personal = $this->makeTask([
            'owner_id' => $member->id,
            'project_id' => null,
            'title' => 'Personal',
        ]);

        $this->actingAs($member);

        $visible = Task::visibleTo($member)->forUser($member->id)->pluck('title')->all();

        $this->assertContains('Ours', $visible);
        $this->assertContains('Personal', $visible);   // belongs to no project
        $this->assertNotContains('Theirs', $visible);
    }

    public function test_a_company_wide_person_sees_every_task_they_are_on(): void
    {
        $employee = $this->user('employee');

        $this->makeTask(['owner_id' => $employee->id, 'title' => 'Ours']);
        $this->makeTask([
            'owner_id' => $employee->id,
            'project_id' => $this->otherProject->id,
            'title' => 'Theirs',
        ]);

        $this->actingAs($employee);

        $visible = Task::visibleTo($employee)->forUser($employee->id)->pluck('title')->all();

        $this->assertContains('Ours', $visible);
        $this->assertContains('Theirs', $visible);
    }

    /*
    |---------------------------------------------------------------------------
    | The task grants, one at a time
    |---------------------------------------------------------------------------
    */

    public function test_creating_editing_closing_and_deleting_are_separate_grants(): void
    {
        $task = $this->makeTask();
        $reader = $this->memberOf($this->project, ['tasks.view']);

        Livewire::actingAs($reader)
            ->test(ProjectTasks::class, ['project' => $this->project])
            ->call('openTaskForm')
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(ProjectTasks::class, ['project' => $this->project])
            ->call('editTask', $task->id)
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(ProjectTasks::class, ['project' => $this->project])
            ->call('deleteTask', $task->id)
            ->assertForbidden();

        $this->assertNotNull($task->fresh());
    }

    public function test_confirming_a_task_is_finished_is_its_own_grant(): void
    {
        $task = $this->makeTask(['status' => 'ready']);

        // The doer may mark it ready and still not confirm it.
        $doer = $this->memberOf($this->project, ['tasks.view', 'tasks.edit']);

        Livewire::actingAs($doer)
            ->test(ProjectTasks::class, ['project' => $this->project])
            ->call('confirmTaskCompletion', $task->id)
            ->assertForbidden();

        $closer = $this->memberOf($this->project, ['tasks.view', 'tasks.close']);

        Livewire::actingAs($closer)
            ->test(ProjectTasks::class, ['project' => $this->project])
            ->call('confirmTaskCompletion', $task->id)
            ->assertOk();
    }

    public function test_a_task_attachment_can_no_longer_be_fetched_by_id(): void
    {
        // Until M13 downloadTaskFile had no check of any kind. It now answers
        // from the task the file hangs on.
        $reader = $this->memberOf($this->project, ['tasks.view']);

        try {
            Livewire::actingAs($reader)
                ->test(ProjectTasks::class, ['project' => $this->project])
                ->call('downloadTaskFile', 99999);

            $this->fail('downloadTaskFile accepted an id that does not exist.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // Right answer: the lookup fails before anything is served.
        }

        // And the guard itself is on the method, reading the task behind the
        // file rather than trusting the id.
        $this->assertStringContainsString(
            "authorizeAbility('tasks.view', \$this->taskBehind(\$file))",
            file_get_contents(base_path('app/Livewire/Concerns/ManagesTasks.php')),
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Meetings — no project of their own
    |---------------------------------------------------------------------------
    */

    public function test_the_meeting_screens_answer_as_they_did_for_every_role(): void
    {
        // Reading was open to any signed-in user; running one was manager and
        // above. Both reproduced.
        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->user($role))->get(route('meetings.index'))->assertOk();
        }

        $this->actingAs($this->user('manager'))->get(route('meetings.create'))->assertOk();
        $this->actingAs($this->user('employee'))->get(route('meetings.create'))->assertForbidden();
    }

    public function test_the_meeting_series_screen_is_manager_and_above_as_before(): void
    {
        $this->actingAs($this->user('admin'))->get(route('meeting-series.index'))->assertOk();
        $this->actingAs($this->user('manager'))->get(route('meeting-series.index'))->assertOk();
        $this->actingAs($this->user('employee'))->get(route('meeting-series.index'))->assertForbidden();
    }

    public function test_managing_the_series_is_a_grant_rather_than_a_role_check(): void
    {
        // The difference M13 makes: somebody who is neither can be given it.
        $keeper = $this->roleWith(['projects.view', 'project.view', 'meetings.manage_series']);

        $this->actingAs($keeper)->get(route('meeting-series.index'))->assertOk();

        // …and a manager can have it taken away.
        $stripped = $this->roleWith(['projects.view', 'project.view', 'meetings.view']);

        $this->actingAs($stripped)->get(route('meeting-series.index'))->assertForbidden();
    }

    public function test_seeing_meetings_is_a_grant(): void
    {
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($blind)->get(route('meetings.index'))->assertForbidden();
    }

    public function test_the_minute_pdf_is_no_longer_reachable_without_the_grant(): void
    {
        // P22's first instalment: every PDF controller in the app is `auth`
        // only. This one is not any more.
        $blind = $this->roleWith(['projects.view', 'project.view']);
        $reader = $this->roleWith(['projects.view', 'project.view', 'meetings.view']);

        $meeting = \App\Models\Meeting::create([
            'number' => 'MIN-0001',
            'title' => 'Weekly',
            'meeting_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($blind)
            ->get(route('meetings.minute.pdf.view', $meeting))->assertForbidden();

        // The reader gets past the guard — what the controller then does with
        // a draft is the module's own business.
        $this->actingAs($reader)
            ->get(route('meetings.minute.pdf.view', $meeting))->assertSuccessful();
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue and the seeds
    |---------------------------------------------------------------------------
    */

    public function test_no_role_check_survives_in_the_module(): void
    {
        foreach ([
            'app/Livewire/Concerns/ManagesTasks.php',
            'app/Livewire/Task/MyTasks.php',
            'app/Livewire/Meeting/MeetingForm.php',
            'app/Livewire/Meeting/MeetingAgenda.php',
            'app/Livewire/Meeting/MeetingIndex.php',
            'app/Livewire/Meeting/MeetingShow.php',
            'app/Livewire/Meeting/MeetingSeriesIndex.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('is_admin', $source, $file);
            $this->assertStringNotContainsString('is_manager', $source, $file);
        }
    }

    public function test_the_seeded_templates_grant_the_expected_task_actions(): void
    {
        $expected = [
            'project-manager' => ['tasks.view', 'tasks.create', 'tasks.edit', 'tasks.close'],
            'procurement' => ['tasks.view'],
            'client-project' => ['tasks.view'],
            'site-supervisor' => ['tasks.view', 'tasks.create', 'tasks.edit', 'tasks.close'],
        ];

        foreach ($expected as $key => $abilities) {
            $held = array_values(array_filter(
                PermissionTemplate::where('key', $key)->firstOrFail()->abilities(),
                fn ($a) => str_starts_with($a, 'tasks.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong task actions.");
        }
    }

    public function test_publishing_and_deleting_a_series_are_held_back_from_an_employee(): void
    {
        $employee = Role::where('name', 'employee')->firstOrFail()->abilityRows()->pluck('ability')->all();
        $manager = Role::where('name', 'manager')->firstOrFail()->abilityRows()->pluck('ability')->all();

        foreach (['meetings.create', 'meetings.edit', 'meetings.freeze', 'meetings.manage_series'] as $ability) {
            $this->assertNotContains($ability, $employee, $ability);
            $this->assertContains($ability, $manager, $ability);
        }

        // Deleting a series was administrator-only and stays that way.
        $this->assertNotContains('meetings.delete', $manager);
    }
}
