<?php

namespace Tests\Feature\Contract;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractChangeOrders;
use App\Livewire\Contract\ContractCreate;
use App\Livewire\Contract\ContractEdit;
use App\Models\Client;
use App\Models\Contract;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The contract's own file, on the three screens that take one.
 *
 * Each is a single-file field, so there is no queue to keep — what matters is
 * that the file chosen is the file stored, that it can be taken back off
 * before saving, and that on edit, choosing nothing leaves the file already on
 * the contract alone.
 */
class ContractFileTest extends TestCase
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
            'company_name' => 'Contract Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-ct@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site-ct@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeVendor(): Vendor
    {
        $vendor = new Vendor;

        $vendor->forceFill([
            'name' => 'Sub '.str()->random(5),
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return $vendor;
    }

    protected function makeContract(array $attributes = []): Contract
    {
        return Contract::create(array_merge([
            'project_id' => $this->project->id,
            'subcontractor_id' => $this->makeVendor()->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 50000,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_a_dropped_contract_file_is_stored_with_the_contract(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ContractCreate::class, ['project' => $this->project])
            ->set('subcontractor_id', $this->makeVendor()->id)
            ->set('start_date', now()->toDateString())
            ->set('amount', 50000)
            ->set('contract_file', UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf'))
            ->call('save');

        $contract = Contract::first();

        $this->assertNotNull($contract->contract_file_path);
        Storage::disk('local')->assertExists($contract->contract_file_path);
    }

    public function test_a_chosen_contract_file_can_be_taken_back_off(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ContractCreate::class, ['project' => $this->project])
            ->set('subcontractor_id', $this->makeVendor()->id)
            ->set('start_date', now()->toDateString())
            ->set('amount', 50000)
            ->set('contract_file', UploadedFile::fake()->create('errado.pdf', 20, 'application/pdf'))
            ->assertSee('errado.pdf')
            ->call('clearContractFile')
            ->assertDontSee('errado.pdf')
            ->assertSet('contract_file', null)
            ->call('save');

        $this->assertNull(Contract::first()->contract_file_path);
    }

    /** Editing without choosing a file leaves the one already on the contract. */
    public function test_editing_without_a_new_file_keeps_the_existing_one(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contracts/original.pdf', 'x');

        $contract = $this->makeContract(['contract_file_path' => 'contracts/original.pdf']);

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('amount', 60000)
            ->call('save');

        $this->assertSame('contracts/original.pdf', $contract->fresh()->contract_file_path);
    }

    public function test_a_replacement_file_takes_over_on_edit(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contracts/original.pdf', 'x');

        $contract = $this->makeContract(['contract_file_path' => 'contracts/original.pdf']);

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('contract_file', UploadedFile::fake()->create('novo.pdf', 20, 'application/pdf'))
            ->call('save');

        $stored = $contract->fresh()->contract_file_path;

        $this->assertNotSame('contracts/original.pdf', $stored);
        Storage::disk('local')->assertExists($stored);
    }

    /** The aditivo modal takes one file the same way. */
    public function test_a_change_order_file_can_be_taken_back_off(): void
    {
        Storage::fake('local');

        $contract = $this->makeContract();

        Livewire::actingAs($this->admin)
            ->test(ContractChangeOrders::class, ['contract' => $contract])
            // Opened, so the modal — and the drop zone inside it — is rendered.
            ->call('openCreateModal')
            ->set('file', UploadedFile::fake()->create('aditivo.pdf', 20, 'application/pdf'))
            ->assertSee('aditivo.pdf')
            ->call('clearFile')
            ->assertSet('file', null)
            ->assertDontSee('aditivo.pdf');
    }
}
