<?php

namespace Tests\Feature\Supplier;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client request 23 (Nov 2026): the supplier forms look the address up as
 * the client form does — the street input drives Google Places and the pick
 * fills street, city, state, ZIP and (in Brazil) the neighbourhood.
 */
class SupplierAddressAutocompleteTest extends TestCase
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

    private function assertAutocomplete(string $html): void
    {
        $this->assertStringContainsString("Alpine.data('placesAddress'", $html);
        $this->assertStringContainsString('x-data="placesAddress(', $html);
        $this->assertStringContainsString("postal_code: 'postal_code'", $html);
        $this->assertStringContainsString('x-ref="streetInput"', $html);
        $this->assertSame(1, substr_count($html, "Alpine.data('placesAddress'"));
    }

    public function test_the_create_form_looks_the_address_up(): void
    {
        $this->assertAutocomplete($this->actingAs($this->admin)->get(route('suppliers.create'))->assertOk()->getContent());
    }

    public function test_the_edit_form_looks_the_address_up(): void
    {
        $supplier = new Vendor;
        $supplier->forceFill(['name' => 'Shore Fuel', 'is_supplier' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        $this->assertAutocomplete($this->actingAs($this->admin)->get(route('suppliers.edit', $supplier))->assertOk()->getContent());
    }
}
