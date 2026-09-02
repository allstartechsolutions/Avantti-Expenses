<?php

namespace Tests\Feature;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\JobSiteFinancialReportPdfController;
use App\Http\Controllers\ProjectFinancialReportPdfController;
use App\Livewire\JobSite\JobSiteFinancialReport;
use App\Livewire\JobSite\JobSiteOverview;
use App\Livewire\Project\ProjectFinancialReport;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Only an approved change order revises the contract value.
 *
 * Until 1 Sep 2026 every change order counted towards it whatever its status,
 * so a rejected one — an offer the client turned down — still moved the profit
 * figure, and so did a draft nobody had finished writing. The cost side had
 * always waited for approval; now both halves land together.
 *
 * Rows that existed before the status column took the `approved` default, so
 * nothing historical moved when this shipped.
 */
class ChangeOrderContractValueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Contract Value Client',
            'contact_name' => 'C',
            'email' => 'cv-client@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Contract Value',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'cv@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
            'initial_amount' => 100000,
            'amount_source' => 'manual',
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Site A',
            'contact_person' => 'C',
            'email' => 'cv-site@example.test',
            'status' => JobSiteStatus::IN_PROGRESS,
            'created_by' => $this->admin->id,
            'job_amount' => 50000,
        ]);
    }

    private function changeOrder(string $status, float $amount, ?JobSite $site = null): ChangeOrder
    {
        return ChangeOrder::create([
            'project_id' => $this->project->id,
            'job_site_id' => $site?->id,
            'title' => ucfirst($status).' '.$amount,
            'requested_date' => now()->toDateString(),
            'status' => $status,
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | The rule itself
    |---------------------------------------------------------------------------
    */

    public function test_only_approved_change_orders_move_the_project_contract_value(): void
    {
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, 10000);
        $this->changeOrder(ChangeOrder::STATUS_PENDING, 7000);
        $this->changeOrder(ChangeOrder::STATUS_DRAFT, 3000);
        $this->changeOrder(ChangeOrder::STATUS_REJECTED, 5000);

        $project = $this->project->fresh();

        $this->assertSame(100000.0, $project->getContractValue());
        $this->assertSame(10000.0, $project->getApprovedChangeOrdersTotal());
        $this->assertSame(110000.0, $project->getAdjustedContractValue());

        // Draft + pending are held back together; the rejected one is in neither.
        $this->assertSame(10000.0, $project->getPendingChangeOrdersTotal());
    }

    public function test_only_approved_change_orders_move_the_job_site_contract_value(): void
    {
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, 8000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_PENDING, 4000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_REJECTED, 6000, $this->site);

        $site = $this->site->fresh();

        $this->assertSame(50000.0, $site->getContractValue());
        $this->assertSame(8000.0, $site->getApprovedChangeOrdersTotal());
        $this->assertSame(58000.0, $site->getAdjustedContractValue());
        $this->assertSame(4000.0, $site->getPendingChangeOrdersTotal());
    }

    public function test_a_rejected_change_order_no_longer_moves_the_profit(): void
    {
        // The reproduction: approve, read the profit, reject, read it again.
        $co = $this->changeOrder(ChangeOrder::STATUS_APPROVED, -10000, $this->site);

        $approvedProfit = Livewire::actingAs($this->admin)
            ->test(JobSiteFinancialReport::class, ['jobSite' => $this->site])
            ->viewData('financials')['profit'];

        $this->assertSame(40000.0, $approvedProfit);

        $co->reject($this->admin);

        $rejectedProfit = Livewire::actingAs($this->admin)
            ->test(JobSiteFinancialReport::class, ['jobSite' => $this->site->fresh()])
            ->viewData('financials')['profit'];

        $this->assertSame(50000.0, $rejectedProfit, 'A rejected change order must not move the profit.');
    }

    public function test_an_approved_deductive_change_order_still_reduces_the_profit(): void
    {
        // The sign must keep working — this is what the rule must not break.
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, -15000, $this->site);

        $financials = Livewire::actingAs($this->admin)
            ->test(JobSiteFinancialReport::class, ['jobSite' => $this->site])
            ->viewData('financials');

        $this->assertSame(-15000.0, $financials['change_orders_total']);
        $this->assertSame(35000.0, $financials['contract_value']);
        $this->assertSame(35000.0, $financials['profit']);
    }

    /*
    |---------------------------------------------------------------------------
    | What is held back is reported, not dropped
    |---------------------------------------------------------------------------
    */

    public function test_the_reports_account_for_what_they_do_not_count(): void
    {
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, 10000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_PENDING, 7000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_REJECTED, 5000, $this->site);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectFinancialReport::class, ['project' => $this->project]);

        $financials = $component->viewData('financials');
        $revenue = $component->viewData('revenueDetail');

        $this->assertSame(10000.0, $financials['change_orders_total']);
        $this->assertSame(7000.0, $financials['pending_change_orders_total']);

        // Counted once, listed once — and the two lists together hold every record.
        $this->assertCount(1, $revenue['change_orders']);
        $this->assertCount(2, $revenue['uncounted_change_orders']);
        $this->assertSame(
            ChangeOrder::count(),
            count($revenue['change_orders']) + count($revenue['uncounted_change_orders']),
        );
    }

    public function test_the_job_site_overview_separates_approved_from_awaiting(): void
    {
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, 10000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_PENDING, 7000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_REJECTED, 5000, $this->site);

        $component = Livewire::actingAs($this->admin)
            ->test(JobSiteOverview::class, ['jobSite' => $this->site]);

        $this->assertSame(10000.0, $component->viewData('totalChangeOrdersAmount'));
        $this->assertSame(7000.0, $component->viewData('pendingChangeOrdersAmount'));
        $this->assertSame(60000.0, $component->viewData('contractValue'));
        $this->assertSame(1, $component->viewData('approvedChangeOrdersCount'));
        $this->assertSame(1, $component->viewData('pendingChangeOrdersCount'));

        // Every change order still appears in the list below the cards.
        $this->assertCount(3, $component->viewData('changeOrders'));
    }

    /*
    |---------------------------------------------------------------------------
    | The PDFs must not disagree with the screens
    |---------------------------------------------------------------------------
    */

    public function test_the_pdfs_report_the_same_figures_as_the_screens(): void
    {
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, -10000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_PENDING, 7000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_REJECTED, 5000);

        $this->actingAs($this->admin);

        $screen = Livewire::actingAs($this->admin)
            ->test(ProjectFinancialReport::class, ['project' => $this->project])
            ->viewData('financials');

        $pdf = $this->pdfFinancials(ProjectFinancialReportPdfController::class, $this->project);

        foreach (['base_contract_value', 'change_orders_total', 'pending_change_orders_total', 'contract_value', 'profit'] as $key) {
            $this->assertSame($screen[$key], $pdf[$key], "Project PDF disagrees with the screen on {$key}.");
        }

        $siteScreen = Livewire::actingAs($this->admin)
            ->test(JobSiteFinancialReport::class, ['jobSite' => $this->site])
            ->viewData('financials');

        $sitePdf = $this->pdfFinancials(JobSiteFinancialReportPdfController::class, $this->site->fresh());

        foreach (['base_contract_value', 'change_orders_total', 'pending_change_orders_total', 'contract_value', 'profit'] as $key) {
            $this->assertSame($siteScreen[$key], $sitePdf[$key], "Job site PDF disagrees with the screen on {$key}.");
        }
    }

    public function test_the_pdf_templates_render_with_change_orders_in_every_state(): void
    {
        $this->changeOrder(ChangeOrder::STATUS_APPROVED, -10000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_PENDING, 7000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_DRAFT, 3000, $this->site);
        $this->changeOrder(ChangeOrder::STATUS_REJECTED, 5000, $this->site);

        $this->actingAs($this->admin);

        $project = view('pdf.project-financial-report',
            $this->pdfData(ProjectFinancialReportPdfController::class, $this->project))->render();
        $site = view('pdf.job-site-financial-report',
            $this->pdfData(JobSiteFinancialReportPdfController::class, $this->site))->render();

        foreach (['project' => $project, 'job site' => $site] as $which => $html) {
            $this->assertStringContainsString(__('Approved Change Orders'), $html, "The {$which} PDF lost its approved section.");
            $this->assertStringContainsString(__('Not Counted'), $html, "The {$which} PDF does not account for what it left out.");
            $this->assertStringContainsString(__('Rejected'), $html, "The {$which} PDF does not say why a change order was left out.");
        }
    }

    /** The PDF controllers build their figures in a private method; read it directly. */
    private function pdfFinancials(string $controller, $scope): array
    {
        return $this->pdfData($controller, $scope)['financials'];
    }

    private function pdfData(string $controller, $scope): array
    {
        $method = new \ReflectionMethod($controller, 'buildPdfData');
        $method->setAccessible(true);

        return $method->invoke(app($controller), $scope);
    }
}
