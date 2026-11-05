<?php

namespace Tests\Feature;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectAmountSource;
use App\Enums\ProjectStatus;
use App\Livewire\Equipment\EquipmentShow;
use App\Livewire\PaymentBatch\PaymentBatchIndex;
use App\Livewire\Project\ProjectIndex;
use App\Livewire\Project\ProjectOverview;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobSite;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorDocument;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The five findings of the Codex review of the Nov 2026 change requests,
 * each pinned so it cannot come back.
 */
class CodexReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::IN_PROGRESS,
            'amount_source' => ProjectAmountSource::FROM_JOBSITES,
            'created_by' => $this->admin->id,
        ]);

        $lot = JobSite::create([
            'project_id' => $this->project->id, 'job_site_name' => 'Lot 12', 'contact_person' => 'C',
            'email' => 'lot@example.test', 'job_amount' => 100000, 'status' => JobSiteStatus::IN_PROGRESS, 'created_by' => $this->admin->id,
        ]);

        Expense::create([
            'project_id' => $this->project->id, 'job_site_id' => $lot->id, 'item_name' => 'Lumber', 'expense_date' => now()->toDateString(),
            'total_amount' => 4321, 'status' => 'paid', 'total_installments' => 1, 'created_by' => $this->admin->id,
        ]);
    }

    private function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'r-'.str()->random(6)]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function vendor(bool $sub, bool $supplier): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'V '.str()->random(4), 'is_subcontractor' => $sub, 'is_supplier' => $supplier, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return $vendor;
    }

    /** P1: the job site figures need the grants of the modules they sum. */
    public function test_job_site_figures_need_expenses_and_contracts_access(): void
    {
        $reader = $this->roleWith(['projects.view', 'project.view', 'finance.view_amounts']);

        Livewire::actingAs($reader)
            ->test(ProjectOverview::class, ['project' => $this->project])
            ->assertSee(__('Total Contract Value'))
            ->assertDontSee(__('Total Expenses'))
            ->assertDontSee('$4,321.00')
            ->assertDontSee(__('Profit or loss by lot'));

        Livewire::actingAs($reader)->test(ProjectIndex::class)->assertDontSee('$4,321.00');

        $both = $this->roleWith(['projects.view', 'project.view', 'finance.view_amounts', 'expenses.view', 'contracts.view']);
        Livewire::actingAs($both)
            ->test(ProjectOverview::class, ['project' => $this->project])
            ->assertSee(__('Profit or loss by lot'))
            ->assertSee('$4,321.00');
    }

    /** P2: a supplier-only vendor's documents download. */
    public function test_a_supplier_only_document_downloads(): void
    {
        Storage::fake('local');
        $supplier = $this->vendor(sub: false, supplier: true);
        Storage::disk('local')->put('subcontractor-documents/x/w9.pdf', 'pdf');
        $doc = SubcontractorDocument::create([
            'subcontractor_id' => $supplier->id, 'document_type_id' => DocumentType::create(['name' => 'W9', 'requires_expiration' => false])->id,
            'file_path' => 'subcontractor-documents/x/w9.pdf', 'file_name' => 'w9.pdf', 'uploaded_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get($doc->downloadUrl())->assertOk();

        // Another vendor in the URL is still a 404.
        $other = $this->vendor(sub: true, supplier: false);
        $this->actingAs($this->admin)->get(route('subcontractors.documents.download', [$other->id, $doc->id]))->assertNotFound();
    }

    /** P2: supplier documents are watched for expiry; a vendor that is neither is not. */
    public function test_supplier_documents_are_watched_for_expiry(): void
    {
        $coi = DocumentType::create(['name' => 'COI', 'requires_expiration' => true, 'expiry_warning_days' => 30]);
        $make = fn (Vendor $v) => SubcontractorDocument::create([
            'subcontractor_id' => $v->id, 'document_type_id' => $coi->id, 'file_path' => 'x.pdf', 'file_name' => 'x.pdf',
            'expiration_date' => now()->addDays(10)->toDateString(), 'uploaded_by' => $this->admin->id,
        ]);

        $supplierDoc = $make($this->vendor(sub: false, supplier: true));
        $subDoc = $make($this->vendor(sub: true, supplier: false));
        $retiredDoc = $make($this->vendor(sub: false, supplier: false));

        $watched = SubcontractorDocument::requiringExpiry()->pluck('id')->all();
        $this->assertContains($supplierDoc->id, $watched);
        $this->assertContains($subDoc->id, $watched);
        $this->assertNotContains($retiredDoc->id, $watched);
    }

    /** P2: "Not paid yet" on a machine is what is still owed, installments included. */
    public function test_equipment_unpaid_counts_only_the_unpaid_part_of_installments(): void
    {
        $machine = Equipment::create(['name' => 'Forklift', 'equipment_type' => 'machinery', 'ownership' => 'owned', 'meter_type' => 'hours', 'status' => 'active', 'created_by' => $this->admin->id]);
        $expense = Expense::create([
            'expense_category_id' => ExpenseCategory::where('key', 'overhead.equipment_maintenance')->value('id'),
            'equipment_id' => $machine->id, 'item_name' => 'Repair', 'expense_date' => now()->toDateString(),
            'total_amount' => 1000, 'status' => 'partial', 'total_installments' => 2, 'created_by' => $this->admin->id,
        ]);
        $expense->payments()->create(['payment_number' => 1, 'amount' => 500, 'due_date' => now()->toDateString(), 'status' => 'paid', 'paid_date' => now()->toDateString()]);
        $expense->payments()->create(['payment_number' => 2, 'amount' => 500, 'due_date' => now()->addMonth()->toDateString(), 'status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test(EquipmentShow::class, ['equipment' => $machine])
            ->assertViewHas('overviewCosts', fn ($c) => $c['unpaid'] === 500.0);
    }

    /** P2: the batch totals obey the money switch. */
    public function test_batch_totals_hide_without_the_money_grant(): void
    {
        $sub = $this->vendor(sub: true, supplier: false);
        $batch = PaymentBatch::create(['name' => 'Friday', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'created_by' => $this->admin->id]);
        PaymentBatchItem::create([
            'payment_batch_id' => $batch->id,
            'contract_id' => Contract::create(['project_id' => $this->project->id, 'subcontractor_id' => $sub->id, 'contract_number' => 'CTR-1', 'status' => 'active', 'start_date' => now()->toDateString(), 'amount' => 90000, 'created_by' => $this->admin->id])->id,
            'amount' => 87654.32, 'payment_method' => 'check', 'status' => 'pending',
        ]);

        $html = Livewire::actingAs($this->roleWith(['payments.view', 'payments.batch']))->test(PaymentBatchIndex::class)->html();
        $totals = str($html)->after(__('Grand total'))->toString();
        $this->assertStringNotContainsString('$87,654.32', $totals);

        $html = Livewire::actingAs($this->roleWith(['payments.view', 'payments.batch', 'finance.view_amounts']))->test(PaymentBatchIndex::class)->html();
        $this->assertStringContainsString('$87,654.32', str($html)->after(__('Grand total'))->toString());
    }
}
