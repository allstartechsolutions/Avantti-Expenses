<?php

namespace Tests\Feature\Subcontractor;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractCreate;
use App\Livewire\Contract\ContractEdit;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A subcontractor's employee can be switched off on the vendor page. An
 * inactive employee stays listed and stays on the contracts that name them,
 * but is not offered — nor accepted — as the contact of a new contract.
 */
class EmployeeActiveStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Subcontractor $wolf;

    protected SubcontractorEmployee $alfredo;

    protected SubcontractorEmployee $vinicius;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Wolf Construction Services LLC', 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();
        $this->wolf = Subcontractor::findOrFail($vendor->id);

        $this->alfredo = SubcontractorEmployee::create(['subcontractor_id' => $this->wolf->id, 'name' => 'Alfredo Active', 'title' => 'Framer']);
        $this->vinicius = SubcontractorEmployee::create(['subcontractor_id' => $this->wolf->id, 'name' => 'Vinicius Gone', 'title' => 'Framer']);
    }

    protected function makeProject(): Project
    {
        return Project::create([
            'project_name' => 'Liberty Run',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'liberty@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_an_editor_switches_an_employee_off_and_on_and_the_tab_filters_and_counts(): void
    {
        $page = Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $this->wolf])
            ->set('activeTab', 'employees')
            ->call('toggleEmployeeActive', $this->vinicius->id);

        $this->vinicius->refresh();
        $this->assertFalse($this->vinicius->is_active);
        $this->assertSame($this->admin->id, $this->vinicius->deactivated_by);
        $this->assertNotNull($this->vinicius->deactivated_at);

        $page->assertViewHas('employeeCounts', ['all' => 2, 'active' => 1, 'inactive' => 1])
            ->set('employeeStatusFilter', 'inactive')
            ->assertSee('Vinicius Gone')
            ->assertDontSee('Alfredo Active')
            ->set('employeeStatusFilter', 'active')
            ->assertSee('Alfredo Active')
            ->assertDontSee('Vinicius Gone');

        $page->call('toggleEmployeeActive', $this->vinicius->id);
        $this->vinicius->refresh();
        $this->assertTrue($this->vinicius->is_active);
        $this->assertNull($this->vinicius->deactivated_by);
    }

    public function test_the_switch_needs_vendors_edit_and_only_reaches_this_vendors_employees(): void
    {
        $role = Role::create(['name' => 'vendor-reader']);
        $role->syncAbilities(['vendors.view']);
        $reader = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($reader)
            ->test(SubcontractorShow::class, ['subcontractor' => $this->wolf])
            ->call('toggleEmployeeActive', $this->vinicius->id)
            ->assertForbidden();
        $this->assertTrue($this->vinicius->fresh()->is_active);

        $other = new Vendor;
        $other->forceFill(['name' => 'Other Sub', 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();
        $stranger = SubcontractorEmployee::create(['subcontractor_id' => $other->id, 'name' => 'Not Theirs']);

        $this->assertThrows(fn () => Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $this->wolf])
            ->call('toggleEmployeeActive', $stranger->id), ModelNotFoundException::class);
        $this->assertTrue($stranger->fresh()->is_active);
    }

    public function test_a_new_contract_offers_and_accepts_active_employees_only(): void
    {
        $this->vinicius->deactivate($this->admin->id);
        $project = $this->makeProject();

        $form = Livewire::actingAs($this->admin)
            ->test(ContractCreate::class, ['project' => $project])
            ->set('subcontractor_id', $this->wolf->id)
            ->assertViewHas('employees', fn ($employees) => $employees->pluck('name')->all() === ['Alfredo Active']);

        $form->set('start_date', now()->toDateString())
            ->set('amount', 1000)
            ->set('subcontractor_employee_id', $this->vinicius->id)
            ->call('save')
            ->assertHasErrors(['subcontractor_employee_id']);
        $this->assertSame(0, Contract::count());

        $form->set('subcontractor_employee_id', $this->alfredo->id)->call('save')->assertHasNoErrors();
        $this->assertSame($this->alfredo->id, Contract::sole()->subcontractor_employee_id);
    }

    public function test_a_contract_that_names_an_employee_since_switched_off_keeps_them(): void
    {
        $contract = Contract::create([
            'project_id' => $this->makeProject()->id,
            'subcontractor_id' => $this->wolf->id,
            'subcontractor_employee_id' => $this->vinicius->id,
            'contract_number' => 'CTR-0001',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 1000,
            'created_by' => $this->admin->id,
        ]);
        $this->vinicius->deactivate($this->admin->id);

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->assertViewHas('employees', fn ($employees) => $employees->pluck('name')->all() === ['Alfredo Active', 'Vinicius Gone'])
            ->assertSee(['Vinicius Gone', '(Inactive)'])
            ->set('amount', '1500')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->vinicius->id, $contract->fresh()->subcontractor_employee_id);
    }
}
