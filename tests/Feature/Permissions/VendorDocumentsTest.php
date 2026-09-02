<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Subcontractor\SubcontractorIndex;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Models\DocumentType;
use App\Models\FileUpload;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vendor documents — the compliance files (insurance, licences, tax
 * clearances) filed against a subcontractor, with the renewal chain that lets
 * one be replaced without losing the one it replaced.
 *
 * Reproduced: uploading and deleting had no guard of their own before this
 * (the page's `vendors.view` was the only check), and the intended level was
 * always "may edit the vendor" — so the migration hands the two new
 * abilities to every role and override holding `vendors.edit`.
 * Revocable: each of upload/renew, archive and delete answers to its own
 * ability and to nothing else.
 */
class VendorDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected DocumentType $insurance;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // The Livewire drop-zone path, which every install without a bucket
        // takes; the bucket path is exercised by its own tests below.
        config(['documents.disk' => 'local']);

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->insurance = DocumentType::create([
            'name' => 'General Liability Insurance',
            'requires_expiration' => true,
            'sort_order' => 1,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'),
        ]);
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function reader(): User
    {
        return $this->roleWith(['projects.view', 'project.view', 'vendors.view']);
    }

    protected function makeSubcontractor(): Subcontractor
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => 'Sub '.str()->random(5),
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return Subcontractor::findOrFail($vendor->id);
    }

    protected function makeDocument(Subcontractor $sub, array $attributes = []): SubcontractorDocument
    {
        Storage::disk('local')->put("subcontractor-documents/{$sub->id}/coi.pdf", 'pdf');

        return SubcontractorDocument::create(array_merge([
            'subcontractor_id' => $sub->id,
            'document_type_id' => $this->insurance->id,
            'file_path' => "subcontractor-documents/{$sub->id}/coi.pdf",
            'file_name' => 'coi.pdf',
            'file_size' => 3,
            'expiration_date' => now()->addDays(90)->toDateString(),
            'uploaded_by' => $this->admin->id,
        ], $attributes));
    }

    protected function upload(User $as, Subcontractor $sub, array $set = [])
    {
        return Livewire::actingAs($as)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->set('document_type_id', (string) $this->insurance->id)
            ->set('document_file', UploadedFile::fake()->create('renewed.pdf', 12, 'application/pdf'))
            ->set('expiration_date', now()->addDays(365)->toDateString())
            ->set($set);
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue and the seeds
    |---------------------------------------------------------------------------
    */

    public function test_the_two_abilities_are_declared_on_the_vendors_area(): void
    {
        $catalog = app(AbilityCatalog::class);

        $this->assertContains('vendors.renew_documents', $catalog::abilities());
        $this->assertContains('vendors.archive_documents', $catalog::abilities());
        $this->assertFalse($catalog::action('vendors.renew_documents')['sensitive'] ?? false);
        $this->assertTrue($catalog::action('vendors.archive_documents')['sensitive']);
    }

    public function test_the_seeded_manager_holds_both_from_a_fresh_seed(): void
    {
        $manager = Role::where('name', 'manager')->first();

        $this->assertContains('vendors.renew_documents', $manager->abilities());
        $this->assertContains('vendors.archive_documents', $manager->abilities());
    }

    public function test_the_migration_hands_both_to_whoever_already_held_vendors_edit(): void
    {
        $editor = Role::create(['name' => 'editor']);
        $editor->syncAbilities(['vendors.view', 'vendors.edit']);

        $viewer = Role::create(['name' => 'viewer']);
        $viewer->syncAbilities(['vendors.view']);

        $person = User::factory()->create(['role_id' => $viewer->id]);
        $person->abilityOverrides()->create(['ability' => 'vendors.edit', 'granted' => true]);

        $migration = require database_path('migrations/2026_09_02_130002_grant_vendor_document_abilities.php');
        $migration->up();
        $migration->up();   // idempotent: a second run adds nothing

        $this->assertEqualsCanonicalizing(
            ['vendors.view', 'vendors.edit', 'vendors.renew_documents', 'vendors.archive_documents'],
            $editor->fresh()->abilities(),
        );
        $this->assertEqualsCanonicalizing(['vendors.view'], $viewer->fresh()->abilities());
        $this->assertEqualsCanonicalizing(
            ['vendors.edit', 'vendors.renew_documents', 'vendors.archive_documents'],
            $person->abilityOverrides()->where('granted', true)->pluck('ability')->all(),
        );
        $this->assertSame(2, $editor->abilityRows()->where('ability', 'vendors.renew_documents')->count() + $editor->abilityRows()->where('ability', 'vendors.archive_documents')->count());
    }

    /*
    |---------------------------------------------------------------------------
    | Upload and renew
    |---------------------------------------------------------------------------
    */

    public function test_a_reader_may_look_but_not_upload(): void
    {
        $sub = $this->makeSubcontractor();

        $this->actingAs($this->reader())->get(route('subcontractors.show', $sub))->assertOk();

        $this->upload($this->reader(), $sub)->call('uploadDocument')->assertForbidden();

        $this->assertSame(0, SubcontractorDocument::count());
    }

    public function test_uploading_answers_to_renew_documents_alone(): void
    {
        $sub = $this->makeSubcontractor();
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        $this->upload($clerk, $sub)->call('uploadDocument')->assertHasNoErrors();

        $document = SubcontractorDocument::sole();
        $this->assertTrue($document->isActive());
        $this->assertSame($clerk->id, $document->uploaded_by);

        // Stored through the shared upload path even without a bucket: a
        // file_uploads row under vendors/, no legacy path.
        $this->assertNull($document->file_path);
        $this->assertNotNull($document->file_upload_id);
        $this->assertSame(SubcontractorDocument::class, $document->fileUpload->attachable_type);
        $this->assertSame($document->id, $document->fileUpload->attachable_id);
        $this->assertStringStartsWith("vendors/{$sub->id}/", $document->fileUpload->object_key);
        Storage::disk('local')->assertExists($document->fileUpload->object_key);
        $this->assertSame('renewed.pdf', $document->file_name);
    }

    public function test_renewing_supersedes_the_old_document_and_keeps_its_type(): void
    {
        $sub = $this->makeSubcontractor();
        $old = $this->makeDocument($sub, ['expiration_date' => now()->addDays(10)->toDateString()]);
        $other = DocumentType::create(['name' => 'W9', 'requires_expiration' => false, 'sort_order' => 2]);
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        $this->assertSame('expiring_soon', $old->expiry_status);

        $this->upload($clerk, $sub)
            ->call('startRenewal', $old->id)
            ->assertSet('renewing_document_id', $old->id)
            ->assertSet('showUploadForm', true)
            // The browser tries to switch the type mid-renewal; the server keeps the old one.
            ->set('document_type_id', (string) $other->id)
            ->set('document_file', UploadedFile::fake()->create('renewed.pdf', 12, 'application/pdf'))
            ->set('expiration_date', now()->addDays(365)->toDateString())
            ->call('uploadDocument')
            ->assertHasNoErrors()
            ->assertSet('renewing_document_id', null);

        $old->refresh();
        $new = SubcontractorDocument::active()->sole();

        $this->assertTrue($old->isSuperseded());
        $this->assertSame($new->id, $old->superseded_by_id);
        $this->assertSame($this->insurance->id, $new->document_type_id);
        $this->assertSame('valid', $old->expiry_status, 'A superseded document no longer reports expiry.');
        $this->assertSame('valid', $new->expiry_status);
        $this->assertSame($old->id, $new->supersedes->id);
    }

    public function test_a_renewal_cannot_start_from_another_vendors_document(): void
    {
        $mine = $this->makeSubcontractor();
        $theirs = $this->makeSubcontractor();
        $document = $this->makeDocument($theirs);
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($clerk)
            ->test(SubcontractorShow::class, ['subcontractor' => $mine])
            ->call('startRenewal', $document->id);
    }

    public function test_a_retired_type_is_refused_on_upload(): void
    {
        $sub = $this->makeSubcontractor();
        $this->insurance->update(['is_active' => false]);
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        $this->upload($clerk, $sub)->call('uploadDocument')->assertHasErrors(['document_type_id']);
    }

    /*
    |---------------------------------------------------------------------------
    | Archive and reactivate
    |---------------------------------------------------------------------------
    */

    public function test_archiving_needs_its_own_grant(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->makeDocument($sub);
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents', 'vendors.edit']);

        Livewire::actingAs($clerk)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startArchive', $document->id)
            ->assertForbidden();

        Livewire::actingAs($clerk)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->set('archiving_document_id', $document->id)
            ->set('archive_reason', 'No longer on site')
            ->call('archiveDocument')
            ->assertForbidden();

        $this->assertTrue($document->fresh()->isActive());
    }

    public function test_archiving_records_who_and_why_and_reactivating_clears_it(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->makeDocument($sub, ['expiration_date' => now()->subDay()->toDateString()]);
        $keeper = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.archive_documents']);

        $this->assertSame('expired', $document->expiry_status);

        Livewire::actingAs($keeper)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startArchive', $document->id)
            ->assertSet('archiving_document_id', $document->id)
            ->call('archiveDocument')
            ->assertHasErrors(['archive_reason'])
            ->set('archive_reason', 'Vendor no longer carries this cover')
            ->call('archiveDocument')
            ->assertHasNoErrors()
            ->assertSet('archiving_document_id', null);

        $document->refresh();
        $this->assertTrue($document->isArchived());
        $this->assertSame($keeper->id, $document->archived_by);
        $this->assertSame('Vendor no longer carries this cover', $document->archive_reason);
        $this->assertNotNull($document->archived_at);
        $this->assertSame('valid', $document->expiry_status, 'An archived document no longer reports expiry.');

        Livewire::actingAs($keeper)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('reactivateDocument', $document->id);

        $document->refresh();
        $this->assertTrue($document->isActive());
        $this->assertNull($document->archived_by);
        $this->assertNull($document->archive_reason);
        $this->assertSame('expired', $document->expiry_status);
    }

    public function test_a_superseded_document_cannot_be_archived_or_renewed_again(): void
    {
        $sub = $this->makeSubcontractor();
        $old = $this->makeDocument($sub);
        $new = $this->makeDocument($sub, ['file_path' => "subcontractor-documents/{$sub->id}/coi-2.pdf"]);
        $old->supersedeWith($new);

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startArchive', $old->id)
            ->assertStatus(422);

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startRenewal', $old->id)
            ->assertStatus(422);
    }

    /*
    |---------------------------------------------------------------------------
    | Delete
    |---------------------------------------------------------------------------
    */

    public function test_deleting_a_document_answers_to_vendors_delete(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->makeDocument($sub);
        $clerk = $this->roleWith([
            'projects.view', 'project.view', 'vendors.view', 'vendors.edit',
            'vendors.renew_documents', 'vendors.archive_documents',
        ]);

        Livewire::actingAs($clerk)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('deleteDocument', $document->id)
            ->assertForbidden();

        $this->assertNotNull($document->fresh());

        $deleter = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.delete']);

        Livewire::actingAs($deleter)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('deleteDocument', $document->id);

        $this->assertNull($document->fresh());
        Storage::disk('local')->assertMissing("subcontractor-documents/{$sub->id}/coi.pdf");
    }

    /*
    |---------------------------------------------------------------------------
    | The file itself
    |---------------------------------------------------------------------------
    */

    public function test_the_file_is_served_on_the_view_grant_and_refused_without_it(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->makeDocument($sub);

        $this->actingAs($this->reader())
            ->get(route('files.download', ['path' => $document->file_path]))
            ->assertOk();

        $nobody = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($nobody)
            ->get(route('files.download', ['path' => $document->file_path]))
            ->assertForbidden();

        $this->actingAs($this->reader())
            ->get(route('files.download', ['path' => "subcontractor-documents/{$sub->id}/nobody-filed-this.pdf"]))
            ->assertNotFound();
    }

    /*
    |---------------------------------------------------------------------------
    | Expiry, on whole days
    |---------------------------------------------------------------------------
    */

    public function test_expiry_status_turns_on_the_thirty_day_line(): void
    {
        $sub = $this->makeSubcontractor();

        $at = fn (int $days) => $this->makeDocument($sub, [
            'expiration_date' => now()->addDays($days)->toDateString(),
            'file_path' => "subcontractor-documents/{$sub->id}/{$days}.pdf",
        ])->expiry_status;

        $this->assertSame('expired', $at(-1));
        $this->assertSame('expiring_soon', $at(0));
        $this->assertSame('expiring_soon', $at(30));
        $this->assertSame('valid', $at(31));
        $this->assertSame('valid', $at(60));

        $this->assertSame(2, SubcontractorDocument::expiringWithin()->count(), 'Today and day 30 are inside the window; day 31 and the past are not.');
        $this->assertSame(1, SubcontractorDocument::expired()->count());
    }

    /*
    |---------------------------------------------------------------------------
    | The tab itself
    |---------------------------------------------------------------------------
    */

    public function test_the_documents_tab_renders_every_state_and_its_dialogs(): void
    {
        $sub = $this->makeSubcontractor();
        $licence = DocumentType::create(['name' => 'Contractor License', 'requires_expiration' => true, 'sort_order' => 2]);
        DocumentType::create(['name' => 'W9', 'requires_expiration' => false, 'sort_order' => 3]);

        $expired = $this->makeDocument($sub, ['expiration_date' => now()->subDays(3)->toDateString(), 'file_name' => 'old-coi.pdf']);
        $current = $this->makeDocument($sub, [
            'expiration_date' => now()->addDays(200)->toDateString(),
            'file_path' => "subcontractor-documents/{$sub->id}/coi-2.pdf",
            'file_name' => 'coi-2.pdf',
            'notes' => 'Policy 4471',
        ]);
        $expired->supersedeWith($current);

        $archived = $this->makeDocument($sub, [
            'expiration_date' => now()->addDays(10)->toDateString(),
            'file_path' => "subcontractor-documents/{$sub->id}/coi-3.pdf",
            'file_name' => 'coi-3.pdf',
        ]);
        $archived->archive($this->admin, 'Duplicate policy');

        $component = Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('setActiveTab', 'documents')
            ->assertSee('Required documents')
            ->assertSee('General Liability Insurance')
            ->assertSee('Contractor License')
            ->assertSee('Missing')                       // the licence has nothing on file
            ->assertSee('coi-2.pdf')
            ->assertSee('Policy 4471')
            ->assertSeeHtml('showHistory('.$current->id.')')
            ->assertSee('(2)')                             // the current policy and the one it replaced
            ->assertSee('(1)')                             // the archived one stands alone
            ->assertSee('Duplicate policy')                // archived rows stay on the tab, reactivatable
            ->assertSee('Replaced old-coi.pdf')            // the superseded row itself is reached through History only
            ->assertSee('1 active document')
            ->assertSee('2 in history')
            ->assertDontSee('1 expired');                // the expired one was superseded, so it no longer counts

        // The current policy's history is its own chain: itself and old-coi.pdf,
        // never the archived coi-3.pdf, which is the head of no chain but its own.
        $component
            ->call('showHistory', $current->id)
            ->assertSet('history_document_id', $current->id)
            ->assertSee('Document history')
            ->assertSee('This document and 1 earlier version')
            ->assertSee('old-coi.pdf')
            ->assertSee('Replaced by')
            ->call('closeHistory')
            ->assertSet('history_document_id', null);

        $ids = fn ($id) => collect($component->call('showHistory', $id)->viewData('historyEntries'))->pluck('id')->all();

        $this->assertSame([$current->id, $expired->id], $ids($current->id), 'Newest first: the renewal, then what it replaced.');
        $this->assertSame([$archived->id], $ids($archived->id));
        $this->assertSame([$current->id, $expired->id], $ids($expired->id), 'Opened from the superseded end, the same chain.');
        $component->call('closeHistory');

        $component
            ->call('startRenewal', $current->id)
            ->assertSee('Renew Document')
            ->assertSee('Renewing General Liability Insurance')
            ->assertSee('Replaces coi-2.pdf')
            ->call('cancelUploadForm')
            ->assertSet('showUploadForm', false)
            ->assertSet('renewing_document_id', null);

        $component
            ->call('startUpload', $licence->id)
            ->assertSet('document_type_id', (string) $licence->id)
            ->assertSee('Upload Document')
            ->call('cancelUploadForm');

        $component
            ->call('startArchive', $current->id)
            ->assertSee('Archive Document')
            ->assertSee('Why is this document no longer required?')
            ->call('cancelArchive')
            ->assertSet('archiving_document_id', null);
    }

    public function test_the_tab_offers_no_write_buttons_to_a_reader(): void
    {
        $sub = $this->makeSubcontractor();
        $this->makeDocument($sub, ['expiration_date' => now()->subDay()->toDateString()]);

        Livewire::actingAs($this->reader())
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('setActiveTab', 'documents')
            ->assertSee('coi.pdf')
            ->assertSee('Expired')
            ->assertDontSee('Upload Document')
            ->assertDontSee('startRenewal')
            ->assertDontSee('startArchive')
            ->assertDontSee('deleteDocument');
    }

    /*
    |---------------------------------------------------------------------------
    | Badges: the header and the list
    |---------------------------------------------------------------------------
    */

    public function test_the_header_badge_reports_the_worst_active_state_with_counts(): void
    {
        $sub = $this->makeSubcontractor();
        $this->makeDocument($sub, ['expiration_date' => now()->subDays(2)->toDateString()]);
        $this->makeDocument($sub, ['expiration_date' => now()->addDays(5)->toDateString(), 'file_path' => "subcontractor-documents/{$sub->id}/b.pdf"]);
        $superseded = $this->makeDocument($sub, ['expiration_date' => now()->subDays(9)->toDateString(), 'file_path' => "subcontractor-documents/{$sub->id}/c.pdf"]);
        $superseded->supersedeWith(SubcontractorDocument::where('file_path', "subcontractor-documents/{$sub->id}/b.pdf")->sole());

        Livewire::actingAs($this->reader())
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->assertSee('Documents expired')
            ->assertSee('1 expired')
            ->assertSee('1 expiring soon');

        $this->assertSame('expired', $sub->fresh()->document_health);
    }

    public function test_the_index_shows_the_badge_and_filters_on_it_without_a_query_per_row(): void
    {
        $expired = $this->makeSubcontractor();
        $this->makeDocument($expired, ['expiration_date' => now()->subDay()->toDateString()]);

        $expiring = $this->makeSubcontractor();
        $this->makeDocument($expiring, ['expiration_date' => now()->addDays(3)->toDateString()]);

        $current = $this->makeSubcontractor();
        $this->makeDocument($current, ['expiration_date' => now()->addDays(300)->toDateString()]);

        $renewed = $this->makeSubcontractor();
        $old = $this->makeDocument($renewed, ['expiration_date' => now()->subDay()->toDateString()]);
        $new = $this->makeDocument($renewed, ['expiration_date' => now()->addDays(300)->toDateString(), 'file_path' => "subcontractor-documents/{$renewed->id}/new.pdf"]);
        $old->supersedeWith($new);

        $bare = $this->makeSubcontractor();

        $names = fn ($component) => collect($component->viewData('subcontractors')->items())->pluck('company_name')->all();

        $all = Livewire::actingAs($this->reader())->test(SubcontractorIndex::class);
        $all->assertSee('Documents expired')->assertSee('Documents expiring soon')->assertSee('Documents current')->assertSee('No dated documents');
        $this->assertCount(5, $names($all));

        $this->assertSame([$expired->company_name], $names($all->set('documentHealth', 'expired')));
        $this->assertSame([$expiring->company_name], $names($all->set('documentHealth', 'expiring_soon')));
        $this->assertEqualsCanonicalizing([$current->company_name, $renewed->company_name], $names($all->set('documentHealth', 'valid')), 'A renewed vendor is current: its expired document was superseded.');
        $this->assertSame([$bare->company_name], $names($all->set('documentHealth', 'none')));
        $this->assertCount(5, $names($all->set('documentHealth', 'not-a-state')), 'An unknown filter value is ignored, not trusted.');

        $all->set('documentHealth', 'expired')->set('search', $expiring->company_name);
        $this->assertSame([], $names($all));
        $all->assertSee('No subcontractor matches both the search and the documents filter.');

        $all->call('clearFilters')->assertSet('search', '')->assertSet('documentHealth', '');

        // Five vendors on the page: the three counts ride on the vendors
        // query as sub-selects, and no row asks the documents table on its own.
        DB::enableQueryLog();
        Livewire::actingAs($this->reader())->test(SubcontractorIndex::class);
        $documentQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn ($sql) => str_starts_with($sql, 'select') && str_contains($sql, 'subcontractor_documents') && ! str_contains($sql, 'from "vendors"'));
        DB::disableQueryLog();

        $this->assertCount(0, $documentQueries, 'The documents badge must not cost a query per row.');
    }

    /*
    |---------------------------------------------------------------------------
    | Review findings, 2 Sep 2026
    |---------------------------------------------------------------------------
    */

    public function test_deleting_a_renewal_gives_the_replaced_document_its_place_back(): void
    {
        $sub = $this->makeSubcontractor();
        $old = $this->makeDocument($sub, ['expiration_date' => now()->addDays(40)->toDateString()]);
        $new = $this->makeDocument($sub, ['file_path' => "subcontractor-documents/{$sub->id}/wrong.pdf"]);
        $old->supersedeWith($new);
        $this->assertTrue($old->fresh()->isSuperseded());

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('deleteDocument', $new->id);

        $old->refresh();
        $this->assertTrue($old->isActive(), 'The wrong renewal is gone; the certificate it replaced is in force again.');
        $this->assertNull($old->superseded_by_id);
        $this->assertSame('valid', $old->expiry_status);
    }

    public function test_a_document_under_a_retired_type_can_still_be_renewed_and_keeps_that_type(): void
    {
        $sub = $this->makeSubcontractor();
        $old = $this->makeDocument($sub, ['expiration_date' => now()->addDays(3)->toDateString()]);
        $this->insurance->update(['is_active' => false]);
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        Livewire::actingAs($clerk)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startRenewal', $old->id)
            ->set('document_file', UploadedFile::fake()->create('renewed.pdf', 12, 'application/pdf'))
            ->set('expiration_date', now()->addDays(365)->toDateString())
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $this->assertTrue($old->fresh()->isSuperseded());
        $this->assertSame($this->insurance->id, SubcontractorDocument::active()->sole()->document_type_id);
    }

    public function test_a_retired_type_stops_driving_the_badge_the_card_and_the_counts_alike(): void
    {
        $sub = $this->makeSubcontractor();
        $this->makeDocument($sub, ['expiration_date' => now()->subDays(2)->toDateString()]);

        $this->assertSame('expired', $sub->fresh()->document_health);

        $this->insurance->update(['is_active' => false]);

        $this->assertSame('none', $sub->fresh()->document_health, 'A retired type takes its documents out of the watch.');
        $this->assertSame(0, SubcontractorDocument::expired()->count());

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('setActiveTab', 'documents')
            ->assertSee('Retired type')
            ->assertDontSee('Documents expired')
            ->assertDontSee('1 expired');
    }

    public function test_a_vendor_that_stopped_being_a_subcontractor_is_nobodys_compliance_problem(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->makeDocument($sub, ['expiration_date' => now()->subDays(2)->toDateString()]);
        $this->assertSame(1, SubcontractorDocument::expired()->count());

        Vendor::whereKey($sub->id)->update(['is_subcontractor' => false, 'is_supplier' => true]);

        $this->assertSame(0, SubcontractorDocument::expired()->count());
        $this->assertNotNull($document->fresh()->vendor, 'The unscoped relation still finds the owner.');
        $this->assertNull($document->fresh()->subcontractor, 'The scoped one does not — which is why the notifier must not use it.');
    }

    public function test_an_undated_document_under_a_dated_type_is_not_passed_off_as_current(): void
    {
        $sub = $this->makeSubcontractor();
        $undated = $this->makeDocument($sub, ['expiration_date' => null]);
        $expired = $this->makeDocument($sub, ['expiration_date' => now()->subDay()->toDateString(), 'file_path' => "subcontractor-documents/{$sub->id}/e.pdf"]);

        $this->assertSame('undated', $undated->expiry_status);
        $this->assertSame('expired', SubcontractorDocument::worstExpiry(['undated', 'valid', 'expired']));
        $this->assertSame('undated', SubcontractorDocument::worstExpiry(['undated', 'valid']));

        $component = Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('setActiveTab', 'documents');

        // The Required card's Renew shortcut targets the dated document, not the undated one.
        $required = $component->viewData('requiredTypes')->first();
        $this->assertSame('expired', $required['status']);
        $this->assertSame($expired->id, $required['document']->id);

        $expired->delete();
        $component = Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('setActiveTab', 'documents')
            ->assertSee('No expiration date')
            ->assertSee('On file without an expiration date');
        $this->assertSame('undated', $component->viewData('requiredTypes')->first()['status']);
    }

    public function test_the_stamps_the_reminders_leave_are_shown_on_the_row(): void
    {
        $sub = $this->makeSubcontractor();
        // The stamps are written by the notifier, never mass-assigned.
        $this->makeDocument($sub, ['expiration_date' => now()->addDays(5)->toDateString()])
            ->forceFill(['notified_30_at' => now()->subDays(25), 'notified_15_at' => now()->subDays(10)])
            ->save();

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('setActiveTab', 'documents')
            ->assertSee('Reminders sent: 30 days before, 15 days before');
    }

    /*
    |---------------------------------------------------------------------------
    | V2 — the shared upload path, beside the legacy files
    |---------------------------------------------------------------------------
    */

    /** A file the uploader would have put in storage against the vendor. */
    protected function waitingFile(Subcontractor $sub, string $name = 'coi-cloud.pdf', array $attributes = []): FileUpload
    {
        $file = new FileUpload(array_merge([
            'disk' => 'local',
            'original_name' => $name,
            'size_bytes' => 5,
            'mime_type' => 'application/pdf',
            'upload_status' => FileUpload::STATUS_AVAILABLE,
            'uploaded_by' => $this->admin->id,
        ], $attributes));
        $file->attachable()->associate(Vendor::withoutGlobalScopes()->findOrFail($sub->id));
        $file->object_key = "vendors/{$sub->id}/".str()->uuid()."/{$name}";
        $file->save();

        Storage::disk('local')->put($file->object_key, 'bytes');

        return $file;
    }

    protected function withBucket(): void
    {
        config([
            'documents.disk' => 'r2',
            'filesystems.disks.r2.key' => 'k', 'filesystems.disks.r2.secret' => 's',
            'filesystems.disks.r2.bucket' => 'b', 'filesystems.disks.r2.endpoint' => 'https://example.test',
        ]);
    }

    public function test_with_a_bucket_the_file_waits_on_the_vendor_and_moves_onto_the_document_on_save(): void
    {
        $this->withBucket();
        $sub = $this->makeSubcontractor();
        $file = $this->waitingFile($sub);
        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        $component = Livewire::actingAs($clerk)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startUpload')
            ->set('document_type_id', (string) $this->insurance->id)
            ->set('expiration_date', now()->addDays(365)->toDateString());

        // Saving without a file says so, instead of writing a row with nothing behind it.
        $component->call('uploadDocument')->assertHasErrors(['document_file']);
        $this->assertSame(0, SubcontractorDocument::count());

        $component->call('documentFileUploaded', $file->id)
            ->assertSet('pending_file_id', $file->id)
            ->assertSee('coi-cloud.pdf')
            ->call('uploadDocument')
            ->assertHasNoErrors()
            ->assertSet('pending_file_id', null);

        $document = SubcontractorDocument::sole();
        $this->assertSame($file->id, $document->file_upload_id);
        $this->assertNull($document->file_path);
        $this->assertSame('coi-cloud.pdf', $document->file_name);
        $this->assertSame(5, $document->file_size);

        $file->refresh();
        $this->assertSame(SubcontractorDocument::class, $file->attachable_type, 'The file belongs to the document now, not to the vendor.');
        $this->assertSame($document->id, $file->attachable_id);

        // Served through the one download route; on the local disk it streams.
        $this->actingAs($this->reader())
            ->get($document->downloadUrl())
            ->assertOk()
            ->assertDownload('coi-cloud.pdf');

        // Deleting the document takes the object and the row with it.
        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('deleteDocument', $document->id);

        Storage::disk('local')->assertMissing($file->object_key);
        $this->assertNull(FileUpload::withTrashed()->find($file->id));
    }

    public function test_a_file_waiting_on_another_vendor_cannot_be_claimed(): void
    {
        $this->withBucket();
        $mine = $this->makeSubcontractor();
        $theirs = $this->makeSubcontractor();
        $file = $this->waitingFile($theirs);

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $mine])
            ->call('startUpload')
            ->call('documentFileUploaded', $file->id)
            ->assertNotFound();

        $this->assertNotNull($file->fresh());
    }

    public function test_cancelling_the_dialog_or_dropping_a_second_file_removes_the_first(): void
    {
        $this->withBucket();
        $sub = $this->makeSubcontractor();
        $first = $this->waitingFile($sub, 'first.pdf');
        $second = $this->waitingFile($sub, 'second.pdf');

        $component = Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $sub])
            ->call('startUpload')
            ->call('documentFileUploaded', $first->id)
            ->call('documentFileUploaded', $second->id)
            ->assertSet('pending_file_id', $second->id);

        Storage::disk('local')->assertMissing($first->object_key);
        $this->assertNull(FileUpload::withTrashed()->find($first->id));

        $component->call('cancelUploadForm')->assertSet('pending_file_id', null);

        Storage::disk('local')->assertMissing($second->object_key);
        $this->assertNull(FileUpload::withTrashed()->find($second->id));
        $this->assertSame(0, SubcontractorDocument::count());
    }

    public function test_the_download_route_serves_legacy_files_on_the_view_grant_and_only_for_their_own_vendor(): void
    {
        $sub = $this->makeSubcontractor();
        $other = $this->makeSubcontractor();
        $legacy = $this->makeDocument($sub);

        $this->actingAs($this->reader())->get($legacy->downloadUrl())->assertOk()->assertDownload('coi.pdf');

        $this->actingAs($this->roleWith(['projects.view', 'project.view']))
            ->get($legacy->downloadUrl())
            ->assertForbidden();

        $this->actingAs($this->reader())
            ->get(route('subcontractors.documents.download', [$other->id, $legacy->id]))
            ->assertNotFound();
    }

    public function test_the_upload_handshake_takes_a_vendor_target_from_renewers_only(): void
    {
        $sub = $this->makeSubcontractor();
        $payload = ['target_type' => 'vendor', 'target_id' => $sub->id, 'file_name' => 'coi.pdf', 'size_bytes' => 1024, 'mime_type' => 'application/pdf'];

        $this->actingAs($this->reader())->postJson(route('uploads.init'), $payload)->assertForbidden();

        $clerk = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.renew_documents']);

        $response = $this->actingAs($clerk)->postJson(route('uploads.init'), $payload)->assertOk();

        $file = FileUpload::findOrFail($response->json('version_id'));
        $this->assertSame(Vendor::class, $file->attachable_type);
        $this->assertSame($sub->id, $file->attachable_id);
        $this->assertStringStartsWith("vendors/{$sub->id}/", $file->object_key);
    }

    public function test_a_file_left_waiting_on_a_vendor_is_pruned_but_a_filed_one_is_kept(): void
    {
        $sub = $this->makeSubcontractor();
        $abandoned = $this->waitingFile($sub, 'abandoned.pdf');
        $abandoned->forceFill(['created_at' => now()->subDays(2)])->save();

        $filed = $this->waitingFile($sub, 'filed.pdf');
        $document = SubcontractorDocument::create([
            'subcontractor_id' => $sub->id, 'document_type_id' => $this->insurance->id,
            'file_upload_id' => $filed->id, 'file_name' => 'filed.pdf', 'file_size' => 5,
            'expiration_date' => now()->addDays(90)->toDateString(), 'uploaded_by' => $this->admin->id,
        ]);
        $filed->attachable()->associate($document)->save();
        $filed->forceFill(['created_at' => now()->subDays(2)])->save();

        $result = app(\App\Services\FileUploadService::class)->pruneStaleUploads();

        $this->assertSame(1, $result['files']);
        $this->assertNull(FileUpload::withTrashed()->find($abandoned->id));
        Storage::disk('local')->assertMissing($abandoned->object_key);
        $this->assertNotNull($filed->fresh());
        Storage::disk('local')->assertExists($filed->object_key);
    }
}
