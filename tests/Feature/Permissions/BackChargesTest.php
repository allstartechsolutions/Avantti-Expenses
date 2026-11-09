<?php

namespace Tests\Feature\Permissions;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\BackCharge\BackChargeIndex;
use App\Livewire\BackCharge\BackChargeShow;
use App\Models\Attachment;
use App\Models\BackCharge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BackChargeDeductions;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Back charges, stage 2 (docs/back-charges.md): the list and the page,
 * administrators only by seed, grantable and revocable like every area.
 */
class BackChargesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Vendor $benitez;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->benitez = $this->subcontractor('Benitez Carpentry');
        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id')]);
    }

    private function subcontractor(string $name): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => $name, 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return $vendor;
    }

    private function backCharge(float $amount = 500, array $attributes = []): BackCharge
    {
        return BackCharge::create(array_merge([
            'number' => BackCharge::generateNumber(),
            'subcontractor_id' => $this->benitez->id,
            'amount' => $amount,
            'date' => now()->toDateString(),
            'reason' => 'Damaged drywall',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function lot(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id, 'job_site_name' => $name, 'contact_person' => 'C',
            'email' => 'lot@example.test', 'job_amount' => 100000, 'status' => JobSiteStatus::IN_PROGRESS, 'created_by' => $this->admin->id,
        ]);
    }

    private function deduct(BackCharge $bc, float $amount): void
    {
        $this->actingAs($this->admin);
        $contract = Contract::create([
            'project_id' => $this->project->id, 'subcontractor_id' => $this->benitez->id,
            'contract_number' => Contract::generateContractNumber(), 'status' => 'active',
            'start_date' => now()->toDateString(), 'amount' => 10000, 'created_by' => $this->admin->id,
        ]);
        $payment = ContractPayment::create(['contract_id' => $contract->id, 'amount' => 1000, 'payment_date' => now()->toDateString(), 'payment_method' => 'check', 'created_by' => $this->admin->id]);
        app(BackChargeDeductions::class)->deductFromPayment($payment, [$bc->id => $amount]);
    }

    public function test_the_screens_answer_as_seeded_for_every_role(): void
    {
        $bc = $this->backCharge();

        foreach ([route('back-charges.index'), route('back-charges.show', $bc)] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->user('manager'))->get($url)->assertForbidden();
            $this->actingAs($this->user('employee'))->get($url)->assertForbidden();
        }

        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('back-charges.index'), false);
        $this->actingAs($this->user('manager'))->get(route('dashboard'))->assertDontSee(route('back-charges.index'), false);
    }

    public function test_the_grant_can_be_given_and_taken_away(): void
    {
        $role = Role::create(['name' => 'pm']);
        $role->syncAbilities(['back-charges.view']);
        $pm = User::factory()->create(['role_id' => $role->id]);
        $bc = $this->backCharge();

        $this->actingAs($pm)->get(route('back-charges.show', $bc))->assertOk()->assertDontSee(__('Edit'));
        Livewire::actingAs($pm)->test(BackChargeIndex::class)->call('openBackChargeCreate')->assertForbidden();
        Livewire::actingAs($pm)->test(BackChargeIndex::class)->call('openBackChargeEdit', $bc->id)->assertForbidden();
        Livewire::actingAs($pm)->test(BackChargeIndex::class)->call('delete', $bc->id)->assertForbidden();
        $this->assertNotNull($bc->fresh());

        $role->syncAbilities(['back-charges.view', 'back-charges.create']);
        app(PermissionResolver::class)->flush();
        Livewire::actingAs($pm->fresh())->test(BackChargeIndex::class)
            ->call('openBackChargeCreate')
            ->set('bc_subcontractor_id', (string) $this->benitez->id)
            ->set('bc_amount', '250')
            ->set('bc_reason', 'Cleanup')
            ->call('saveBackCharge')
            ->assertHasNoErrors();
        $this->assertSame(2, BackCharge::count());

        $role->syncAbilities([]);
        app(PermissionResolver::class)->flush();
        $this->actingAs($pm->fresh())->get(route('back-charges.index'))->assertForbidden();
        Livewire::actingAs($pm->fresh())->test(BackChargeShow::class, ['backCharge' => $bc])->assertForbidden();
    }

    public function test_creating_against_an_employee_with_an_origin_lot(): void
    {
        $maria = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Maria Benitez']);
        $lot = $this->lot($this->project, 'Lot 12');

        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->call('openBackChargeCreate')
            ->set('bc_subcontractor_id', (string) $this->benitez->id)
            ->set('bc_subcontractor_employee_id', (string) $maria->id)
            ->set('bc_project_id', (string) $this->project->id)
            ->set('bc_job_site_id', (string) $lot->id)
            ->set('bc_amount', '1000')
            ->set('bc_date', '2026-11-09')
            ->set('bc_reason', 'Broken window')
            ->call('saveBackCharge')
            ->assertHasNoErrors()
            ->assertRedirect(route('back-charges.show', BackCharge::sole()));

        $this->assertSame(__(':number created. Attach photos or documents here.', ['number' => 'BC-0001']), session('message'));

        $bc = BackCharge::sole();
        $this->assertSame(['BC-0001', $maria->id, $lot->id, 1000.0, $this->admin->id], [$bc->number, $bc->subcontractor_employee_id, $bc->job_site_id, $bc->amount, $bc->created_by]);
    }

    public function test_an_employee_or_lot_from_somewhere_else_is_refused(): void
    {
        $wolf = $this->subcontractor('Wolf Construction');
        $wolfsMan = SubcontractorEmployee::create(['subcontractor_id' => $wolf->id, 'name' => 'Jose Ruiz']);
        $other = Project::create(['project_name' => 'Other', 'client_id' => $this->project->client_id, 'contact_person' => 'C', 'email' => 'o@example.test', 'status' => ProjectStatus::CREATED, 'created_by' => $this->admin->id]);
        $foreignLot = $this->lot($other, 'Lot 1');

        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->call('openBackChargeCreate')
            ->set('bc_subcontractor_id', (string) $this->benitez->id)
            ->set('bc_project_id', (string) $this->project->id)
            // Set straight on the property, as a crafted request would.
            ->set('bc_subcontractor_employee_id', (string) $wolfsMan->id)
            ->set('bc_job_site_id', (string) $foreignLot->id)
            ->set('bc_amount', '100')
            ->set('bc_reason', 'x')
            ->call('saveBackCharge')
            ->assertHasErrors(['bc_subcontractor_employee_id', 'bc_job_site_id']);

        $this->assertSame(0, BackCharge::count());
    }

    public function test_what_was_deducted_holds_the_amount_and_the_company(): void
    {
        $bc = $this->backCharge(500);
        $this->deduct($bc, 300);

        $page = Livewire::actingAs($this->admin)->test(BackChargeShow::class, ['backCharge' => $bc])
            ->assertSee('BC-0001')->assertSee('$300.00')->assertSee('$200.00')
            ->call('openBackChargeEdit', $bc->id)
            ->set('bc_amount', '299.99')
            ->call('saveBackCharge')
            ->assertHasErrors('bc_amount');

        $page->set('bc_amount', '500')
            ->set('bc_subcontractor_id', (string) $this->subcontractor('Wolf Construction')->id)
            ->call('saveBackCharge')
            ->assertHasErrors('bc_subcontractor_id');

        // Waiving the rest: lower it to what was deducted.
        $page->set('bc_subcontractor_id', (string) $this->benitez->id)
            ->set('bc_amount', '300')
            ->set('bc_description', 'Waived the rest after the walk-through.')
            ->call('saveBackCharge')
            ->assertHasNoErrors()
            ->assertSee(__(':number updated.', ['number' => 'BC-0001']));

        $this->assertTrue($bc->fresh()->isFullyDeducted());
        $this->assertSame($this->admin->id, $bc->fresh()->updated_by);
    }

    public function test_delete_only_while_nothing_is_deducted(): void
    {
        Storage::fake();
        $bare = $this->backCharge(100);
        Storage::put('back-charges/photo.jpg', 'x');
        $bare->attachments()->create(['file_path' => 'back-charges/photo.jpg', 'original_name' => 'photo.jpg', 'uploaded_by' => $this->admin->id]);
        $used = $this->backCharge(500);
        $this->deduct($used, 100);

        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->assertSee(__('Delete :number? Nothing has been deducted from it.', ['number' => $bare->number]))
            ->assertDontSee(__('Delete :number? Nothing has been deducted from it.', ['number' => $used->number]))
            ->call('delete', $used->id)
            ->call('delete', $bare->id);

        $this->assertNull($bare->fresh());
        $this->assertSame(0, Attachment::count());
        Storage::assertMissing('back-charges/photo.jpg');
        $this->assertNotNull($used->fresh(), 'a back charge with deductions stays');

        Livewire::actingAs($this->admin)->test(BackChargeShow::class, ['backCharge' => $used])->call('delete');
        $this->assertNotNull($used->fresh());
    }

    public function test_the_list_the_cards_the_filters_and_the_csv(): void
    {
        $open = $this->backCharge(500);
        $done = $this->backCharge(100, ['reason' => 'Cleanup']);
        $this->deduct($open, 200);
        $this->deduct($done, 100);
        $wolf = $this->subcontractor('Wolf Construction');
        $this->backCharge(50, ['subcontractor_id' => $wolf->id, 'reason' => 'Trash']);

        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->assertViewHas('summary', fn ($s) => $s['open_count'] === 2 && $s['open_remaining'] === 350.0 && $s['deducted'] === 300.0 && $s['done_count'] === 1)
            ->set('statusFilter', 'open')
            ->assertViewHas('backCharges', fn ($p) => $p->total() === 2)
            ->set('statusFilter', 'deducted')
            ->assertViewHas('backCharges', fn ($p) => $p->pluck('id')->all() === [$done->id])
            ->call('clearFilters')
            ->set('subcontractorFilter', (string) $wolf->id)
            ->assertViewHas('backCharges', fn ($p) => $p->total() === 1)
            ->call('clearFilters')
            ->set('search', 'drywall')
            ->assertViewHas('backCharges', fn ($p) => $p->pluck('id')->all() === [$open->id])
            ->call('exportCsv')
            ->assertFileDownloaded('back-charges-'.now()->format('Y-m-d').'.csv');
    }

    public function test_its_files_answer_to_the_same_grant(): void
    {
        Storage::fake();
        $bc = $this->backCharge();
        Storage::put('back-charges/photo.jpg', 'x');
        $bc->attachments()->create(['file_path' => 'back-charges/photo.jpg', 'original_name' => 'photo.jpg', 'uploaded_by' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('files.download', ['path' => 'back-charges/photo.jpg']))->assertOk();
        $this->actingAs($this->user('manager'))->get(route('files.download', ['path' => 'back-charges/photo.jpg']))->assertForbidden();
    }

    public function test_the_subcontractor_and_the_job_site_are_searched_for(): void
    {
        $this->subcontractor('Wolf Construction');
        $off = $this->subcontractor('Benitez Roofing');
        $off->forceFill(['is_active' => false])->save();
        $maria = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Maria Benitez']);

        $form = Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->call('openBackChargeCreate')
            ->set('bcSubcontractorSearch', 'beni')
            ->assertViewHas('subcontractorResults', fn ($r) => collect($r)->pluck('label')->all() === ['Benitez Carpentry'])
            ->call('selectBcSubcontractor', $this->benitez->id)
            ->assertSet('bc_subcontractor_id', (string) $this->benitez->id)
            ->assertSet('bcSubcontractorSearch', 'Benitez Carpentry')
            ->set('bc_subcontractor_employee_id', (string) $maria->id)
            ->set('bcProjectSearch', 'kent')
            ->assertViewHas('projectResults', fn ($r) => collect($r)->pluck('id')->all() === [$this->project->id])
            ->call('selectBcProject', $this->project->id)
            ->assertSet('bc_project_id', (string) $this->project->id);

        // Typing over the chosen name unlinks it, and its employee with it.
        $form->set('bcSubcontractorSearch', 'Benitez Carp')
            ->assertSet('bc_subcontractor_id', '')
            ->assertSet('bc_subcontractor_employee_id', '')
            // An inactive one cannot be picked by id either.
            ->call('selectBcSubcontractor', $off->id)
            ->assertSet('bc_subcontractor_id', '');

        // Editing shows the names that were saved.
        $bc = $this->backCharge(100, ['project_id' => $this->project->id]);
        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->call('openBackChargeEdit', $bc->id)
            ->assertSet('bcSubcontractorSearch', 'Benitez Carpentry')
            ->assertSet('bcProjectSearch', 'Kent Island');
    }
}
