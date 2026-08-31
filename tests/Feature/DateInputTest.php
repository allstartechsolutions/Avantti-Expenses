<?php

namespace Tests\Feature;

use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\Report\AccountsPayableReport;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The date field, and why it is not `<input type="date">`.
 *
 * A native date input renders in the *browser's* locale, which has nothing to
 * do with `config('app.country')`: a Brazilian install seen through an en-US
 * browser asked for mm/dd/yyyy, which is what the owner reported. The box is a
 * text field in this install's order now, and the native control is kept
 * beside it for its picker alone. What crosses to Livewire is still `Y-m-d`.
 */
class DateInputTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'C',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'p@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function expenseForm(): string
    {
        return Livewire::actingAs($this->admin)
            ->test(ExpenseCreate::class, ['project' => $this->project])
            ->html();
    }

    public function test_it_asks_for_the_date_the_way_this_country_writes_it(): void
    {
        config(['app.country' => 'BR']);
        $this->assertStringContainsString('dd/mm/aaaa', $this->expenseForm());

        config(['app.country' => 'US']);
        $this->assertStringContainsString('mm/dd/yyyy', $this->expenseForm());
    }

    public function test_the_box_people_type_into_is_never_a_native_date_input(): void
    {
        $html = $this->expenseForm();

        // The field on screen is text...
        $this->assertStringContainsString('x-model="display"', $html);

        // ...and the native control is there only to open a picker: hidden,
        // out of the tab order, and never read.
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('openPicker()', $html);
        $this->assertStringContainsString('takeFromPicker($event)', $html);
    }

    public function test_the_country_reaches_the_javascript_that_formats_the_value(): void
    {
        config(['app.country' => 'BR']);
        $this->assertStringContainsString(', true)"', $this->expenseForm());

        config(['app.country' => 'US']);
        $this->assertStringContainsString(', false)"', $this->expenseForm());
    }

    /** The value that crosses to Livewire is the stored one, not the shown one. */
    public function test_the_property_is_entangled_not_replaced(): void
    {
        $html = $this->expenseForm();

        $this->assertStringContainsString(".entangle('expense_date')", $html);
    }

    /** A filter that re-queries as you type has to keep its `.live`. */
    public function test_a_live_binding_is_carried_through(): void
    {
        $html = Livewire::actingAs($this->admin)
            ->test(AccountsPayableReport::class)
            ->html();

        $this->assertMatchesRegularExpression("/entangle\('fromDate'\)\.live/", $html);
    }

    /**
     * The disabled fields are still fields.
     *
     * The expense form's payment section locks its dates on a paid expense.
     * Written as `@disabled($flag)` the component tag did not compile at all,
     * and the field vanished from the page whether locked or not.
     */
    public function test_a_conditionally_disabled_field_still_renders(): void
    {
        $html = $this->expenseForm();

        $this->assertStringNotContainsString('<x-ui.', $html, 'A component tag was left uncompiled in the output.');
        $this->assertStringContainsString(".entangle('expense_paid_date')", $html);
    }

    /** The id is stable, or Livewire's morph chases a new one on every keystroke. */
    public function test_the_id_does_not_change_between_renders(): void
    {
        $first = $this->expenseForm();
        $second = $this->expenseForm();

        preg_match('/id="(date-input-[^"]+)"/', $first, $a);
        preg_match('/id="(date-input-[^"]+)"/', $second, $b);

        $this->assertNotEmpty($a[1] ?? null);
        $this->assertSame($a[1], $b[1]);
    }
}
