<?php

namespace Tests\Feature\Permissions;

use App\Livewire\SystemSettings\DocumentTypeSettings;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\SubcontractorDocument;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 6 of docs/vendor-document-expiry-plan.md: the Document Types screen
 * and the add-only, country-aware seeder.
 */
class DocumentTypeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function fileUnder(DocumentType $type): SubcontractorDocument
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Sub '.str()->random(4), 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();

        return SubcontractorDocument::create([
            'subcontractor_id' => $vendor->id,
            'document_type_id' => $type->id,
            'file_path' => "subcontractor-documents/{$vendor->id}/x.pdf",
            'file_name' => 'x.pdf',
            'file_size' => 1,
            'uploaded_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | The screen
    |---------------------------------------------------------------------------
    */

    public function test_a_settings_reader_can_look_but_not_change_anything(): void
    {
        $type = DocumentType::create(['name' => 'Licença Ambiental', 'requires_expiration' => true, 'sort_order' => 1]);
        $reader = $this->roleWith(['settings.view']);

        Livewire::actingAs($reader)
            ->test(DocumentTypeSettings::class)
            ->assertOk()
            ->assertSee('Licença Ambiental')
            ->assertDontSee('wire:click="create"', false);

        $fresh = fn () => Livewire::actingAs($reader)->test(DocumentTypeSettings::class);

        $fresh()->call('create')->assertForbidden();
        $fresh()->call('edit', $type->id)->assertForbidden();
        $fresh()->call('toggleActive', $type->id)->assertForbidden();
        $fresh()->call('delete', $type->id)->assertForbidden();
        $fresh()->set('name', 'Sneaky')->call('save')->assertForbidden();

        $this->assertNull(DocumentType::where('name', 'Sneaky')->first());
        $this->assertTrue($type->fresh()->is_active);

        Livewire::actingAs($this->roleWith(['projects.view']))
            ->test(DocumentTypeSettings::class)
            ->assertForbidden();
    }

    public function test_an_editor_can_create_edit_retire_and_reactivate(): void
    {
        $editor = $this->roleWith(['settings.view', 'settings.edit']);

        $component = Livewire::actingAs($editor)->test(DocumentTypeSettings::class);

        $component->call('create')
            ->assertSet('showFormModal', true)
            ->set('name', '  Alvará Sanitário  ')
            ->set('description', 'Vigilância sanitária')
            ->set('requires_expiration', true)
            ->set('sort_order', 3)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $type = DocumentType::where('name', 'Alvará Sanitário')->sole();
        $this->assertTrue($type->requires_expiration);
        $this->assertTrue($type->is_active);
        $this->assertSame(3, $type->sort_order);
        $this->assertNull($type->key, 'A type made on the screen carries no seed key.');

        // A second type with the same name is refused — padded or not.
        $component->call('create')->set('name', 'Alvará Sanitário')->call('save')->assertHasErrors(['name']);
        $component->call('create')->set('name', '  Alvará Sanitário ')->call('save')->assertHasErrors(['name']);
        $this->assertSame(1, DocumentType::where('name', 'Alvará Sanitário')->count());

        $component->call('edit', $type->id)
            ->assertSet('name', 'Alvará Sanitário')
            ->set('name', 'Alvará Sanitário (ANVISA)')
            ->set('requires_expiration', false)
            ->call('save')
            ->assertHasNoErrors();

        $type->refresh();
        $this->assertSame('Alvará Sanitário (ANVISA)', $type->name);
        $this->assertFalse($type->requires_expiration);

        $component->call('toggleActive', $type->id);
        $this->assertFalse($type->fresh()->is_active);
        $this->assertFalse(DocumentType::active()->whereKey($type->id)->exists(), 'A retired type leaves the upload picker.');

        $component->call('toggleActive', $type->id);
        $this->assertTrue($type->fresh()->is_active);
    }

    public function test_a_type_with_documents_can_be_retired_but_never_deleted(): void
    {
        $editor = $this->roleWith(['settings.view', 'settings.edit']);
        $used = DocumentType::create(['name' => 'Licença Ambiental', 'requires_expiration' => true, 'sort_order' => 1]);
        $unused = DocumentType::create(['name' => 'Typo', 'requires_expiration' => false, 'sort_order' => 2]);
        $document = $this->fileUnder($used);

        $component = Livewire::actingAs($editor)->test(DocumentTypeSettings::class);

        $component->call('delete', $used->id);
        $this->assertNotNull($used->fresh(), 'A type with documents survives a delete call.');
        $this->assertNotNull($document->fresh());

        $component->call('toggleActive', $used->id);
        $this->assertFalse($used->fresh()->is_active);
        $this->assertSame($used->id, $document->fresh()->document_type_id, 'The document keeps its retired type.');

        $component->call('delete', $unused->id);
        $this->assertNull($unused->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | The seeder
    |---------------------------------------------------------------------------
    */

    public function test_the_seeder_follows_the_country_and_never_touches_an_existing_row(): void
    {
        // Whatever the suite's country pin seeded, start from a clean table.
        DocumentType::query()->delete();

        // A Brazilian install that was set up with the American list and has
        // since renamed and retired things on the screen.
        (new DocumentTypeSeeder)->run('US');
        $this->assertSame(8, DocumentType::count());

        DocumentType::where('key', 'us.auto_insurance')->update(['is_active' => false, 'sort_order' => 50]);
        DocumentType::where('key', 'other')->update(['description' => 'Anything else', 'requires_expiration' => true]);
        DocumentType::where('key', 'us.w9')->update(['name' => 'Form W-9 (renamed)']);

        (new DocumentTypeSeeder)->run('BR');

        $this->assertSame(8 + 9, DocumentType::count(), 'Nine certidões added beside the eight American rows; "Other" is shared.');

        $auto = DocumentType::where('key', 'us.auto_insurance')->sole();
        $this->assertFalse($auto->is_active, 'A retired row stays retired.');
        $this->assertSame(50, $auto->sort_order);

        $other = DocumentType::where('key', 'other')->sole();
        $this->assertSame('Anything else', $other->description, 'An edited row keeps its edit.');
        $this->assertTrue($other->requires_expiration);

        $this->assertTrue(DocumentType::where('key', 'br.fgts')->sole()->requires_expiration);
        $this->assertFalse(DocumentType::where('key', 'br.contrato_social')->sole()->requires_expiration);

        // Running either list again changes nothing — and a renamed row is
        // found by its key, never re-created under its old name.
        (new DocumentTypeSeeder)->run('BR');
        (new DocumentTypeSeeder)->run('US');
        $this->assertSame(17, DocumentType::count());
        $this->assertSame(0, DocumentType::where('name', 'W9')->count());
        $this->assertSame('Form W-9 (renamed)', DocumentType::where('key', 'us.w9')->sole()->name);
    }

    public function test_rows_seeded_before_the_key_existed_are_claimed_by_name_once_from_either_list(): void
    {
        DocumentType::query()->delete();

        // The shape a Brazilian production had: the American list, seeded by
        // name, no key column filled.
        DocumentType::create(['name' => 'W9', 'description' => 'old', 'requires_expiration' => false, 'sort_order' => 1]);
        DocumentType::create(['name' => 'Other', 'description' => 'old', 'requires_expiration' => false, 'sort_order' => 99]);

        (new DocumentTypeSeeder)->run('BR');

        $w9 = DocumentType::where('name', 'W9')->sole();
        $this->assertSame('us.w9', $w9->key, 'An American row on a Brazilian install still gets its key.');
        $this->assertSame('old', $w9->description, 'Claiming the row does not rewrite it.');
        $this->assertSame('other', DocumentType::where('name', 'Other')->sole()->key);
        $this->assertSame(2 + 9, DocumentType::count(), 'Only the Brazilian list is added; the American one is not completed.');
    }

    public function test_the_other_countrys_unused_types_are_retired_and_used_ones_kept(): void
    {
        DocumentType::query()->delete();
        (new DocumentTypeSeeder)->run('US');
        (new DocumentTypeSeeder)->run('BR');

        $insurance = DocumentType::where('key', 'us.general_liability_insurance')->sole();
        $this->fileUnder($insurance);

        $retired = DocumentTypeSeeder::retireForeignUnused('BR');

        $this->assertSame(6, $retired, 'Seven American types minus the one holding a document.');
        $this->assertTrue($insurance->fresh()->is_active, 'A type with documents is never retired behind the owner\'s back.');
        $this->assertFalse(DocumentType::where('key', 'us.w9')->sole()->is_active);
        $this->assertTrue(DocumentType::where('key', 'other')->sole()->is_active, '"Other" belongs to both lists.');
        $this->assertSame(9, DocumentType::where('key', 'like', 'br.%')->where('is_active', true)->count());

        // Running it again finds nothing left to do.
        $this->assertSame(0, DocumentTypeSeeder::retireForeignUnused('BR'));
    }

    public function test_every_seeded_name_and_description_is_translated(): void
    {
        $pt = json_decode(file_get_contents(lang_path('pt_BR.json')), true);
        $en = json_decode(file_get_contents(lang_path('en.json')), true);

        foreach (['US', 'BR'] as $country) {
            foreach (DocumentTypeSeeder::typesFor($country) as $type) {
                $this->assertArrayHasKey($type['name'], $pt, "pt_BR is missing the type name '{$type['name']}'.");
                $this->assertArrayHasKey($type['name'], $en);
                $this->assertArrayHasKey($type['description'], $pt, "pt_BR is missing the description of '{$type['name']}'.");
            }
        }
    }
}
