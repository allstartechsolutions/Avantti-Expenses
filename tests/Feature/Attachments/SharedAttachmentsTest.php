<?php

namespace Tests\Feature\Attachments;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Shared\Attachments;
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
 * The attachments panel shared by expenses, purchase orders, income,
 * requisitions and quotations.
 *
 * It used to take one file per round trip. It now takes a queue, which is the
 * behaviour these pin: several files go up in one act, a file can be taken
 * back off before it does, and a refused file neither goes up nor blocks what
 * else is waiting.
 */
class SharedAttachmentsTest extends TestCase
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
            'company_name' => 'Attachment Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-at@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site-at@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeExpense(): Expense
    {
        return Expense::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'expense_date' => now()->toDateString(),
            'total_amount' => 1000,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makePurchaseOrder(): PurchaseOrder
    {
        return PurchaseOrder::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'po_number' => 'PO-'.str()->random(5),
            'po_date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => 1000,
            'created_by' => $this->admin->id,
        ]);
    }

    /** Several files in one act, rather than one round trip each. */
    public function test_several_files_go_up_in_one_upload(): void
    {
        Storage::fake('local');

        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(Attachments::class, ['modelType' => 'expense', 'modelId' => $expense->id])
            ->set('newUploads', [
                UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->image('foto.jpg'),
            ])
            // The box is emptied for the next drop, and the queue holds both.
            ->assertSet('newUploads', [])
            ->assertCount('uploads', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['nota.pdf', 'foto.jpg'],
            $expense->fresh()->attachments()->pluck('original_name')->all(),
        );
    }

    /** A second drop adds to the queue instead of replacing the first. */
    public function test_a_second_drop_adds_to_the_queue(): void
    {
        Storage::fake('local');

        $order = $this->makePurchaseOrder();

        Livewire::actingAs($this->admin)
            ->test(Attachments::class, ['modelType' => 'purchase-order', 'modelId' => $order->id])
            ->set('newUploads', [UploadedFile::fake()->create('orcamento.pdf', 20, 'application/pdf')])
            ->set('newUploads', [UploadedFile::fake()->create('proposta.pdf', 20, 'application/pdf')])
            ->assertCount('uploads', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $order->fresh()->attachments()->count());
    }

    public function test_a_queued_file_can_be_taken_back_off(): void
    {
        Storage::fake('local');

        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(Attachments::class, ['modelType' => 'expense', 'modelId' => $expense->id])
            ->set('newUploads', [
                UploadedFile::fake()->create('mantida.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('removida.pdf', 10, 'application/pdf'),
            ])
            ->call('discardUpload', 1)
            ->assertCount('uploads', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['mantida.pdf'], $expense->fresh()->attachments()->pluck('original_name')->all());
    }

    /**
     * A file of the wrong type is refused on the way in, not on the way out.
     *
     * Left in the box it would be invisible and would fail every later upload,
     * with nothing on screen to remove it.
     */
    public function test_a_refused_file_does_not_block_the_ones_beside_it(): void
    {
        Storage::fake('local');

        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(Attachments::class, ['modelType' => 'expense', 'modelId' => $expense->id])
            ->set('newUploads', [
                UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('planilha.xlsx', 10),
            ])
            ->assertHasErrors('newUploads')
            ->assertSet('newUploads', [])
            ->assertCount('uploads', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['nota.pdf'], $expense->fresh()->attachments()->pluck('original_name')->all());
    }

    /** Uploading is still held to the record's own edit grant. */
    public function test_uploading_is_refused_without_the_edit_grant(): void
    {
        Storage::fake('local');

        $expense = $this->makeExpense();

        // A role that may read expenses and nothing else.
        $role = Role::create(['name' => 'reader-'.uniqid()]);
        $role->syncAbilities(['expenses.view']);

        $reader = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($reader)
            ->test(Attachments::class, ['modelType' => 'expense', 'modelId' => $expense->id])
            ->set('newUploads', [UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')])
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, $expense->fresh()->attachments()->count());
    }
}
