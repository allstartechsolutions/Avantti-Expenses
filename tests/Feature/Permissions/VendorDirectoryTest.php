<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Vendor\VendorIndex;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Directory's Vendors list: suppliers, subcontractors and the
 * companies that are both, in one place. It answers to the same
 * `vendors.*` grants as the two lists it fronts.
 */
class VendorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeVendor(string $name, bool $supplier, bool $subcontractor): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => $name,
            'is_supplier' => $supplier,
            'is_subcontractor' => $subcontractor,
            'created_by' => $this->admin->id,
        ])->save();

        return $vendor;
    }

    public function test_the_vendors_list_answers_for_every_seeded_role_and_can_be_taken_away(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->user($role))->get(route('vendors.index'))->assertOk();
        }

        $blind = $this->roleWith(['projects.view', 'project.view']);
        $this->actingAs($blind)->get(route('vendors.index'))->assertForbidden();

        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view']);
        $this->actingAs($reader)->get(route('vendors.index'))->assertOk();
    }

    public function test_the_list_shows_both_kinds_and_filters_by_type(): void
    {
        $supplier = $this->makeVendor('Cement Depot', true, false);
        $sub = $this->makeVendor('Steel Erectors', false, true);
        $both = $this->makeVendor('Do It All', true, true);

        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->assertSee('Cement Depot')->assertSee('Steel Erectors')->assertSee('Do It All')
            ->set('type', 'suppliers')
            ->assertSee('Cement Depot')->assertDontSee('Steel Erectors')->assertSee('Do It All')
            ->set('type', 'subcontractors')
            ->assertDontSee('Cement Depot')->assertSee('Steel Erectors')->assertSee('Do It All')
            ->set('type', 'both')
            ->assertDontSee('Cement Depot')->assertDontSee('Steel Erectors')->assertSee('Do It All')
            ->call('clearFilters')
            ->set('search', 'steel')
            ->assertSee('Steel Erectors')->assertDontSee('Cement Depot');

        // A supplier opens its own page; a subcontractor the richer one.
        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->assertSee(route('suppliers.show', $supplier->id))
            ->assertSee(route('subcontractors.show', $sub->id))
            ->assertSee(route('subcontractors.show', $both->id));
    }

    public function test_deleting_from_the_list_needs_the_grant_and_refuses_what_the_old_lists_refused(): void
    {
        $lone = $this->makeVendor('Lone Supplier', true, false);
        $both = $this->makeVendor('Do It All', true, true);
        $busy = $this->makeVendor('Busy Sub', false, true);
        Subcontractor::findOrFail($busy->id)->employees()->create(['name' => 'Somebody']);

        $editor = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.edit']);

        Livewire::actingAs($editor)
            ->test(VendorIndex::class)
            ->call('deleteVendor', $lone->id)
            ->assertForbidden();

        $this->assertNotNull(Vendor::find($lone->id));

        // Both classifications: sent to the record, nothing deleted.
        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->call('deleteVendor', $both->id);

        $this->assertNotNull(Vendor::find($both->id));

        // Employees alone do not block, as on the old list; contracts would.
        Livewire::actingAs($this->admin)
            ->test(VendorIndex::class)
            ->call('deleteVendor', $busy->id)
            ->call('deleteVendor', $lone->id);

        $this->assertNull(Vendor::find($busy->id));
        $this->assertNull(Vendor::find($lone->id));
    }
}
