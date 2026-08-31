<?php

namespace Tests\Feature\Documents;

use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectDocuments;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The preview shows the version the document is on.
 *
 * `documents.preview` serves whatever is current, but its URL is the same
 * string for every version — so Livewire's morph left the `<iframe>` untouched
 * and the reader went on looking at the version they opened while the history
 * beside it already listed the new one. The src and the `wire:key` both carry
 * the current version id now; these pin that they move when it does.
 */
class PreviewVersionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

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
            'email' => 'project-doc@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function addVersion(Document $document, int $number): DocumentVersion
    {
        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $number,
            'disk' => 'local',
            'object_key' => 'documents/planta-v'.$number.'.pdf',
            'original_name' => 'planta.pdf',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'upload_status' => DocumentVersion::STATUS_AVAILABLE,
            'uploaded_by' => $this->admin->id,
        ]);

        $document->update([
            'current_version_id' => $version->id,
            'current_version_number' => $number,
            'current_size_bytes' => $version->size_bytes,
            'current_mime_type' => $version->mime_type,
        ]);

        return $version;
    }

    public function test_the_preview_follows_the_current_version(): void
    {
        $document = Document::create([
            'project_id' => $this->project->id,
            'name' => 'planta-baixa.pdf',
            'category' => 'plans',
            'is_internal' => false,
            'created_by' => $this->admin->id,
        ]);

        $first = $this->addVersion($document, 1);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openDetail', $document->id);

        // The stage is pinned to version one.
        $component->assertSee('v='.$first->id, false)
            ->assertSee('preview-pdf-'.$document->id.'-'.$first->id, false);

        // A new version lands while the detail view is open.
        $second = $this->addVersion($document, 2);

        $component->call('documentUploaded', $document->id)
            ->assertSee('v='.$second->id, false)
            ->assertSee('preview-pdf-'.$document->id.'-'.$second->id, false)
            // And the old one is no longer what the stage points at.
            ->assertDontSee('v='.$first->id, false);
    }
}
