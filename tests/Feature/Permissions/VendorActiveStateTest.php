<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Approval\ApprovalForm;
use App\Livewire\Subcontractor\SubcontractorIndex;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Livewire\Supplier\SupplierIndex;
use App\Livewire\Supplier\SupplierShow;
use App\Livewire\Vendor\VendorIndex;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A vendor can be switched off without being deleted. Off, it stays on the
 * lists behind a status filter and keeps every record that names it, but
 * is not offered when a new record picks a vendor. The switch is an edit
 * (`vendors.edit`), reproduced on every vendor list and page.
 */
class VendorActiveStateTest extends TestCase
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

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeVendor(string $name, bool $supplier, bool $subcontractor, bool $active = true): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => $name,
            'is_supplier' => $supplier,
            'is_subcontractor' => $subcontractor,
            'is_active' => $active,
            'created_by' => $this->admin->id,
        ])->save();

        return $vendor;
    }

    public function test_a_new_vendor_is_active_and_the_switch_records_who_turned_it_off(): void
    {
        $vendor = Supplier::create(['name' => 'Cement Depot', 'created_by' => $this->admin->id]);

        $this->assertTrue($vendor->fresh()->is_active);

        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->call('toggleActive', $vendor->id);

        $vendor->refresh();
        $this->assertFalse($vendor->is_active);
        $this->assertNotNull($vendor->deactivated_at);
        $this->assertSame($this->admin->id, $vendor->deactivated_by);

        // Reversible, and the audit facts are cleared with it.
        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->call('toggleActive', $vendor->id);

        $vendor->refresh();
        $this->assertTrue($vendor->is_active);
        $this->assertNull($vendor->deactivated_at);
        $this->assertNull($vendor->deactivated_by);
    }

    public function test_the_switch_needs_the_edit_grant_on_every_screen_that_offers_it(): void
    {
        $supplier = $this->makeVendor('Cement Depot', true, false);
        $sub = $this->makeVendor('Steel Erectors', false, true);

        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view']);

        Livewire::actingAs($reader)->test(VendorIndex::class)->call('toggleActive', $supplier->id)->assertForbidden();
        Livewire::actingAs($reader)->test(SupplierIndex::class)->call('toggleActive', $supplier->id)->assertForbidden();
        Livewire::actingAs($reader)->test(SubcontractorIndex::class)->call('toggleActive', $sub->id)->assertForbidden();
        Livewire::actingAs($reader)->test(SupplierShow::class, ['supplier' => Supplier::findOrFail($supplier->id)])
            ->call('toggleActive', $supplier->id)->assertForbidden();
        Livewire::actingAs($reader)->test(SubcontractorShow::class, ['subcontractor' => Subcontractor::findOrFail($sub->id)])
            ->call('toggleActive', $sub->id)->assertForbidden();

        $this->assertTrue($supplier->fresh()->is_active);
        $this->assertTrue($sub->fresh()->is_active);

        // A reader sees the state as a chip, never as a switch.
        Livewire::actingAs($reader)->test(VendorIndex::class)->assertDontSee('toggleActive');

        $editor = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.edit']);

        Livewire::actingAs($editor)->test(SupplierShow::class, ['supplier' => Supplier::findOrFail($supplier->id)])
            ->assertSee('toggleActive')
            ->call('toggleActive', $supplier->id)
            ->assertSee(__('This vendor is inactive.'));
        Livewire::actingAs($editor)->test(SubcontractorShow::class, ['subcontractor' => Subcontractor::findOrFail($sub->id)])
            ->call('toggleActive', $sub->id)
            ->assertSee(__('This vendor is inactive.'));

        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertFalse($sub->fresh()->is_active);
    }

    public function test_every_list_filters_by_status_and_flags_the_inactive_rows(): void
    {
        $this->makeVendor('Cement Depot', true, false);
        $this->makeVendor('Old Quarry', true, false, active: false);
        $this->makeVendor('Steel Erectors', false, true);
        $this->makeVendor('Gone Framing', false, true, active: false);

        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->assertSee('Cement Depot')->assertSee('Old Quarry')->assertSee('Steel Erectors')->assertSee('Gone Framing')
            ->assertSee(__('Inactive (:count)', ['count' => 2]))
            // The type cards split their total into active and inactive.
            ->assertSee(trans_choice(':count active|:count active', 2, ['count' => 2]))
            ->assertSee(trans_choice(':count inactive|:count inactive', 2, ['count' => 2]))
            ->set('status', 'inactive')
            ->assertDontSee('Cement Depot')->assertSee('Old Quarry')->assertDontSee('Steel Erectors')->assertSee('Gone Framing')
            ->set('status', 'active')
            ->assertSee('Cement Depot')->assertDontSee('Old Quarry')->assertSee('Steel Erectors')->assertDontSee('Gone Framing')
            ->call('clearFilters')
            ->assertSet('status', '')
            ->assertSee('Old Quarry');

        Livewire::actingAs($this->admin)
            ->test(SupplierIndex::class)
            ->assertSee('Cement Depot')->assertSee('Old Quarry')
            ->set('status', 'inactive')
            ->assertDontSee('Cement Depot')->assertSee('Old Quarry')
            ->set('status', 'active')
            ->assertSee('Cement Depot')->assertDontSee('Old Quarry')
            ->call('clearFilters')
            ->assertSet('status', '');

        Livewire::actingAs($this->admin)
            ->test(SubcontractorIndex::class)
            ->assertSee('Steel Erectors')->assertSee('Gone Framing')
            ->set('status', 'inactive')
            ->assertDontSee('Steel Erectors')->assertSee('Gone Framing')
            ->set('status', 'active')
            ->assertSee('Steel Erectors')->assertDontSee('Gone Framing')
            ->call('clearFilters')
            ->assertSet('status', '');
    }

    public function test_an_inactive_vendor_is_not_offered_to_a_new_record_but_an_edit_keeps_its_own(): void
    {
        $this->seed(CollaborationResponseCodeSeeder::class);

        $client = Client::create([
            'company_name' => 'Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $this->admin->id,
        ]);
        $project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $this->admin->id,
        ]);

        $live = $this->makeVendor('Cerâmica Viva', true, false);
        $gone = $this->makeVendor('Cerâmica Fechada', true, false, active: false);

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $project])
            ->set('supplierSearch', 'cerâmica')
            ->assertViewHas('supplierResults', fn (array $rows) => collect($rows)->contains('id', $live->id)
                && ! collect($rows)->contains('id', $gone->id));

        // The scopes behind every picker: `active()` for a new record,
        // `activeOrCurrent()` for a form that already names a vendor.
        $this->assertEqualsCanonicalizing([$live->id], Supplier::active()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$live->id, $gone->id], Supplier::activeOrCurrent($gone->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$live->id], Supplier::activeOrCurrent(null)->pluck('id')->all());
    }
}
