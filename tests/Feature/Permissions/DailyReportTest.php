<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\DailyReport\DailyReportForm;
use App\Models\Client;
use App\Models\DailyReport;
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
 * M14 — Daily Reports.
 *
 * The site's diary, and the main screen of the two seeded templates that hold
 * almost nothing else. Every pass before this was about money and buying,
 * where **Site Supervisor** and the read-only **Client** guest hold little or
 * nothing; this is the first where their own screen is converted, so it is the
 * first real test that "invite the client and they see the diary and nothing
 * else" actually holds.
 */
class DailyReportTest extends TestCase
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
                ['company_name' => 'DR Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-dr@example.test',
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
            'email' => str($name)->slug().'-dr@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeReport(array $attributes = []): DailyReport
    {
        return DailyReport::create(array_merge([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'report_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
        ], $attributes));
    }

    /** Somebody added to one scope with one of the seeded templates. */
    protected function withTemplate(string $key, Project|JobSite $scope, bool $guest = false): User
    {
        $template = PermissionTemplate::where('key', $key)->firstOrFail();

        $user = $this->user('employee', [
            'access_scope' => AccessScope::ASSIGNED,
            'is_guest' => $guest,
        ]);

        if ($guest) {
            $user->forceFill(['role_id' => null])->save();
        }

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
            'permission_template_id' => $template->id,
            'can_see_money' => $template->can_see_money,
        ]);
        $membership->syncAbilities($template->abilities());

        app(PermissionResolver::class)->flush();

        return $user;
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

    public function test_the_daily_report_screens_answer_as_they_did_for_every_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.daily-reports', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.daily-reports', $this->site))->assertOk();
            $this->actingAs($user)->get(route('dailyreports.create', $this->site))->assertOk();
        }
    }

    public function test_seeing_the_diary_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.daily-reports', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.daily-reports', $this->site))->assertForbidden();
    }

    public function test_filing_and_correcting_are_separate_grants(): void
    {
        $report = $this->makeReport();
        $reader = $this->memberOf($this->site, ['daily-reports.view']);

        $this->actingAs($reader)->get(route('dailyreports.create', $this->site))->assertForbidden();
        $this->actingAs($reader)->get(route('dailyreports.edit', [$this->site, $report]))->assertForbidden();

        // Filing does not carry correcting somebody else's.
        $filer = $this->memberOf($this->site, ['daily-reports.view', 'daily-reports.create']);

        $this->actingAs($filer)->get(route('dailyreports.create', $this->site))->assertOk();
        $this->actingAs($filer)->get(route('dailyreports.edit', [$this->site, $report]))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | A report closes after seven days
    |---------------------------------------------------------------------------
    */

    public function test_a_closed_report_needs_its_own_grant_to_reopen(): void
    {
        $old = $this->makeReport(['report_date' => now()->subDays(30)->toDateString()]);

        $this->assertFalse($old->isEditable());

        $editor = $this->memberOf($this->site, ['daily-reports.view', 'daily-reports.edit']);

        Livewire::actingAs($editor)
            ->test(DailyReportForm::class, ['jobSite' => $this->site, 'dailyReport' => $old])
            ->set('tasks', [['description' => 'Late fix', 'images' => [], 'order' => 0]])
            ->call('save')
            ->assertForbidden();

        // The grant that reopens it.
        $auditor = $this->memberOf($this->site, [
            'daily-reports.view', 'daily-reports.edit', 'daily-reports.edit_locked',
        ]);

        Livewire::actingAs($auditor)
            ->test(DailyReportForm::class, ['jobSite' => $this->site, 'dailyReport' => $old])
            ->set('tasks', [['description' => 'Late fix', 'images' => [], 'order' => 0]])
            ->call('save')
            ->assertOk();
    }

    public function test_a_locked_report_is_closed_however_recent_it_is(): void
    {
        $locked = $this->makeReport(['locked_at' => now()]);

        $this->assertFalse($locked->isEditable());

        $editor = $this->memberOf($this->site, ['daily-reports.view', 'daily-reports.edit']);

        Livewire::actingAs($editor)
            ->test(DailyReportForm::class, ['jobSite' => $this->site, 'dailyReport' => $locked])
            ->set('tasks', [['description' => 'Change', 'images' => [], 'order' => 0]])
            ->call('save')
            ->assertForbidden();
    }

    public function test_no_seeded_role_or_template_may_reopen_a_closed_report(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $this->assertNotContains(
                'daily-reports.edit_locked',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
                $name,
            );
        }

        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('daily-reports.edit_locked', $template->abilities(), $template->key);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | The two personas this module exists for
    |---------------------------------------------------------------------------
    */

    public function test_a_site_supervisor_runs_the_diary_on_their_own_site_and_nowhere_else(): void
    {
        $supervisor = $this->withTemplate('site-supervisor', $this->site);

        // Their site: the diary works end to end.
        $this->actingAs($supervisor)->get(route('jobsites.daily-reports', $this->site))->assertOk();
        $this->actingAs($supervisor)->get(route('dailyreports.create', $this->site))->assertOk();

        // The project above it, and anywhere else, is not theirs.
        $this->actingAs($supervisor)->get(route('projects.daily-reports', $this->project))->assertForbidden();

        $elsewhere = $this->makeSite($this->otherProject, 'Site Z');
        $this->actingAs($supervisor)->get(route('jobsites.daily-reports', $elsewhere))->assertForbidden();

        // And they cannot reopen a closed one.
        $old = $this->makeReport(['report_date' => now()->subDays(30)->toDateString()]);

        Livewire::actingAs($supervisor)
            ->test(DailyReportForm::class, ['jobSite' => $this->site, 'dailyReport' => $old])
            ->set('tasks', [['description' => 'Late', 'images' => [], 'order' => 0]])
            ->call('save')
            ->assertForbidden();
    }

    public function test_a_client_guest_reads_the_diary_and_can_do_nothing_to_it(): void
    {
        $guest = $this->withTemplate('client-project', $this->project, guest: true);

        // They can follow the project's diary…
        $this->actingAs($guest)->get(route('projects.daily-reports', $this->project))->assertOk();

        // …and not file, correct, or reach another project's.
        $this->actingAs($guest)->get(route('dailyreports.project.create', $this->project))->assertForbidden();

        $report = $this->makeReport(['job_site_id' => null]);

        $this->actingAs($guest)
            ->get(route('dailyreports.project.edit', [$this->project, $report]))->assertForbidden();

        $this->actingAs($guest)
            ->get(route('projects.daily-reports', $this->otherProject))->assertForbidden();

        // The money screens stay shut, which is the whole point of a guest.
        $this->actingAs($guest)->get(route('projects.expenses', $this->project))->assertForbidden();
        $this->actingAs($guest)->get(route('projects.budget', $this->project))->assertForbidden();
    }

    public function test_a_guest_may_read_the_diary_pdf_but_not_another_projects(): void
    {
        $guest = $this->withTemplate('client-project', $this->project, guest: true);

        $ours = $this->makeReport();
        $theirs = $this->makeReport([
            'project_id' => $this->otherProject->id,
            'job_site_id' => null,
        ]);

        $this->actingAs($guest)->get(route('dailyreports.pdf.view', $ours))->assertSuccessful();
        $this->actingAs($guest)->get(route('dailyreports.pdf.view', $theirs))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | P22 — the PDF, and the photos
    |---------------------------------------------------------------------------
    */

    public function test_the_daily_report_pdf_is_no_longer_reachable_by_id(): void
    {
        $report = $this->makeReport();
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('dailyreports.pdf.view', $report))->assertForbidden();
        $this->actingAs($blind)->get(route('dailyreports.pdf.download', $report))->assertForbidden();

        $reader = $this->memberOf($this->site, ['daily-reports.view']);

        $this->actingAs($reader)->get(route('dailyreports.pdf.view', $report))->assertSuccessful();
    }

    public function test_a_report_of_another_project_is_refused_to_a_member_of_this_one(): void
    {
        $foreign = $this->makeReport([
            'project_id' => $this->otherProject->id,
            'job_site_id' => null,
        ]);

        $member = $this->memberOf($this->site, ['daily-reports.view']);

        $this->actingAs($member)->get(route('dailyreports.pdf.view', $foreign))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue and the seeds
    |---------------------------------------------------------------------------
    */

    public function test_the_seeded_templates_grant_the_expected_daily_report_actions(): void
    {
        $expected = [
            'project-manager' => [
                'daily-reports.view', 'daily-reports.create', 'daily-reports.edit',
            ],
            'site-supervisor' => [
                'daily-reports.view', 'daily-reports.create', 'daily-reports.edit',
            ],
            'site-team' => ['daily-reports.view', 'daily-reports.create'],
            'client-project' => ['daily-reports.view'],
            'accounting' => [],
        ];

        foreach ($expected as $key => $abilities) {
            $held = array_values(array_filter(
                PermissionTemplate::where('key', $key)->firstOrFail()->abilities(),
                fn ($a) => str_starts_with($a, 'daily-reports.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong daily report actions.");
        }
    }

    public function test_reopening_a_closed_report_is_marked_sensitive(): void
    {
        $this->assertTrue(
            \App\Services\AbilityCatalog::action('daily-reports.edit_locked')['sensitive'],
        );
    }
}
