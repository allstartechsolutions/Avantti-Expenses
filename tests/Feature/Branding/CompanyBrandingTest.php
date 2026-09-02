<?php

namespace Tests\Feature\Branding;

use App\Livewire\Company\CompanyInfo;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Branding;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The customer's own marks, and the product's underneath them.
 *
 * What matters here is the fallback: an install that uploads nothing must look
 * exactly as it did before this existed, and an install that uploads something
 * must stop showing the product's icon everywhere at once.
 */
class CompanyBrandingTest extends TestCase
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

        Branding::forget();
    }

    public function test_an_install_with_no_company_falls_back_to_the_product_mark(): void
    {
        $this->assertSame(config('app.logo_url'), Branding::iconUrl());
        $this->assertSame(config('app.logo_url'), Branding::faviconUrl());
        $this->assertSame(config('app.name'), Branding::name());
        $this->assertNull(Branding::darkIconUrl());
        $this->assertFalse(Branding::hasCustomIcon());
    }

    public function test_a_company_with_no_icons_lends_only_its_name(): void
    {
        $this->makeCompany(['name' => 'Construtora Alfa']);

        $this->assertSame('Construtora Alfa', Branding::name());
        $this->assertSame(config('app.logo_url'), Branding::iconUrl());
    }

    public function test_the_display_name_wins_over_the_company_name(): void
    {
        $this->makeCompany(['name' => 'Construtora Alfa Ltda ME', 'brand_name' => 'Alfa']);

        $this->assertSame('Alfa', Branding::name());
    }

    public function test_uploaded_icons_replace_the_product_mark_everywhere(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany(['name' => 'Construtora Alfa']);

        Livewire::actingAs($this->admin)
            ->test(CompanyInfo::class)
            ->set('app_icon', UploadedFile::fake()->image('icon.png', 128, 128))
            ->set('app_icon_dark', UploadedFile::fake()->image('icon-dark.png', 128, 128))
            ->set('favicon', UploadedFile::fake()->create('favicon.ico', 4, 'image/x-icon'))
            ->set('brand_name', 'Alfa')
            ->call('saveCompany')
            ->assertHasNoErrors();

        $company->refresh();

        $this->assertNotNull($company->app_icon);
        $this->assertNotNull($company->app_icon_dark);
        $this->assertNotNull($company->favicon);

        Storage::disk('public')->assertExists($company->app_icon);
        Storage::disk('public')->assertExists($company->favicon);

        // The saved row is what every screen now reads, cache and all.
        $this->assertTrue(Branding::hasCustomIcon());
        $this->assertNotNull(Branding::darkIconUrl());
        $this->assertStringContainsString('image/x-icon', Branding::faviconType());
        $this->assertNotSame(config('app.logo_url'), Branding::iconUrl());
        $this->assertSame('Alfa', Branding::name());
    }

    public function test_removing_an_icon_returns_the_install_to_the_default(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany(['name' => 'Construtora Alfa']);

        Livewire::actingAs($this->admin)
            ->test(CompanyInfo::class)
            ->set('app_icon', UploadedFile::fake()->image('icon.png', 128, 128))
            ->call('saveCompany');

        $stored = $company->refresh()->app_icon;
        $this->assertNotNull($stored);

        Livewire::actingAs($this->admin)
            ->test(CompanyInfo::class)
            ->call('removeStoredFile', 'app_icon')
            ->assertSet('existingAppIcon', null);

        $this->assertNull($company->refresh()->app_icon);
        Storage::disk('public')->assertMissing($stored);
        $this->assertSame(config('app.logo_url'), Branding::iconUrl());
    }

    public function test_a_favicon_that_is_not_an_icon_or_a_png_is_refused(): void
    {
        Storage::fake('public');

        $this->makeCompany(['name' => 'Construtora Alfa']);

        Livewire::actingAs($this->admin)
            ->test(CompanyInfo::class)
            ->set('favicon', UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf'))
            ->assertHasErrors('favicon');
    }

    public function test_a_user_without_company_edit_cannot_remove_a_stored_icon(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany(['name' => 'Construtora Alfa']);
        $company->app_icon = 'company-branding/icon.png';
        $company->save();

        // Someone who may read the company screen but not change it.
        $role = Role::create(['name' => 'company-reader']);
        $role->syncAbilities(['company.view']);
        $viewer = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($viewer)
            ->test(CompanyInfo::class)
            ->call('removeStoredFile', 'app_icon')
            ->assertForbidden();

        $this->assertSame('company-branding/icon.png', $company->refresh()->app_icon);
    }

    public function test_an_unknown_field_name_from_the_browser_is_refused(): void
    {
        $this->makeCompany(['name' => 'Construtora Alfa']);

        Livewire::actingAs($this->admin)
            ->test(CompanyInfo::class)
            ->call('removeStoredFile', 'name')
            ->assertNotFound();
    }

    public function test_the_sign_in_page_wears_the_company_mark(): void
    {
        $company = $this->makeCompany(['name' => 'Construtora Alfa', 'brand_name' => 'Alfa']);
        $company->app_icon = 'company-branding/icon.png';
        $company->favicon = 'company-branding/favicon.ico';
        $company->save();

        // Nobody is signed in here — this is the whole point of the fallback
        // being cheap and safe: it is read before a session exists.
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('company-branding/icon.png', false);
        $response->assertSee('company-branding/favicon.ico', false);
        $response->assertSee('type="image/x-icon"', false);
        $response->assertSee('Alfa', false);
        $response->assertDontSee(config('app.logo_url'), false);
    }

    public function test_a_dark_icon_is_rendered_beside_the_light_one(): void
    {
        $company = $this->makeCompany();
        $company->app_icon = 'company-branding/icon.png';
        $company->app_icon_dark = 'company-branding/icon-dark.png';
        $company->save();

        $html = view()->make('components.app-logo-icon', ['attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();

        // Both marks are in the page; the theme decides which one is seen.
        $this->assertStringContainsString('company-branding/icon.png', $html);
        $this->assertStringContainsString('company-branding/icon-dark.png', $html);
        $this->assertStringContainsString('dark:hidden', $html);
        $this->assertStringContainsString('dark:block', $html);
    }

    public function test_only_one_mark_is_rendered_when_there_is_no_dark_icon(): void
    {
        $html = view()->make('components.app-logo-icon', ['attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();

        $this->assertSame(1, substr_count($html, '<img'));
        $this->assertStringNotContainsString('dark:hidden', $html);
    }

    public function test_the_company_screen_shows_the_branding_card(): void
    {
        $this->makeCompany();

        $this->actingAs($this->admin)
            ->get(route('company.info'))
            ->assertOk()
            ->assertSee(__('Branding'))
            ->assertSee(__('App Icon'))
            ->assertSee(__('Dark Mode Icon'))
            ->assertSee(__('Favicon'))
            ->assertSee(__('Using the default'));
    }

    private function makeCompany(array $attributes = []): Company
    {
        // The model guards everything; the screens assign column by column.
        $company = new Company();
        $company->forceFill(array_merge([
            'name' => 'Construtora Alfa',
            'street' => 'Rua A, 100',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01000-000',
            'email' => 'contato@alfa.com.br',
            'phone' => '11999990000',
            'created_by' => $this->admin->id,
        ], $attributes));
        $company->save();

        return $company;
    }
}
