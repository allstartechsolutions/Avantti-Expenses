<?php

namespace Tests\Feature\BackCharge;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Models\BackCharge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BackChargeDeductions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Back charges, stage 5 (docs/back-charges.md): where else they show — the
 * contract, the subcontractor, the job site and lot — and what they block.
 */
class BackChargePanelsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Vendor $benitez;

    protected Project $project;

    protected JobSite $lot;

    protected Contract $contract;

    protected BackCharge $bc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->actingAs($this->admin);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C', 'email' => 'kent@example.test', 'status' => ProjectStatus::CREATED, 'created_by' => $this->admin->id,
        ]);
        $this->lot = JobSite::create([
            'project_id' => $this->project->id, 'job_site_name' => 'Lot 12', 'contact_person' => 'C',
            'email' => 'lot@example.test', 'job_amount' => 100000, 'status' => JobSiteStatus::IN_PROGRESS, 'created_by' => $this->admin->id,
        ]);

        $this->benitez = new Vendor;
        $this->benitez->forceFill(['name' => 'Benitez Carpentry', 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        $this->contract = Contract::create([
            'project_id' => $this->project->id, 'job_site_id' => $this->lot->id, 'subcontractor_id' => $this->benitez->id, 'contract_number' => 'CTR-1204',
            'status' => 'active', 'start_date' => now()->toDateString(), 'amount' => 10000, 'created_by' => $this->admin->id,
        ]);

        $this->bc = BackCharge::create([
            'number' => 'BC-0007', 'subcontractor_id' => $this->benitez->id, 'project_id' => $this->project->id, 'job_site_id' => $this->lot->id,
            'amount' => 1000, 'date' => now()->toDateString(), 'reason' => 'Damaged drywall', 'created_by' => $this->admin->id,
        ]);

        $payment = ContractPayment::create(['contract_id' => $this->contract->id, 'amount' => 2500, 'payment_date' => now()->toDateString(), 'payment_method' => 'check', 'created_by' => $this->admin->id]);
        app(BackChargeDeductions::class)->deductFromPayment($payment, [$this->bc->id => 600]);
    }

    public function test_the_contract_page_shows_what_was_deducted(): void
    {
        $this->get(route('contracts.show', $this->contract))->assertOk()
            ->assertSee(__('Back charges deducted'))
            ->assertSee('BC-0007')
            ->assertSee('$9,400.00') // adjusted: 10,000 − 600
            ->assertSee('$1,900.00') // paid in cash: 2,500 − 600
            ->assertSee('$7,500.00') // balance
            ->assertSee(__('Check: :amount', ['amount' => '$1,900.00']));
    }

    public function test_the_subcontractor_page_lists_what_is_open(): void
    {
        Livewire::test(SubcontractorShow::class, ['subcontractor' => Subcontractor::find($this->benitez->id)])
            ->assertSee(__('Back Charges'))
            ->assertSee('BC-0007')
            ->assertSee('$400.00');
    }

    public function test_the_job_site_and_lot_list_what_started_there_for_those_who_may_see(): void
    {
        $this->get(route('jobsites.overview', $this->lot))->assertOk()->assertSee('BC-0007')->assertSee(__('Started on this lot'));
        $this->get(route('projects.overview', $this->project))->assertOk()->assertSee('BC-0007');

        $manager = User::factory()->create(['role_id' => Role::where('name', 'manager')->value('id')]);
        $this->actingAs($manager)->get(route('jobsites.overview', $this->lot))->assertOk()->assertDontSee('BC-0007');
    }

    public function test_a_subcontractor_or_employee_with_back_charges_is_not_deleted(): void
    {
        $this->contract->payments()->delete();
        $this->contract->delete();

        Livewire::test(SubcontractorShow::class, ['subcontractor' => Subcontractor::find($this->benitez->id)])->call('deleteSubcontractor');
        $this->assertNotNull(Vendor::find($this->benitez->id));
        $this->assertTrue(Vendor::hasSubcontractorRecords($this->benitez->id));

        $maria = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Maria Benitez']);
        $this->bc->update(['subcontractor_employee_id' => $maria->id]);
        Livewire::test(SubcontractorShow::class, ['subcontractor' => Subcontractor::find($this->benitez->id)])->call('deleteEmployee', $maria->id);
        $this->assertNotNull($maria->fresh());
    }

    public function test_merging_a_duplicate_takes_its_back_charges_along(): void
    {
        $survivor = new Vendor;
        $survivor->forceFill(['name' => 'Benitez Carpentry LLC', 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        $this->benitez->mergeInto($survivor);

        $this->assertSame($survivor->id, $this->bc->fresh()->subcontractor_id);
    }
}
