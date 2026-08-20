<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Company\CompanyInfo;
use App\Livewire\SystemSettings\ModuleAccessSettings;
use App\Livewire\SystemSettings\SettingsIndex;
use App\Models\ModuleAccess;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M3 — Company & Settings.
 *
 * The company record was open to everybody, signed in being the only
 * requirement; System Settings was behind the `admin` middleware. Both now run
 * on abilities, and the answers are deliberately the same as before.
 */
class CompanyAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
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

    /*
    |---------------------------------------------------------------------------
    | Company
    |---------------------------------------------------------------------------
    */

    public function test_the_company_screen_answers_as_it_did_before(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->user($role))->get(route('company.info'))->assertOk();
        }
    }

    public function test_seeing_the_company_and_changing_it_are_separate_grants(): void
    {
        $reader = $this->roleWith(['company.view']);

        $this->actingAs($reader)->get(route('company.info'))
            ->assertOk()
            ->assertSee(__('You can see the company details but not change them.'));

        // The save button is not rendered…
        $this->actingAs($reader)->get(route('company.info'))->assertDontSee(__('Update Company'));

        // …and the action behind it is refused, which is the part that matters.
        Livewire::actingAs($reader)
            ->test(CompanyInfo::class)
            ->call('saveCompany')
            ->assertForbidden();
    }

    public function test_somebody_with_no_company_ability_cannot_open_it_at_all(): void
    {
        $nobody = $this->roleWith(['dashboard.view']);

        $this->actingAs($nobody)->get(route('company.info'))->assertForbidden();

        Livewire::actingAs($nobody)
            ->test(CompanyInfo::class)
            ->assertForbidden();
    }

    public function test_removing_the_logo_needs_the_edit_ability(): void
    {
        $reader = $this->roleWith(['company.view']);

        Livewire::actingAs($reader)
            ->test(CompanyInfo::class)
            ->call('removeExistingLogo')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Settings
    |---------------------------------------------------------------------------
    */

    public function test_settings_answers_as_the_admin_middleware_did(): void
    {
        $this->actingAs($this->user('admin'))->get(route('system-settings.index'))->assertOk();
        $this->actingAs($this->user('manager'))->get(route('system-settings.index'))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('system-settings.index'))->assertForbidden();
    }

    public function test_settings_can_now_be_granted_to_somebody_who_is_not_an_administrator(): void
    {
        // The thing the middleware could never do.
        $bookkeeper = $this->roleWith(['settings.view', 'settings.edit']);

        $this->actingAs($bookkeeper)->get(route('system-settings.index'))->assertOk();

        Livewire::actingAs($bookkeeper)->test(SettingsIndex::class)->assertOk();
    }

    public function test_switching_a_module_off_is_held_apart_from_editing_settings(): void
    {
        // Turning a module off takes it away from everybody in the company, so
        // it is its own grant rather than part of "may edit settings".
        $bookkeeper = $this->roleWith(['settings.view', 'settings.edit']);

        $module = ModuleAccess::create([
            'module_key' => 'estimates',
            'module_name' => 'Estimates',
            'is_enabled' => true,
            'is_core' => false,
            'created_by' => $this->user('admin')->id,
        ]);

        Livewire::actingAs($bookkeeper)
            ->test(ModuleAccessSettings::class)
            ->call('toggle', $module->id)
            ->assertForbidden();

        $this->assertTrue($module->fresh()->is_enabled);

        // With the ability, it works.
        $switcher = $this->roleWith(['settings.view', 'settings.manage_modules']);

        Livewire::actingAs($switcher)
            ->test(ModuleAccessSettings::class)
            ->call('toggle', $module->id);

        $this->assertFalse($module->fresh()->is_enabled);
    }

    public function test_a_settings_reader_cannot_change_anything(): void
    {
        $reader = $this->roleWith(['settings.view']);

        $this->actingAs($reader)->get(route('system-settings.index'))->assertOk();

        Livewire::actingAs($reader)
            ->test(\App\Livewire\SystemSettings\TaxRateSettings::class)
            ->call('save')
            ->assertForbidden();
    }

    public function test_a_settings_reader_cannot_toggle_a_notification(): void
    {
        $reader = $this->roleWith(['settings.view']);

        Livewire::actingAs($reader)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->call('toggle', 'task_assigned')
            ->assertForbidden();
    }

    public function test_the_menu_follows_the_grants(): void
    {
        $navigation = app(\App\Services\Navigation::class);

        $reader = $this->roleWith(['company.view']);
        $names = array_map(
            fn ($entry) => $entry['name'],
            collect($navigation->sidebar($reader))->firstWhere('key', 'company')['items'] ?? [],
        );

        $this->assertSame(['Company Info'], $names);
        $this->assertSame([], $navigation->header($reader), 'No settings ability, no gear.');

        $admin = $this->user('admin');
        $this->assertSame(['Settings'], array_column($navigation->header($admin), 'name'));
    }
}
