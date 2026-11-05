<?php

namespace Tests\Feature\Supplier;

use App\Livewire\Subcontractor\SubcontractorShow;
use App\Livewire\Supplier\SupplierShow;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client request 27 (Nov 2026): suppliers keep documents too — W-9, COI —
 * with the same upload, renewal, archive and expiry as a subcontractor. The
 * documents belong to the vendor row, so a vendor that is both shows one set.
 */
class SupplierDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected DocumentType $w9;

    protected DocumentType $coi;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['documents.disk' => 'local']);

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->w9 = DocumentType::create(['name' => 'W9', 'requires_expiration' => false, 'sort_order' => 1]);
        $this->coi = DocumentType::create(['name' => 'Certificate of Insurance (COI)', 'requires_expiration' => true, 'expiry_warning_days' => 30, 'sort_order' => 2]);
    }

    private function supplier(bool $alsoSubcontractor = false): Supplier
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Shore Fuel Co', 'is_supplier' => true, 'is_subcontractor' => $alsoSubcontractor, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return Supplier::findOrFail($vendor->id);
    }

    public function test_a_supplier_files_a_w9_and_a_coi(): void
    {
        $supplier = $this->supplier();

        $page = Livewire::actingAs($this->admin)
            ->test(SupplierShow::class, ['supplier' => $supplier])
            ->assertSee(__('Required documents'))
            ->assertSee('Certificate of Insurance (COI)')
            ->call('startUpload', $this->w9->id)
            ->assertSet('showUploadForm', true)
            ->set('document_file', UploadedFile::fake()->create('w9-2026.pdf', 20, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors()
            ->call('startUpload', $this->coi->id)
            ->set('document_file', UploadedFile::fake()->create('coi.pdf', 20, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasErrors(['expiration_date' => 'required'])
            ->set('expiration_date', now()->addDays(20)->toDateString())
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $documents = SubcontractorDocument::where('subcontractor_id', $supplier->id)->with('documentType')->get();
        $this->assertSame(['W9', 'Certificate of Insurance (COI)'], $documents->pluck('documentType.name')->all());
        $this->assertSame('expiring_soon', $documents->last()->expiry_status);

        $page->assertSee('w9-2026.pdf')->assertSee('coi.pdf');
    }

    public function test_an_id_from_another_vendor_is_refused(): void
    {
        $supplier = $this->supplier();
        $other = $this->supplier();
        Storage::disk('local')->put('x/coi.pdf', 'pdf');
        $theirs = SubcontractorDocument::create(['subcontractor_id' => $other->id, 'document_type_id' => $this->coi->id, 'file_path' => 'x/coi.pdf', 'file_name' => 'coi.pdf', 'expiration_date' => now()->addYear()->toDateString(), 'uploaded_by' => $this->admin->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(SupplierShow::class, ['supplier' => $supplier])
            ->call('deleteDocument', $theirs->id);
    }

    public function test_uploading_needs_the_document_grant(): void
    {
        $supplier = $this->supplier();
        $role = Role::create(['name' => 'vendor-reader']);
        $role->syncAbilities(['projects.view', 'project.view', 'vendors.view']);

        Livewire::actingAs(User::factory()->create(['role_id' => $role->id]))
            ->test(SupplierShow::class, ['supplier' => $supplier])
            ->call('startUpload')
            ->assertForbidden();
    }

    public function test_a_vendor_that_is_both_shows_the_same_documents_on_both_pages(): void
    {
        $supplier = $this->supplier(alsoSubcontractor: true);
        Storage::disk('local')->put('x/w9.pdf', 'pdf');
        SubcontractorDocument::create(['subcontractor_id' => $supplier->id, 'document_type_id' => $this->w9->id, 'file_path' => 'x/w9.pdf', 'file_name' => 'w9-on-file.pdf', 'uploaded_by' => $this->admin->id]);

        Livewire::actingAs($this->admin)->test(SupplierShow::class, ['supplier' => $supplier])->assertSee('w9-on-file.pdf');
        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => Subcontractor::findOrFail($supplier->id)])
            ->call('setActiveTab', 'documents')
            ->assertSee('w9-on-file.pdf');
    }
}
