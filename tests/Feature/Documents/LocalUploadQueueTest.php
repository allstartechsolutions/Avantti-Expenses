<?php

namespace Tests\Feature\Documents;

use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectDocuments;
use App\Models\Client;
use App\Models\Document;
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
 * The documents upload panel on an install without Cloudflare R2.
 *
 * The bytes travel through PHP here, so the panel keeps its own queue. What
 * these pin is that the queue behaves like the cloud branch beside it: a
 * second drop adds rather than replaces, a file can be taken back off, and a
 * file the allow-list refuses neither goes up nor blocks the ones with it.
 */
class LocalUploadQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        // The test environment has R2 configured, which renders the cloud
        // branch of the panel. These are about the branch used when it is not.
        config(['documents.disk' => 'local']);

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Doc Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-lu@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_a_second_drop_adds_to_the_queue(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openUploadModal')
            ->set('localNewUploads', [UploadedFile::fake()->create('planta.pdf', 20, 'application/pdf')])
            // The box is emptied for the next drop, and the queue holds the first.
            ->assertSet('localNewUploads', [])
            ->set('localNewUploads', [UploadedFile::fake()->create('corte.pdf', 20, 'application/pdf')])
            ->assertCount('localUploads', 2)
            ->assertSee('planta.pdf')
            ->assertSee('corte.pdf')
            ->call('discardLocalUpload', 0)
            ->assertCount('localUploads', 1)
            ->assertDontSee('planta.pdf');
    }

    public function test_a_file_the_allow_list_refuses_does_not_take_the_queue_with_it(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openUploadModal')
            ->set('localNewUploads', [
                UploadedFile::fake()->create('planta.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('malicioso.exe', 20, 'application/x-msdownload'),
            ])
            ->assertHasErrors('localNewUploads')
            ->assertSet('localNewUploads', [])
            ->assertCount('localUploads', 1);
    }

    /** Opening the panel again does not inherit the last queue. */
    public function test_the_queue_does_not_survive_the_panel_closing(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openUploadModal')
            ->set('localNewUploads', [UploadedFile::fake()->create('planta.pdf', 20, 'application/pdf')])
            ->assertCount('localUploads', 1)
            ->call('closeUploadModal')
            ->call('openUploadModal')
            ->assertCount('localUploads', 0);
    }

    public function test_the_queued_files_are_stored_when_the_upload_is_started(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        Livewire::actingAs($this->admin)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openUploadModal')
            ->set('localNewUploads', [UploadedFile::fake()->create('planta.pdf', 20, 'application/pdf')])
            ->set('localNewUploads', [UploadedFile::fake()->create('corte.pdf', 20, 'application/pdf')])
            ->call('saveLocalUploads')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['planta.pdf', 'corte.pdf'],
            Document::pluck('name')->all(),
        );
    }
}
