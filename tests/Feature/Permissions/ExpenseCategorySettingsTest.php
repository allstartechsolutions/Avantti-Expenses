<?php

namespace Tests\Feature\Permissions;

use App\Livewire\SystemSettings\ExpenseCategorySettings;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 of the company-expenses module (docs/company-expenses.md): the
 * Expense Categories screen, the 4-digit account code, and the add-only,
 * country-aware seeder.
 */
class ExpenseCategorySettingsTest extends TestCase
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

    /** A company (general) expense filed under a category. */
    protected function fileUnder(ExpenseCategory $category): Expense
    {
        return Expense::create([
            'project_id' => null,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'total_amount' => 10,
            'status' => 'unpaid',
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | The screen
    |---------------------------------------------------------------------------
    */

    public function test_a_settings_reader_can_look_but_not_change_anything(): void
    {
        $category = ExpenseCategory::create(['name' => 'Seguro predial', 'account_code' => '6201', 'sort_order' => 1]);
        $reader = $this->roleWith(['settings.view']);

        Livewire::actingAs($reader)
            ->test(ExpenseCategorySettings::class)
            ->assertOk()
            ->assertSee('Seguro predial')
            ->assertSee('6201')
            ->assertDontSee('wire:click="create"', false);

        $fresh = fn () => Livewire::actingAs($reader)->test(ExpenseCategorySettings::class);

        $fresh()->call('create')->assertForbidden();
        $fresh()->call('edit', $category->id)->assertForbidden();
        $fresh()->call('toggleActive', $category->id)->assertForbidden();
        $fresh()->call('delete', $category->id)->assertForbidden();
        $fresh()->set('name', 'Sneaky')->call('save')->assertForbidden();

        $this->assertNull(ExpenseCategory::where('name', 'Sneaky')->first());
        $this->assertTrue($category->fresh()->is_active);

        Livewire::actingAs($this->roleWith(['projects.view']))
            ->test(ExpenseCategorySettings::class)
            ->assertForbidden();
    }

    public function test_an_editor_can_create_edit_retire_and_reactivate(): void
    {
        $editor = $this->roleWith(['settings.view', 'settings.edit']);

        $component = Livewire::actingAs($editor)->test(ExpenseCategorySettings::class);

        $component->call('create')
            ->assertSet('showFormModal', true)
            ->set('name', '  Seguro predial  ')
            ->set('account_code', ' 6201 ')
            ->set('description', 'Apólice do escritório')
            ->set('sort_order', 3)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $category = ExpenseCategory::where('name', 'Seguro predial')->sole();
        $this->assertSame('6201', $category->account_code);
        $this->assertTrue($category->is_active);
        $this->assertSame(3, $category->sort_order);
        $this->assertNull($category->key, 'A category made on the screen carries no seed key.');

        // A second category with the same name or the same code is refused.
        $component->call('create')->set('name', 'Seguro predial')->set('account_code', '6202')->call('save')->assertHasErrors(['name']);
        $component->call('create')->set('name', 'Outro seguro')->set('account_code', '6201')->call('save')->assertHasErrors(['account_code']);
        $this->assertSame(1, ExpenseCategory::where('name', 'like', '%seguro%')->count());

        // A code has to be four digits from 1000 up.
        $component->call('create')->set('name', 'Curto')->set('account_code', '620')->call('save')->assertHasErrors(['account_code']);
        $component->call('create')->set('name', 'Zero')->set('account_code', '0620')->call('save')->assertHasErrors(['account_code']);

        $component->call('edit', $category->id)
            ->assertSet('name', 'Seguro predial')
            ->assertSet('account_code', '6201')
            ->set('name', 'Seguro predial (matriz)')
            ->set('account_code', '6205')
            ->call('save')
            ->assertHasNoErrors();

        $category->refresh();
        $this->assertSame('Seguro predial (matriz)', $category->name);
        $this->assertSame('6205', $category->account_code);

        $component->call('toggleActive', $category->id);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertFalse(ExpenseCategory::active()->whereKey($category->id)->exists(), 'A retired category leaves the picker.');

        $component->call('toggleActive', $category->id);
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_a_blank_account_code_is_generated_unique_and_four_digits(): void
    {
        $editor = $this->roleWith(['settings.view', 'settings.edit']);

        Livewire::actingAs($editor)
            ->test(ExpenseCategorySettings::class)
            ->call('create')
            ->set('name', 'Gerado')
            ->set('account_code', '')
            ->call('save')
            ->assertHasNoErrors();

        $code = ExpenseCategory::where('name', 'Gerado')->sole()->account_code;
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{3}$/', $code);

        // Clearing the code on edit generates a fresh one rather than failing.
        $category = ExpenseCategory::where('name', 'Gerado')->sole();
        Livewire::actingAs($editor)
            ->test(ExpenseCategorySettings::class)
            ->call('edit', $category->id)
            ->set('account_code', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertMatchesRegularExpression('/^[1-9][0-9]{3}$/', $category->fresh()->account_code);

        // The generator never hands out a code already in use.
        ExpenseCategory::create(['name' => 'Taken', 'account_code' => '4242', 'sort_order' => 1]);
        for ($i = 0; $i < 50; $i++) {
            $this->assertNotSame('4242', ExpenseCategory::generateAccountCode());
        }
    }

    public function test_a_category_with_expenses_can_be_retired_but_never_deleted(): void
    {
        $editor = $this->roleWith(['settings.view', 'settings.edit']);
        // Codes outside the seeded 6xxx range: the migration seeds the table.
        $used = ExpenseCategory::create(['name' => 'Aluguel do pátio', 'account_code' => '7100', 'sort_order' => 1]);
        $unused = ExpenseCategory::create(['name' => 'Typo', 'account_code' => '7101', 'sort_order' => 2]);
        $expense = $this->fileUnder($used);

        $component = Livewire::actingAs($editor)->test(ExpenseCategorySettings::class);

        $component->call('delete', $used->id);
        $this->assertNotNull($used->fresh(), 'A category with expenses survives a delete call.');
        $this->assertNotNull($expense->fresh());

        $component->call('toggleActive', $used->id);
        $this->assertFalse($used->fresh()->is_active);
        $this->assertSame($used->id, $expense->fresh()->expense_category_id, 'The expense keeps its retired category.');

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
        ExpenseCategory::query()->delete();

        (new ExpenseCategorySeeder)->run('US');
        $this->assertSame(18, ExpenseCategory::count());

        ExpenseCategory::where('key', 'overhead.marketing')->update(['is_active' => false, 'sort_order' => 50]);
        ExpenseCategory::where('key', 'overhead.rent')->update(['name' => 'Office rent', 'account_code' => '6105']);

        (new ExpenseCategorySeeder)->run('BR');

        $this->assertSame(19, ExpenseCategory::count(), 'The Brazilian list adds only pró-labore beside the shared rows.');

        $marketing = ExpenseCategory::where('key', 'overhead.marketing')->sole();
        $this->assertFalse($marketing->is_active, 'A retired row stays retired.');
        $this->assertSame(50, $marketing->sort_order);

        $rent = ExpenseCategory::where('key', 'overhead.rent')->sole();
        $this->assertSame('Office rent', $rent->name, 'A renamed row keeps its name.');
        $this->assertSame('6105', $rent->account_code, 'A recoded row keeps its code.');
        $this->assertSame(0, ExpenseCategory::where('name', 'Rent')->count(), 'The renamed row is not re-created under its old name.');

        // Running either list again changes nothing.
        (new ExpenseCategorySeeder)->run('BR');
        (new ExpenseCategorySeeder)->run('US');
        $this->assertSame(19, ExpenseCategory::count());
    }

    public function test_a_seeded_code_already_taken_by_the_owner_is_not_stolen(): void
    {
        ExpenseCategory::query()->delete();

        // The owner made their own 6100 before the seed ran.
        ExpenseCategory::create(['name' => 'Escritório', 'account_code' => '6100', 'sort_order' => 1]);

        (new ExpenseCategorySeeder)->run('US');

        $this->assertSame('6100', ExpenseCategory::where('name', 'Escritório')->sole()->account_code);
        $rent = ExpenseCategory::where('key', 'overhead.rent')->sole();
        $this->assertNotSame('6100', $rent->account_code);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{3}$/', $rent->account_code);
    }

    public function test_every_seeded_name_and_description_is_translated(): void
    {
        $pt = json_decode(file_get_contents(lang_path('pt_BR.json')), true);
        $en = json_decode(file_get_contents(lang_path('en.json')), true);

        foreach (['US', 'BR'] as $country) {
            foreach (ExpenseCategorySeeder::categoriesFor($country) as $category) {
                $this->assertArrayHasKey($category['name'], $pt, "pt_BR is missing the category name '{$category['name']}'.");
                $this->assertArrayHasKey($category['name'], $en);
                $this->assertArrayHasKey($category['description'], $pt, "pt_BR is missing the description of '{$category['name']}'.");
            }
        }
    }
}
