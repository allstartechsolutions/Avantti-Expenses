<?php

namespace Tests\Feature\Expense;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\PurchaseOrder\PurchaseOrderCreate;
use App\Models\Client;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The single-file receipt on an expense and the quote on a purchase order.
 *
 * Both were plain file inputs with no way to see or undo what was chosen; both
 * are drop zones now. What is pinned here is that the file chosen is the file
 * stored, and that it can be taken back off before saving.
 */
class ReceiptFieldTest extends TestCase
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
            'company_name' => 'Receipt Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-rc@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site-rc@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_an_expense_receipt_is_stored_and_can_be_taken_back_off(): void
    {
        Storage::fake('local');

        $component = Livewire::actingAs($this->admin)
            ->test(ExpenseCreate::class, ['project' => $this->project])
            ->set('expense_receipt', UploadedFile::fake()->create('recibo.pdf', 20, 'application/pdf'))
            // The chosen file is named on screen — it never was before.
            ->assertSee('recibo.pdf');

        $component->call('clearExpenseReceipt')
            ->assertSet('expense_receipt', null)
            ->assertDontSee('recibo.pdf');
    }

    /**
     * The project's expense modal carries the same zone as the full screens.
     * (The job site screen had one too; it now files on the full screen.)
     */
    public function test_the_project_expense_modal_shows_and_clears_the_chosen_receipt(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Project\ProjectShow::class, ['project' => $this->project])
            ->call('openExpenseCreateModal')
            ->set('expense_receipt', UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'))
            ->assertSee('nota.pdf')
            ->call('clearExpenseReceipt')
            ->assertSet('expense_receipt', null)
            ->assertDontSee('nota.pdf');
    }

    public function test_a_purchase_order_document_is_stored_and_can_be_taken_back_off(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(PurchaseOrderCreate::class, ['project' => $this->project])
            ->set('po_receipt', UploadedFile::fake()->create('orcamento.pdf', 20, 'application/pdf'))
            ->assertSee('orcamento.pdf')
            ->call('clearPoReceipt')
            ->assertSet('po_receipt', null)
            ->assertDontSee('orcamento.pdf');
    }
}
