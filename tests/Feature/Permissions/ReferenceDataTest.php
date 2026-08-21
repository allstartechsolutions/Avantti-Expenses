<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Client\ClientIndex;
use App\Livewire\Subcontractor\SubcontractorIndex;
use App\Livewire\Supplier\SupplierIndex;
use App\Livewire\Vendor\VendorDuplicates;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M16 — Reference data.
 *
 * Clients, vendors and the catalog: the lists the rest of the application
 * points at. All three are **company-wide** — they belong to no project — so
 * they are held by role and appear in neither project editor.
 *
 * The vendor unification means one area covers three screens: suppliers,
 * subcontractors and the merge tool all read and write the same `vendors`
 * table, so all three answer to `vendors.*`.
 */
class ReferenceDataTest extends TestCase
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

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

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

    protected function makeClient(): Client
    {
        return Client::create([
            'company_name' => 'Acme '.str()->random(4),
            'contact_name' => 'A',
            'email' => str()->random(6).'@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeVendor(array $attributes = []): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(array_merge([
            'name' => 'Vendor '.str()->random(5),
            'is_supplier' => true,
            'created_by' => $this->admin->id,
        ], $attributes))->save();

        return $vendor;
    }

    protected function makeCatalogItem(): CatalogItem
    {
        return CatalogItem::create([
            'type' => 'product',
            'name' => 'Cement '.str()->random(4),
            'is_active' => true,
            'purchase_unit' => 'bag',
            'usage_unit' => 'bag',
            'units_per_purchase' => 1,
            'current_cost' => 30,
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Reproduced, then revocable
    |---------------------------------------------------------------------------
    */

    public function test_the_reference_screens_answer_as_they_did_for_every_role(): void
    {
        $client = $this->makeClient();
        $vendor = $this->makeVendor();
        $item = $this->makeCatalogItem();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('clients.index'))->assertOk();
            $this->actingAs($user)->get(route('clients.show', $client))->assertOk();
            $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
            $this->actingAs($user)->get(route('subcontractors.index'))->assertOk();
            $this->actingAs($user)->get(route('catalog.index'))->assertOk();
            $this->actingAs($user)->get(route('catalog.edit', $item))->assertOk();
        }
    }

    public function test_the_merge_tool_stays_administrator_only(): void
    {
        $this->actingAs($this->user('admin'))->get(route('vendors.duplicates'))->assertOk();
        $this->actingAs($this->user('manager'))->get(route('vendors.duplicates'))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('vendors.duplicates'))->assertForbidden();
    }

    public function test_each_of_the_three_lists_can_be_taken_away_on_its_own(): void
    {
        $client = $this->makeClient();

        // Holds clients only.
        $clientKeeper = $this->roleWith(['projects.view', 'project.view', 'clients.view']);

        $this->actingAs($clientKeeper)->get(route('clients.index'))->assertOk();
        $this->actingAs($clientKeeper)->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($clientKeeper)->get(route('catalog.index'))->assertForbidden();

        // Holds the catalog only.
        $buyer = $this->roleWith(['projects.view', 'project.view', 'catalog.view']);

        $this->actingAs($buyer)->get(route('catalog.index'))->assertOk();
        $this->actingAs($buyer)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('clients.show', $client))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | One area, three screens — the vendor unification
    |---------------------------------------------------------------------------
    */

    public function test_suppliers_and_subcontractors_answer_to_the_same_grant(): void
    {
        // They are one table since the unification, so one grant covers both.
        $keeper = $this->roleWith(['projects.view', 'project.view', 'vendors.view']);

        $this->actingAs($keeper)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($keeper)->get(route('subcontractors.index'))->assertOk();

        // …and neither is reachable without it.
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($blind)->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($blind)->get(route('subcontractors.index'))->assertForbidden();
    }

    public function test_creating_and_editing_a_vendor_are_separate_from_reading(): void
    {
        $vendor = $this->makeVendor();
        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view']);

        $this->actingAs($reader)->get(route('suppliers.create'))->assertForbidden();
        $this->actingAs($reader)->get(route('suppliers.edit', $vendor))->assertForbidden();
        $this->actingAs($reader)->get(route('subcontractors.create'))->assertForbidden();

        $keeper = $this->roleWith([
            'projects.view', 'project.view', 'vendors.view', 'vendors.create', 'vendors.edit',
        ]);

        $this->actingAs($keeper)->get(route('suppliers.create'))->assertOk();
        $this->actingAs($keeper)->get(route('suppliers.edit', $vendor))->assertOk();
    }

    public function test_deleting_a_vendor_needs_its_own_grant(): void
    {
        $vendor = $this->makeVendor();

        $editor = $this->roleWith([
            'projects.view', 'project.view', 'vendors.view', 'vendors.edit',
        ]);

        Livewire::actingAs($editor)
            ->test(SupplierIndex::class)
            ->call('deleteSupplier', $vendor->id)
            ->assertForbidden();

        Livewire::actingAs($editor)
            ->test(SubcontractorIndex::class)
            ->call('confirmDeleteSubcontractor', $vendor->id)
            ->assertForbidden();

        $this->assertNotNull($vendor->fresh());
    }

    public function test_merging_is_held_apart_from_every_other_vendor_grant(): void
    {
        // Somebody who may do everything else to a vendor still may not merge.
        $keeper = $this->roleWith([
            'projects.view', 'project.view',
            'vendors.view', 'vendors.create', 'vendors.edit', 'vendors.delete',
        ]);

        $this->actingAs($keeper)->get(route('vendors.duplicates'))->assertForbidden();

        Livewire::actingAs($keeper)
            ->test(VendorDuplicates::class)
            ->assertForbidden();

        $merger = $this->roleWith(['projects.view', 'project.view', 'vendors.merge']);

        $this->actingAs($merger)->get(route('vendors.duplicates'))->assertOk();
    }

    /*
    |---------------------------------------------------------------------------
    | Clients and the catalog
    |---------------------------------------------------------------------------
    */

    public function test_deleting_a_client_needs_its_own_grant(): void
    {
        $client = $this->makeClient();

        $editor = $this->roleWith([
            'projects.view', 'project.view', 'clients.view', 'clients.edit',
        ]);

        Livewire::actingAs($editor)
            ->test(ClientIndex::class)
            ->call('confirmDeleteClient', $client->id)
            ->assertForbidden();

        $this->assertNotNull($client->fresh());
    }

    public function test_the_catalog_grants_are_separable(): void
    {
        $item = $this->makeCatalogItem();
        $reader = $this->roleWith(['projects.view', 'project.view', 'catalog.view']);

        $this->actingAs($reader)->get(route('catalog.index'))->assertOk();
        $this->actingAs($reader)->get(route('catalog.create'))->assertForbidden();
        $this->actingAs($reader)->get(route('catalog.edit', $item))->assertForbidden();
        $this->actingAs($reader)->get(route('catalog.categories.create'))->assertForbidden();
    }

    public function test_the_quick_create_on_a_form_still_needs_the_client_grant(): void
    {
        // The estimate and invoice forms embed a "new client" panel. It is a
        // create either way.
        $reader = $this->roleWith(['projects.view', 'project.view', 'clients.view']);

        Livewire::actingAs($reader)
            ->test(\App\Livewire\Client\ClientQuickCreate::class)
            ->call('openModal')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue's own claims
    |---------------------------------------------------------------------------
    */

    public function test_all_three_areas_are_company_wide(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;

        foreach (['clients', 'vendors', 'catalog'] as $area) {
            $this->assertSame(['global'], $catalog::area($area)['levels'], $area);
        }

        // The catalog carries prices, so it is a money area; the other two
        // hold names and addresses.
        $this->assertTrue($catalog::area('catalog')['money']);
        $this->assertFalse($catalog::area('clients')['money']);

        $this->assertTrue($catalog::action('vendors.merge')['sensitive']);
    }

    public function test_the_seeds_reproduce_what_each_role_could_do_before(): void
    {
        $manager = Role::where('name', 'manager')->firstOrFail()->abilityRows()->pluck('ability')->all();
        $employee = Role::where('name', 'employee')->firstOrFail()->abilityRows()->pluck('ability')->all();

        // Reading and writing reference data was open to everybody signed in.
        foreach (['clients.view', 'clients.create', 'vendors.view', 'catalog.view'] as $ability) {
            $this->assertContains($ability, $employee, $ability);
        }

        // Deleting a vendor, and merging, were administrator-only.
        foreach (['vendors.delete', 'vendors.merge'] as $ability) {
            $this->assertNotContains($ability, $manager, $ability);
            $this->assertNotContains($ability, $employee, $ability);
        }
    }
}
