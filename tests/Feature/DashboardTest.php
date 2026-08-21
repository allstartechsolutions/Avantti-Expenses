<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        // A role is what carries `dashboard.view` since M18, and every real
        // user has one — see tests/Feature/Permissions/DashboardTest.php.
        $this->seed(\Database\Seeders\RoleSeeder::class);
        app(\Database\Seeders\PermissionSeeder::class)->run();

        $user = User::factory()->create([
            'role_id' => \App\Models\Role::where('name', 'employee')->value('id'),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }
}
