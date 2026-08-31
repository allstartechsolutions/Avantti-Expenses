<?php

namespace Tests\Feature\Uploads;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\DailyReport\DailyReportForm;
use App\Livewire\Project\ProjectChangeOrders;
use App\Livewire\Project\ProjectQuotations;
use App\Livewire\Project\ProjectRequisitions;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
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
 * A second drop must add to the queue, never replace it.
 *
 * Livewire's `uploadMultiple` runs with `append = false`, so a multi-file
 * `wire:model` bound straight to the queue loses the first batch with nothing
 * on screen to say so. Every converted screen writes to a scratch box that an
 * `updated…()` hook empties into the queue; these are the four that were still
 * on the old shape.
 */
class QueueAccumulationTest extends TestCase
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
            'company_name' => 'Queue Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-q@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site-q@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 20, 'application/pdf');
    }

    public function test_a_quotations_attachments_accumulate(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('quo_new_uploads', [$this->pdf('desenho.pdf')])
            ->assertSet('quo_new_uploads', [])
            ->set('quo_new_uploads', [$this->pdf('memorial.pdf')])
            ->assertCount('quo_uploads', 2)
            ->assertSee('desenho.pdf')
            ->assertSee('memorial.pdf')
            ->call('discardQuotationUpload', 0)
            ->assertCount('quo_uploads', 1)
            ->assertDontSee('desenho.pdf');
    }

    public function test_a_requisitions_attachments_accumulate(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_new_uploads', [$this->pdf('spec.pdf')])
            ->set('req_new_uploads', [$this->pdf('foto.pdf')])
            ->assertCount('req_uploads', 2)
            ->call('discardRequisitionUpload', 1)
            ->assertCount('req_uploads', 1);
    }

    /** A file of the wrong type is refused without taking the good one with it. */
    public function test_a_refused_attachment_does_not_take_the_queue_with_it(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_new_uploads', [
                $this->pdf('spec.pdf'),
                UploadedFile::fake()->create('planilha.xlsx', 10),
            ])
            ->assertHasErrors('req_new_uploads')
            ->assertSet('req_new_uploads', [])
            ->assertCount('req_uploads', 1);
    }

    /** The vendor's proposal takes several files the same way. */
    public function test_a_vendor_proposals_attachments_accumulate(): void
    {
        Storage::fake('local');

        $quotation = \App\Models\Quotation::create([
            'project_id' => $this->project->id,
            'quotation_number' => 'QT-'.str()->random(6),
            'type' => 'material',
            'title' => 'Cimento',
            'status' => 'sent',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\QuotationItem::create([
            'quotation_id' => $quotation->id,
            'item_name' => 'Cimento CP-II',
            'item_type' => 'custom',
            'quantity' => 10,
            'unit' => 'sc',
            'sort_order' => 0,
        ]);

        $vendor = new \App\Models\Vendor;
        $vendor->forceFill([
            'name' => 'Fornecedor '.str()->random(4),
            'is_supplier' => true,
            'created_by' => $this->admin->id,
        ])->save();

        $row = \App\Models\QuotationVendor::create([
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            // 'invited' is a state the proposal may be keyed in from.
            'status' => 'invited',
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openProposalModal', $row->id)
            ->set('prop_new_uploads', [$this->pdf('proposta.pdf')])
            ->assertSet('prop_new_uploads', [])
            ->set('prop_new_uploads', [$this->pdf('anexo.pdf')])
            ->assertCount('prop_uploads', 2)
            ->call('discardProposalUpload', 0)
            ->assertCount('prop_uploads', 1);
    }

    /** A daily report's photographs arrive a few at a time all day. */
    public function test_daily_report_photographs_accumulate(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(DailyReportForm::class, ['jobSite' => $this->site])
            ->call('openAddTaskModal')
            ->set('newTaskImages', [UploadedFile::fake()->image('manha.jpg')])
            ->assertSet('newTaskImages', [])
            ->set('newTaskImages', [UploadedFile::fake()->image('tarde.jpg')])
            ->assertCount('taskImages', 2)
            ->call('removeNewImage', 0)
            ->assertCount('taskImages', 1);
    }

    /** The change order now refuses a file that is not a document or an image. */
    public function test_a_change_order_refuses_a_file_of_the_wrong_type(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('openChangeOrderCreateModal')
            ->set('co_number', 'CO-001')
            ->set('co_description', 'Aditivo')
            ->set('co_file', UploadedFile::fake()->create('planilha.xlsx', 10))
            ->call('saveChangeOrder')
            ->assertHasErrors('co_file');
    }

    /** The change order takes one file, and it can now be taken back off. */
    public function test_a_change_order_file_can_be_taken_back_off(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('openChangeOrderCreateModal')
            ->set('co_file', $this->pdf('aditivo.pdf'))
            ->assertSee('aditivo.pdf')
            ->call('clearChangeOrderFile')
            ->assertSet('co_file', null)
            ->assertDontSee('aditivo.pdf');
    }
}
