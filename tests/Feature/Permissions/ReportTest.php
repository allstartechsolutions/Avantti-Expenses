<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
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
use Tests\TestCase;

/**
 * M17 — Reports.
 *
 * Two different shapes under one pass.
 *
 * The **six company reports** were behind the `admin` middleware. Each now has
 * its own grant, so an accountant can be given Sales Tax and Accounts Payable
 * without being given Company Financials — which is the one that shows what the
 * company is worth.
 *
 * The **project and job-site financial reports** belong to a project, so they
 * are scoped like every other tab, and their PDFs need `export` rather than
 * `view` — printing a project's finances and sending them on is a further act.
 *
 * This pass also fixes P28: the payment-schedule projection used a MySQL-only
 * `DATE_FORMAT`, so the project financial report 500'd on sqlite and neither it
 * nor the payment schedule had ever been rendered by a test.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    /** Route name => the ability it now answers to. */
    protected const COMPANY_REPORTS = [
        'reports.company-financials' => 'reports.company_financials',
        'reports.sales-tax' => 'reports.sales_tax',
        'reports.expenses' => 'reports.expenses',
        'reports.payment-schedule' => 'reports.payment_schedule',
        'reports.accounts-payable' => 'reports.accounts_payable',
        'reports.payment-details' => 'reports.payment_details',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
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
                ['company_name' => 'Report Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-rp@example.test',
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
            'email' => str($name)->slug().'-rp@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
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
    | P28 — the screens that had never been rendered
    |---------------------------------------------------------------------------
    */

    public function test_the_project_financial_report_renders_at_all(): void
    {
        // It could not be, before this pass: the payment-schedule projection
        // called MySQL-only DATE_FORMAT and the screen 500'd on sqlite.
        $this->actingAs($this->admin)
            ->get(route('projects.report', $this->project))->assertOk();

        $this->actingAs($this->admin)
            ->get(route('jobsites.report', $this->site))->assertOk();
    }

    public function test_the_payment_schedule_report_renders_at_all(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.payment-schedule'))->assertOk();
    }

    /*
    |---------------------------------------------------------------------------
    | The six company reports
    |---------------------------------------------------------------------------
    */

    public function test_the_company_reports_stay_administrator_only(): void
    {
        foreach (array_keys(self::COMPANY_REPORTS) as $route) {
            $this->actingAs($this->user('admin'))->get(route($route))->assertOk();
            $this->actingAs($this->user('manager'))->get(route($route))->assertForbidden();
            $this->actingAs($this->user('employee'))->get(route($route))->assertForbidden();
        }
    }

    public function test_each_report_is_its_own_grant(): void
    {
        foreach (self::COMPANY_REPORTS as $route => $ability) {
            $holder = $this->roleWith(['projects.view', 'project.view', $ability]);

            $this->actingAs($holder)->get(route($route))->assertOk();

            // …and holding one gives none of the others.
            foreach (self::COMPANY_REPORTS as $otherRoute => $otherAbility) {
                if ($otherRoute === $route) {
                    continue;
                }

                $this->actingAs($holder)->get(route($otherRoute))->assertForbidden();
            }
        }
    }

    public function test_an_accountant_can_be_given_the_tax_reports_without_the_company_accounts(): void
    {
        // The point of splitting them: Company Financials shows what the
        // business is worth, and is the one marked sensitive.
        $accountant = $this->roleWith([
            'projects.view', 'project.view',
            'reports.sales_tax', 'reports.accounts_payable', 'reports.payment_details',
        ]);

        $this->actingAs($accountant)->get(route('reports.sales-tax'))->assertOk();
        $this->actingAs($accountant)->get(route('reports.accounts-payable'))->assertOk();
        $this->actingAs($accountant)->get(route('reports.company-financials'))->assertForbidden();

        $this->assertTrue(
            \App\Services\AbilityCatalog::action('reports.company_financials')['sensitive'],
        );
    }

    public function test_a_reports_pdf_answers_to_the_same_grant_as_its_screen(): void
    {
        // P22: a PDF of a report somebody may not open is the same disclosure
        // by another door.
        $blind = $this->roleWith(['projects.view', 'project.view']);

        foreach ([
            'reports.company-financials.pdf.view',
            'reports.expenses.pdf.view',
            'reports.payment-schedule.pdf.view',
            'reports.accounts-payable.pdf.view',
            'reports.payment-details.pdf.view',
        ] as $route) {
            $this->actingAs($blind)->get(route($route))->assertForbidden();
        }

        $holder = $this->roleWith(['projects.view', 'project.view', 'reports.expenses']);

        $this->actingAs($holder)->get(route('reports.expenses.pdf.view'))->assertSuccessful();
        $this->actingAs($holder)->get(route('reports.payment-details.pdf.view'))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The project's own financial report
    |---------------------------------------------------------------------------
    */

    public function test_the_project_report_is_scoped_like_every_other_tab(): void
    {
        $other = $this->makeProject('Elsewhere');

        $member = $this->memberOf($this->project, ['project-report.view']);

        $this->actingAs($member)->get(route('projects.report', $this->project))->assertOk();
        $this->actingAs($member)->get(route('projects.report', $other))->assertForbidden();
    }

    public function test_seeing_the_project_report_is_a_grant_of_its_own(): void
    {
        // A member of the project, holding everything else, still cannot open
        // its financial report.
        $member = $this->memberOf($this->project, [
            'expenses.view', 'income.view', 'budget.view', 'contracts.view',
        ]);

        $this->actingAs($member)->get(route('projects.report', $this->project))->assertForbidden();
        $this->actingAs($member)->get(route('jobsites.report', $this->site))->assertForbidden();
    }

    public function test_printing_the_project_report_needs_export_and_not_only_view(): void
    {
        // Reading a project's finances on screen and sending the PDF on are
        // different acts.
        $reader = $this->memberOf($this->project, ['project-report.view']);

        $this->actingAs($reader)->get(route('projects.report', $this->project))->assertOk();
        $this->actingAs($reader)->get(route('projects.report.pdf.view', $this->project))->assertForbidden();

        $exporter = $this->memberOf($this->project, ['project-report.view', 'project-report.export']);

        $this->actingAs($exporter)->get(route('projects.report.pdf.view', $this->project))->assertSuccessful();
    }

    public function test_the_job_site_report_follows_its_own_membership(): void
    {
        // Granted on the job site alone: the site's report opens, the
        // project's does not.
        $siteMember = $this->memberOf($this->site, ['project-report.view']);

        $this->actingAs($siteMember)->get(route('jobsites.report', $this->site))->assertOk();
        $this->actingAs($siteMember)->get(route('projects.report', $this->project))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The seeds and the menu
    |---------------------------------------------------------------------------
    */

    public function test_the_reports_menu_follows_the_grants(): void
    {
        $taxOnly = $this->roleWith(['projects.view', 'project.view', 'reports.sales_tax']);
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($taxOnly)->get(route('projects.index'))
            ->assertOk()
            ->assertSee(route('reports.sales-tax'))
            ->assertDontSee(route('reports.company-financials'));

        $this->actingAs($blind)->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee(route('reports.sales-tax'));
    }

    public function test_no_seeded_role_below_administrator_holds_a_company_report(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $held = Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all();

            foreach (array_values(self::COMPANY_REPORTS) as $ability) {
                $this->assertNotContains($ability, $held, "{$name} holds {$ability}");
            }
        }
    }

    public function test_the_templates_grant_the_project_report_where_they_should(): void
    {
        $expected = [
            'project-manager' => ['project-report.view', 'project-report.export'],
            'accounting' => ['project-report.view', 'project-report.export'],
            'site-supervisor' => [],
            'client-project' => [],
        ];

        foreach ($expected as $key => $abilities) {
            $held = array_values(array_filter(
                PermissionTemplate::where('key', $key)->firstOrFail()->abilities(),
                fn ($a) => str_starts_with($a, 'project-report.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong report actions.");
        }
    }
}
