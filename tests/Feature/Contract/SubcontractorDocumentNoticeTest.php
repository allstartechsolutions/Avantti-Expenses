<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\Contract\ContractShow;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Livewire\PaymentBatch\PaymentBatchShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentType;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every screen that pays a subcontractor warns when that subcontractor's
 * compliance documents have expired or fall due within the warning window —
 * judged by the same rule as the vendor badge, so a current or archived
 * document never raises it.
 */
class SubcontractorDocumentNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected DocumentType $insurance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Notice Client',
            'contact_name' => 'C',
            'email' => 'notice@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Noticed',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'noticed@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->insurance = DocumentType::create([
            'name' => 'General Liability Insurance',
            'requires_expiration' => true,
            'sort_order' => 1,
        ]);
    }

    protected function makeContract(string $name, ?int $expiresInDays): Contract
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => $name,
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        if ($expiresInDays !== null) {
            SubcontractorDocument::create([
                'subcontractor_id' => $vendor->id,
                'document_type_id' => $this->insurance->id,
                'file_path' => "subcontractor-documents/{$vendor->id}/coi.pdf",
                'file_name' => 'coi.pdf',
                'file_size' => 3,
                'expiration_date' => now()->addDays($expiresInDays)->toDateString(),
                'uploaded_by' => $this->admin->id,
            ]);
        }

        return Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 50000,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_contract_page_lists_the_expired_document(): void
    {
        $contract = $this->makeContract('Lapsed Sub', -5);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertSee('Lapsed Sub has 1 expired document.')
            ->assertSee('General Liability Insurance')
            ->assertSee('Expired '.now()->subDays(5)->appDate().' (5 days ago)')
            ->assertSee(route('subcontractors.show', [$contract->subcontractor_id, 'tab' => 'documents']), false);
    }

    public function test_the_contract_page_warns_ahead_of_the_date(): void
    {
        $contract = $this->makeContract('Due Sub', 10);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertSee('Due Sub has 1 document expiring soon.')
            ->assertSee('(in 10 days)');
    }

    public function test_the_document_detail_needs_vendor_access_but_the_warning_does_not(): void
    {
        $contract = $this->makeContract('Lapsed Sub', -5);

        $role = Role::create(['name' => 'contracts-only']);
        $role->syncAbilities(['projects.view', 'project.view', 'contracts.view']);
        $reader = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($reader)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertSee('Lapsed Sub has 1 expired document.')
            ->assertDontSee('General Liability Insurance')
            ->assertDontSee(route('subcontractors.show', [$contract->subcontractor_id, 'tab' => 'documents']), false);
    }

    public function test_a_current_or_archived_document_raises_nothing(): void
    {
        $current = $this->makeContract('Current Sub', 90);

        $archived = $this->makeContract('Archived Sub', -5);
        SubcontractorDocument::where('subcontractor_id', $archived->subcontractor_id)
            ->update(['status' => SubcontractorDocument::STATUS_ARCHIVED]);

        foreach ([$current, $archived] as $contract) {
            Livewire::actingAs($this->admin)
                ->test(ContractShow::class, ['contract' => $contract])
                ->assertDontSee('Check the compliance documents before releasing a payment.');
        }
    }

    public function test_contract_payments_names_each_flagged_subcontractor(): void
    {
        $this->makeContract('Lapsed Sub', -1);
        $this->makeContract('Due Sub', 3);
        $this->makeContract('Current Sub', 90);

        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->assertSee('Subcontractors to check: 1 with expired documents, 1 with documents expiring soon.')
            ->assertSeeInOrder(['Lapsed Sub', 'Due Sub'])
            ->assertSee('Documents expired')
            ->assertSee('Documents expiring soon');
    }

    public function test_contract_payments_says_nothing_when_all_are_current(): void
    {
        $this->makeContract('Current Sub', 90);
        $this->makeContract('Undocumented Sub', null);

        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->assertDontSee('Check the compliance documents before releasing a payment.');
    }

    public function test_an_entered_amount_hidden_by_a_filter_is_still_warned_about(): void
    {
        $lapsed = $this->makeContract('Lapsed Sub', -1);
        $current = $this->makeContract('Current Sub', 90);

        // Process Payments pays every entered amount, filtered out or not.
        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->set('payAmounts.'.$lapsed->id, '100')
            ->set('subcontractorFilter', (string) $current->subcontractor_id)
            ->assertSee('1 subcontractor to be paid has expired documents.')
            ->assertSee('Lapsed Sub');
    }

    public function test_a_pending_batch_item_hidden_by_a_filter_is_still_warned_about(): void
    {
        $lapsed = $this->makeContract('Lapsed Sub', -1);
        $current = $this->makeContract('Current Sub', 90);

        $batch = PaymentBatch::create([
            'name' => 'Filtered',
            'status' => 'draft',
            'payment_date' => now()->toDateString(),
            'subcontractor_id' => $current->subcontractor_id,
            'created_by' => $this->admin->id,
        ]);
        PaymentBatchItem::create([
            'payment_batch_id' => $batch->id,
            'contract_id' => $lapsed->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        // Approve All pays every pending item, filtered out or not.
        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $batch])
            ->assertSee('1 subcontractor to be paid has expired documents.')
            ->assertSee('Lapsed Sub');
    }

    public function test_the_batch_view_warns_only_for_lines_still_to_pay(): void
    {
        $lapsed = $this->makeContract('Lapsed Sub', -1);
        $rejected = $this->makeContract('Rejected Sub', -1);

        $batch = PaymentBatch::create([
            'name' => 'Friday',
            'status' => 'draft',
            'payment_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);
        $paid = $this->makeContract('Paid Sub', -1);

        foreach ([[$lapsed, 'pending'], [$rejected, 'rejected'], [$paid, 'approved']] as [$contract, $status]) {
            PaymentBatchItem::create([
                'payment_batch_id' => $batch->id,
                'contract_id' => $contract->id,
                'amount' => 100,
                'status' => $status,
            ]);
        }

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $batch])
            ->assertSee('3 subcontractors to be paid have expired documents.');

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchShow::class, ['paymentBatch' => $batch])
            ->assertSee('1 subcontractor to be paid has expired documents.');
    }
}
