<?php

namespace Tests\Feature\Equipment;

use App\Livewire\Equipment\EquipmentShow;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client request 14 (Nov 2026): from a machine's Costs tab, open an expense —
 * items, payments, history and its files — without leaving the page.
 */
class EquipmentCostExpenseViewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $this->equipment = Equipment::create([
            'name' => 'Bobcat S650',
            'equipment_type' => 'vehicle',
            'ownership' => 'owned',
            'meter_type' => 'km',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    private function companyExpense(?int $equipmentId, string $note): Expense
    {
        return Expense::create([
            'expense_category_id' => ExpenseCategory::where('key', 'overhead.rent')->value('id'),
            'equipment_id' => $equipmentId,
            'expense_date' => now()->toDateString(),
            'total_amount' => 350,
            'status' => 'unpaid',
            'notes' => $note,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_a_tagged_expense_opens_with_its_attachments(): void
    {
        $expense = $this->companyExpense($this->equipment->id, 'Hydraulic hose');
        $expense->attachments()->create([
            'file_path' => 'attachments/invoice-881.pdf',
            'original_name' => 'invoice-881.pdf',
            'uploaded_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EquipmentShow::class, ['equipment' => $this->equipment])
            ->call('setActiveTab', 'costs')
            ->assertSee("openExpenseViewModal({$expense->id})", false)
            ->assertSee("openExpenseFiles({$expense->id})", false)
            ->assertSee(trans_choice(':count file|:count files', 1, ['count' => 1]))
            ->call('openExpenseFiles', $expense->id)
            ->assertDispatched('expense-show-files')
            ->assertSet('viewingExpenseId', $expense->id)
            ->assertSet('expense_notes', 'Hydraulic hose')
            ->assertSee('expense-attachments-'.$expense->id, false);
    }

    /** Request 26: the overview shows the costs without opening the Costs tab. */
    public function test_the_overview_shows_the_costs_at_a_glance(): void
    {
        $this->equipment->update(['purchase_cost' => 10000]);
        $maintenance = $this->equipment->maintenances()->create([
            'title' => 'Hose',
            'maintenance_type' => 'corrective',
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);

        $repair = $this->companyExpense($this->equipment->id, 'Repair');
        $repair->update(['equipment_maintenance_id' => $maintenance->id]);
        $this->companyExpense($this->equipment->id, 'Fuel');
        $this->companyExpense(null, 'Office rent');

        Livewire::actingAs($this->admin)
            ->test(EquipmentShow::class, ['equipment' => $this->equipment])
            ->assertViewHas('overviewCosts', fn ($c) => $c['tco'] === 10700.0
                && $c['total'] === 700.0
                && $c['count'] === 2
                && $c['maintenance'] === 350.0
                && $c['other'] === 350.0
                && $c['unpaid'] === 700.0
                && $c['latest']->count() === 2)
            ->assertSee(__('Latest expenses'))
            ->assertSee("openExpenseViewModal({$repair->id})", false)
            ->call('setActiveTab', 'costs')
            ->assertSet('activeTab', 'costs');
    }

    public function test_the_overview_says_when_nothing_is_tagged(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EquipmentShow::class, ['equipment' => $this->equipment])
            ->assertSee(__('No expense tagged to this equipment yet'))
            ->assertSee(route('company-expenses.create', ['equipment' => $this->equipment->id]), false);
    }

    public function test_an_expense_not_tagged_to_this_machine_does_not_open(): void
    {
        $other = $this->companyExpense(null, 'Office rent');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(EquipmentShow::class, ['equipment' => $this->equipment])
            ->call('openExpenseViewModal', $other->id);
    }
}
