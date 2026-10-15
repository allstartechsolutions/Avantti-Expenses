<?php

namespace Tests\Feature\Contract;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Livewire\PaymentBatch\PaymentBatchShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\JobSite;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PaymentBatchLayout;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A payment batch and /contract-payments can be grouped (client, project, job
 * site, subcontractor; status on a batch) and sorted by column; the PDF and the
 * CSV come out in the order the screen shows, and the contract's employee is
 * printed under the subcontractor everywhere.
 */
class PaymentBatchGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected PaymentBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->batch = PaymentBatch::create([
            'name' => 'Friday run',
            'status' => 'draft',
            'payment_date' => '2026-10-16',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function subcontractor(string $name): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => $name, 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return $vendor;
    }

    protected function site(string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str()->random(6).'@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function line(Vendor $vendor, ?JobSite $site, string $number, float $amount, array $contract = [], string $status = 'pending'): Contract
    {
        $made = Contract::create(array_merge([
            'project_id' => $this->project->id,
            'job_site_id' => $site?->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => $number,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 10000,
            'created_by' => $this->admin->id,
        ], $contract));

        PaymentBatchItem::create([
            'payment_batch_id' => $this->batch->id,
            'contract_id' => $made->id,
            'amount' => $amount,
            'payment_method' => 'check',
            'status' => $status,
        ]);

        return $made;
    }

    /** The three lines most tests use: two lots, two subcontractors, one employee. */
    protected function seedLines(): void
    {
        $wolf = $this->subcontractor('Wolf Framing');
        $acme = $this->subcontractor('Acme Drywall');
        $vinicius = SubcontractorEmployee::create(['subcontractor_id' => $wolf->id, 'name' => 'Vinicius Contact', 'title' => 'Framer']);

        $this->line($wolf, $this->site('Lot 10'), 'CTR-0003', 300, ['subcontractor_employee_id' => $vinicius->id]);
        $this->line($acme, $this->site('Lot 2'), 'CTR-0001', 100);
        $this->line($wolf, null, 'CTR-0002', 200);
    }

    public function test_the_lines_group_by_job_site_in_lot_order_with_project_general_last(): void
    {
        $this->seedLines();

        $groups = PaymentBatchLayout::lines($this->batch->fresh(), 'job_site', '', 'asc');

        $this->assertSame(['Lot 2', 'Lot 10', __('Project General')], $groups->pluck('label')->all());
        $this->assertSame([100.0, 300.0, 200.0], $groups->map(fn ($g) => $g['totals']['total_amount'])->all());
    }

    public function test_the_lines_group_by_subcontractor_and_sort_by_amount_inside_each_group(): void
    {
        $this->seedLines();

        $groups = PaymentBatchLayout::lines($this->batch->fresh(), 'subcontractor', 'amount', 'desc');

        $this->assertSame(['Acme Drywall', 'Wolf Framing'], $groups->pluck('label')->all());
        $this->assertSame(['CTR-0003', 'CTR-0002'], $groups[1]['rows']->pluck('contract.contract_number')->all());
        $this->assertSame(500.0, $groups[1]['totals']['total_amount']);
    }

    public function test_an_unknown_grouping_or_column_from_the_browser_falls_back_to_the_default_order(): void
    {
        $this->assertSame('', PaymentBatchLayout::cleanGroup('password'));
        $this->assertSame('', PaymentBatchLayout::cleanGroup(['job_site']));
        $this->assertSame('', PaymentBatchLayout::cleanGroup('status', withStatus: false));
        $this->assertSame('', PaymentBatchLayout::cleanLineSort('id; drop'));
        $this->assertSame('asc', PaymentBatchLayout::cleanDirection('sideways'));
    }

    public function test_the_show_screen_groups_sorts_and_names_the_employee(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchShow::class, ['paymentBatch' => $this->batch])
            ->assertSee('Vinicius Contact')
            ->set('groupBy', 'subcontractor')
            ->assertSeeInOrder(['Acme Drywall', 'CTR-0001', 'Wolf Framing', '$500.00', 'CTR-0003', 'CTR-0002'])
            ->call('sort', 'contract')
            ->assertSeeInOrder(['Wolf Framing', 'CTR-0002', 'CTR-0003'])
            ->assertSee('group=subcontractor', false)
            ->assertSee('sort=contract', false);
    }

    public function test_the_csv_comes_out_in_the_screen_order_with_the_employee_column(): void
    {
        $this->seedLines();

        $screen = Livewire::actingAs($this->admin)
            ->test(PaymentBatchShow::class, ['paymentBatch' => $this->batch])
            ->set('groupBy', 'job_site')
            ->call('exportCsv');

        $csv = base64_decode(data_get($screen->effects, 'download.content'));

        $this->assertStringContainsString('Contact (Employee)', $csv);
        $this->assertMatchesRegularExpression('/"?Wolf Framing"?,"?Vinicius Contact"?/', $csv);
        $this->assertMatchesRegularExpression('/Lot 2.*CTR-0001.*Subtotal.*Lot 10.*CTR-0003.*'.preg_quote(__('Project General'), '/').'.*CTR-0002/s', $csv);
    }

    public function test_the_pdf_takes_the_grouping_and_prints_the_employee(): void
    {
        $this->seedLines();

        $this->actingAs($this->admin)
            ->get(route('payment-batches.pdf.view', ['paymentBatch' => $this->batch->id, 'group' => 'job_site', 'sort' => 'amount', 'dir' => 'desc']))
            ->assertOk();

        $groups = PaymentBatchLayout::lines($this->batch->fresh(), 'job_site', 'amount', 'desc');
        $html = view('pdf.payment-batch', [
            'batch' => $this->batch->fresh(['createdBy', 'approvedBy']),
            'items' => $groups->flatMap(fn ($g) => $g['rows']),
            'groups' => $groups,
            'groupLabel' => 'Job Site',
            'summary' => PaymentBatchLayout::lineTotals($groups->flatMap(fn ($g) => $g['rows'])),
            'company' => null,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Vinicius Contact (Framer)', $html);
        $this->assertMatchesRegularExpression('/Lot 2.*CTR-0001.*Lot 10.*CTR-0003.*'.preg_quote(__('Project General'), '/').'.*CTR-0002/s', $html);
    }

    public function test_the_edit_screen_groups_the_contracts_with_whole_group_totals(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->set('groupBy', 'subcontractor')
            ->assertSeeInOrder(['Acme Drywall', '1 contract', 'CTR-0001', 'Wolf Framing', '2 contracts', '$20,000.00'])
            ->call('sort', 'contract')
            ->assertSeeInOrder(['Wolf Framing', 'CTR-0002', 'CTR-0003'])
            ->set('groupBy', 'status')
            ->assertSet('groupBy', '');
    }

    public function test_the_edit_screen_sorts_by_subcontractor(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('sort', 'subcontractor')
            ->assertSeeInOrder(['CTR-0001', 'Wolf Framing'])
            ->call('sort', 'subcontractor')
            ->assertSeeInOrder(['Wolf Framing', 'Acme Drywall']);
    }

    public function test_contract_payments_groups_with_whole_group_totals_and_sorts_by_subcontractor(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->set('groupBy', 'subcontractor')
            ->assertSeeInOrder(['Acme Drywall', '1 contract', 'CTR-0001', 'Wolf Framing', '2 contracts', '$20,000.00', 'CTR-0003'])
            ->call('sort', 'contract')
            ->assertSeeInOrder(['Wolf Framing', 'CTR-0002', 'CTR-0003'])
            ->set('groupBy', '')
            ->call('sort', 'subcontractor')
            ->assertSeeInOrder(['CTR-0001', 'Wolf Framing']);
    }

    public function test_contract_payments_csv_follows_the_screen_and_names_the_employee(): void
    {
        $this->seedLines();

        $screen = Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->set('groupBy', 'job_site')
            ->call('exportCsv');

        $csv = base64_decode(data_get($screen->effects, 'download.content'));

        $this->assertMatchesRegularExpression('/"?Wolf Framing"?,"?Vinicius Contact"?/', $csv);
        $this->assertMatchesRegularExpression('/Group,"?Lot 2.*CTR-0001.*Group,"?Lot 10.*CTR-0003.*Group,.*CTR-0002/s', $csv);
    }

    public function test_contract_payments_pdf_takes_the_grouping_and_the_new_sort_columns(): void
    {
        $this->seedLines();

        foreach (['subcontractor', 'project'] as $sort) {
            $this->actingAs($this->admin)
                ->get(route('contract-payments.pdf.view', ['group' => 'subcontractor', 'sort' => $sort, 'dir' => 'desc']))
                ->assertOk();
        }
    }
}
