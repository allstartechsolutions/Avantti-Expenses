<?php

namespace Tests\Feature\Uploads;

use App\Livewire\Company\CompanyInfo;
use App\Livewire\CostCode\CostCodeTemplateShow;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Models\CostCodeTemplate;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The three single-purpose upload fields: the company logo, the cost-code CSV
 * import, and a subcontractor's document.
 *
 * Each takes one file of its own kind rather than the document allow-list, so
 * what is pinned here is that the file is taken, shown, and can be taken back
 * off — the last of which none of the three could do before.
 */
class OneOffUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);
    }

    public function test_a_dropped_logo_is_held_and_can_be_removed(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(CompanyInfo::class)
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->assertHasNoErrors('logo')
            // The preview is what the screen shows for an image.
            ->assertNotSet('logoPreview', null)
            ->call('removeLogo')
            ->assertSet('logo', null)
            ->assertSet('logoPreview', null);
    }

    public function test_a_dropped_csv_is_read_and_can_be_taken_back_off(): void
    {
        $template = CostCodeTemplate::create([
            'name' => 'Padrão',
            'created_by' => $this->admin->id,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'codes.csv',
            "code,name,description\n01.100,Serviços Preliminares,\n01.200,Canteiro de Obras,\n",
        );

        Livewire::actingAs($this->admin)
            ->test(CostCodeTemplateShow::class, ['template' => $template])
            ->call('openImportModal')
            ->set('importFile', $csv)
            ->assertCount('importPreview', 2)
            ->assertSee('codes.csv')
            ->call('clearImportFile')
            ->assertSet('importFile', null)
            ->assertCount('importPreview', 0)
            ->assertDontSee('codes.csv');
    }

    public function test_a_dropped_subcontractor_document_can_be_taken_back_off(): void
    {
        Storage::fake('local');
        // The drop zone is the path an install without a bucket takes.
        config(['documents.disk' => 'local']);

        // `company_name` is the legacy alias for the unified `name` column.
        $subcontractor = Subcontractor::create([
            'company_name' => 'Sub Alvenaria',
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $subcontractor])
            // The documents tab, then the upload dialog.
            ->set('activeTab', 'documents')
            ->call('startUpload')
            ->set('document_file', UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf'))
            ->assertSee('contrato.pdf')
            ->call('clearDocumentFile')
            ->assertSet('document_file', null)
            ->assertDontSee('contrato.pdf');
    }
}
