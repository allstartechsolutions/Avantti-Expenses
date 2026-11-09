<?php

namespace Tests\Feature\BackCharge;

use App\Models\BackCharge;
use App\Models\Contract;
use App\Models\PaymentBatch;
use App\Models\User;
use Database\Seeders\BackChargeTestSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The hand-testing data (BackChargeTestSeeder) builds every case, and only once. */
class BackChargeTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_one_back_charge_per_state_and_stays_additive(): void
    {
        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->seed(BackChargeTestSeeder::class);
        $this->seed(BackChargeTestSeeder::class); // a second run adds nothing

        $labels = BackCharge::withDeducted()->orderBy('id')->get()->map->getStatusLabel()->all();
        $this->assertSame([__('Open'), __('Open'), __('Open'), __('Fully deducted'), __('Waived'), __('Pending approval')], $labels);

        $this->assertSame(17400.0, Contract::where('contract_number', 'CTR-T02')->first()->getNetAmount());
        $this->assertSame('partially_paid', Contract::where('contract_number', 'CTR-T01')->value('status'));

        foreach (['test-admin', 'test-pm', 'test-manager'] as $who) {
            $this->assertNotNull(User::where('email', "{$who}@avantti.test")->first());
        }

        $pm = User::where('email', 'test-pm@avantti.test')->first();
        $this->assertTrue($pm->can('back-charges.deduct'));
        $this->assertFalse($pm->can('back-charges.delete'));
        $this->assertFalse(User::where('email', 'test-manager@avantti.test')->first()->can('back-charges.view'));

        $this->actingAs($pm)->get(route('back-charges.index'))->assertOk()->assertSee(__('Pending approval'));

        // The draft batch offers Ana's ladder only on her contract, CTR-T01.
        $draft = PaymentBatch::where('name', 'Test — Week 46 (draft)')->sole();
        $this->actingAs($pm)->get(route('payment-batches.edit', $draft))->assertOk()
            ->assertSee('Test — Lost company ladder')->assertSee(__('Deduct now'));
    }
}
