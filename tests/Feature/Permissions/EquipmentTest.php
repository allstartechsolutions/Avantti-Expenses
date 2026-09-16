<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\CompanyExpense\CompanyExpenseCreate;
use App\Livewire\Equipment\EquipmentCreate;
use App\Livewire\Equipment\EquipmentEdit;
use App\Livewire\Equipment\EquipmentShow;
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\JobSite\JobSiteEquipment;
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\Project\ProjectEquipment;
use App\Livewire\Project\ProjectShow;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Equipment — docs/equipment-module.md.
 *
 * A company record: vehicles, machinery and tools. Nothing existed before,
 * so "reproduced" is "every seeded role reaches it" — the owner's decision:
 * readings, maintenance and assignments are site work and stay with
 * employees; deleting is administrator-only like every other delete on a
 * company record.
 */
class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
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

    protected function makeEquipment(array $attributes = []): Equipment
    {
        return Equipment::create(array_merge([
            'name' => 'Excavator '.str()->random(4),
            'equipment_type' => 'machinery',
            'ownership' => 'owned',
            'meter_type' => 'hours',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Equipment Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-eq@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-eq@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    /** Somebody confined to one project, holding these abilities there. */
    protected function memberOf(Project $project, array $abilities = []): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | Reproduced, then revocable
    |---------------------------------------------------------------------------
    */

    public function test_every_seeded_role_reaches_the_register(): void
    {
        $equipment = $this->makeEquipment();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('equipment.index'))->assertOk()->assertSee($equipment->name);
            $this->actingAs($user)->get(route('equipment.maintenance.index'))->assertOk();
            $this->actingAs($user)->get(route('equipment.show', $equipment))->assertOk();
            $this->actingAs($user)->get(route('equipment.create'))->assertOk();
            $this->actingAs($user)->get(route('equipment.edit', $equipment))->assertOk();
        }
    }

    public function test_the_register_is_a_grant_that_can_be_taken_away(): void
    {
        $equipment = $this->makeEquipment();
        $stranger = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($stranger)->get(route('equipment.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('equipment.maintenance.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('equipment.show', $equipment))->assertForbidden();
        $this->actingAs($stranger)->get(route('equipment.create'))->assertForbidden();
        $this->actingAs($stranger)->get(route('equipment.edit', $equipment))->assertForbidden();

        $viewer = $this->roleWith(['equipment.view']);
        $this->actingAs($viewer)->get(route('equipment.index'))->assertOk();
        $this->actingAs($viewer)->get(route('equipment.show', $equipment))->assertOk();
        $this->actingAs($viewer)->get(route('equipment.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('equipment.edit', $equipment))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Separate
    |---------------------------------------------------------------------------
    */

    public function test_creating_stores_everything_the_form_knows_and_the_starting_reading(): void
    {
        $creator = $this->roleWith(['equipment.view', 'equipment.create']);

        Livewire::actingAs($creator)
            ->test(EquipmentCreate::class)
            ->set('name', 'Ford F-250 #2')
            ->set('equipment_type', 'vehicle')
            ->set('asset_tag', 'V-002')
            ->set('make', 'Ford')
            ->set('model', 'F-250')
            ->set('year', '2022')
            ->set('plate', 'abc1d23')
            ->set('ownership', 'owned')
            ->set('purchase_date', '2022-03-01')
            ->set('purchase_cost', '185000.50')
            ->set('meter_type', 'km')
            ->set('initial_meter', '45210.5')
            ->set('initial_meter_at', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $equipment = Equipment::where('name', 'Ford F-250 #2')->sole();
        $this->assertSame('ABC1D23', $equipment->plate, 'Plates are stored upper-case.');
        $this->assertEquals(185000.50, $equipment->purchase_cost);
        $this->assertSame(18500050, (int) $equipment->getRawOriginal('purchase_cost'), 'Money is cents in the database.');
        $this->assertEquals(45210.5, $equipment->current_meter);
        $this->assertSame(1, $equipment->readings()->count());
        $this->assertSame($creator->id, $equipment->created_by);
        $this->assertSame('created', $equipment->histories()->first()->action);

        // A viewer cannot save through the component either.
        Livewire::actingAs($this->roleWith(['equipment.view']))
            ->test(EquipmentCreate::class)
            ->assertForbidden();
    }

    public function test_a_vehicle_defaults_its_meter_to_the_countrys_unit(): void
    {
        config(['app.country' => 'BR']);

        Livewire::actingAs($this->admin)
            ->test(EquipmentCreate::class)
            ->assertSet('meter_type', 'none')
            ->set('equipment_type', 'vehicle')
            ->assertSet('meter_type', 'km');
    }

    public function test_editing_is_its_own_grant_and_records_what_changed(): void
    {
        $equipment = $this->makeEquipment(['name' => 'Loader']);

        Livewire::actingAs($this->roleWith(['equipment.view']))
            ->test(EquipmentEdit::class, ['equipment' => $equipment])
            ->assertForbidden();

        Livewire::actingAs($this->roleWith(['equipment.view', 'equipment.edit']))
            ->test(EquipmentEdit::class, ['equipment' => $equipment])
            ->assertSet('name', 'Loader')
            ->set('name', 'Loader 950')
            ->set('status', 'retired')
            ->call('save')
            ->assertHasNoErrors();

        $equipment->refresh();
        $this->assertSame('Loader 950', $equipment->name);
        $this->assertSame('retired', $equipment->status);
        $this->assertNotNull($equipment->retired_at);
        $this->assertEqualsCanonicalizing(['status_changed', 'edited'], $equipment->histories()->pluck('action')->all());
    }

    public function test_deleting_is_held_apart_from_editing_and_takes_the_record_with_it(): void
    {
        $equipment = $this->makeEquipment();
        $equipment->readings()->create(['reading' => 10, 'read_at' => now()->toDateString(), 'recorded_by' => $this->admin->id]);

        $editor = $this->roleWith(['equipment.view', 'equipment.edit']);
        Livewire::actingAs($editor)->test(EquipmentShow::class, ['equipment' => $equipment])->call('confirmDelete')->assertForbidden();
        Livewire::actingAs($editor)->test(EquipmentShow::class, ['equipment' => $equipment])->call('delete')->assertForbidden();
        $this->assertNotNull($equipment->fresh());

        // The employee seed has no delete; the administrator does.
        Livewire::actingAs($this->user('employee'))->test(EquipmentShow::class, ['equipment' => $equipment])->call('delete')->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test(EquipmentShow::class, ['equipment' => $equipment])
            ->call('confirmDelete')
            ->assertSet('showDeleteModal', true)
            ->assertSee(trans_choice(':count meter reading|:count meter readings', 1, ['count' => 1]))
            ->call('delete')
            ->assertRedirect(route('equipment.index'));

        $this->assertNull($equipment->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | Assignments and the project / job-site tab
    |---------------------------------------------------------------------------
    */

    public function test_assigning_is_its_own_grant_and_keeps_a_log(): void
    {
        $project = $this->makeProject('Ours');
        $site = $this->makeSite($project, 'Site A');
        $other = $this->makeProject('Theirs');
        $otherSite = $this->makeSite($other, 'Site X');
        $equipment = $this->makeEquipment();

        $editor = $this->roleWith(['equipment.view', 'equipment.edit']);
        Livewire::actingAs($editor)->test(EquipmentShow::class, ['equipment' => $equipment])->call('startAssign')->assertForbidden();
        Livewire::actingAs($editor)->test(EquipmentShow::class, ['equipment' => $equipment])->call('assign')->assertForbidden();

        $assigner = $this->roleWith(['equipment.view', 'equipment.assign', 'projects.view', 'project.view']);
        $component = Livewire::actingAs($assigner)->test(EquipmentShow::class, ['equipment' => $equipment]);

        // A job site of another project cannot be chosen under this one.
        $component->call('startAssign')
            ->set('assign_project_id', (string) $project->id)
            ->set('assign_job_site_id', (string) $otherSite->id)
            ->set('assign_started_at', '2026-09-01')
            ->call('assign')
            ->assertHasErrors(['assign_job_site_id']);

        // Nothing at all is refused too.
        $component->set('assign_project_id', '')->set('assign_job_site_id', '')->set('assign_responsible_user_id', '')
            ->call('assign')->assertHasErrors(['assign_project_id']);

        $component->set('assign_project_id', (string) $project->id)
            ->set('assign_job_site_id', (string) $site->id)
            ->set('assign_responsible_user_id', (string) $assigner->id)
            ->set('assign_started_at', '2026-09-01')
            ->set('assign_notes', 'Driven over by Joe')
            ->call('assign')
            ->assertHasNoErrors()
            ->assertSet('showAssignModal', false);

        $equipment->refresh();
        $this->assertSame($project->id, $equipment->project_id);
        $this->assertSame($site->id, $equipment->job_site_id);
        $this->assertSame($assigner->id, $equipment->responsible_user_id);
        $this->assertSame('2026-09-01', $equipment->assigned_at->toDateString());
        $this->assertSame(1, $equipment->assignments()->open()->count());

        // Moving closes the first stay the day the second starts.
        $component->call('startAssign')
            ->set('assign_project_id', (string) $other->id)
            ->set('assign_job_site_id', '')
            ->set('assign_started_at', '2026-09-10')
            ->call('assign')
            ->assertHasNoErrors();

        $equipment->refresh();
        $this->assertSame($other->id, $equipment->project_id);
        $this->assertNull($equipment->job_site_id);
        $this->assertSame(2, $equipment->assignments()->count());
        $this->assertSame('2026-09-10', $equipment->assignments()->whereNotNull('ended_at')->first()->ended_at->toDateString());

        // Sending it back leaves it nowhere, and the log says so.
        $component->call('endAssignment')->assertHasNoErrors();
        $equipment->refresh();
        $this->assertFalse($equipment->isAssigned());
        $this->assertSame(0, $equipment->assignments()->open()->count());
        $this->assertContains('unassigned', $equipment->histories()->pluck('action')->all());
    }

    public function test_the_project_and_job_site_tabs_show_what_is_here_and_obey_confinement(): void
    {
        $project = $this->makeProject('Ours');
        $site = $this->makeSite($project, 'Site A');
        $other = $this->makeProject('Theirs');
        $here = $this->makeEquipment(['name' => 'Loader here']);
        $elsewhere = $this->makeEquipment(['name' => 'Truck elsewhere']);
        $here->assignTo($project->id, $site->id, null, '2026-09-01');
        $elsewhere->assignTo($other->id, null, null, '2026-09-01');

        $this->actingAs($this->admin)->get(route('projects.equipment', $project))->assertOk()->assertSee('Loader here');
        $this->actingAs($this->admin)->get(route('jobsites.equipment', $site))->assertOk()->assertSee('Loader here');

        // What is here is the loader alone; the truck is only offered by the "send here" picker.
        Livewire::actingAs($this->admin)->test(ProjectEquipment::class, ['project' => $project])
            ->assertViewHas('here', fn ($here) => $here->pluck('name')->all() === ['Loader here'])
            ->assertViewHas('available', fn ($available) => $available->pluck('name')->contains('Truck elsewhere'));

        // A member of the project sees the tab when their membership grants
        // equipment.view — narrower than the role, which is what confinement
        // means; a member of another project never does.
        $this->actingAs($this->memberOf($project, ['equipment.view']))->get(route('projects.equipment', $project))->assertOk();
        $this->actingAs($this->memberOf($project))->get(route('projects.equipment', $project))->assertForbidden();
        $this->actingAs($this->memberOf($other, ['equipment.view']))->get(route('projects.equipment', $project))->assertForbidden();
        $this->actingAs($this->memberOf($other, ['equipment.view']))->get(route('jobsites.equipment', $site))->assertForbidden();

        // Without the equipment grant, the tab is gone even for a member.
        $blind = $this->roleWith(['projects.view', 'project.view']);
        $this->actingAs($blind)->get(route('projects.equipment', $project))->assertForbidden();

        // Sending something here from the tab needs the assign grant.
        $viewer = $this->roleWith(['projects.view', 'project.view', 'equipment.view']);
        Livewire::actingAs($viewer)->test(ProjectEquipment::class, ['project' => $project])
            ->set('assignEquipmentId', (string) $elsewhere->id)->call('assignHere')->assertForbidden();

        Livewire::actingAs($this->admin)->test(JobSiteEquipment::class, ['jobSite' => $site])
            ->set('assignEquipmentId', (string) $elsewhere->id)
            ->set('assignStartedAt', '2026-09-12')
            ->call('assignHere')
            ->assertHasNoErrors();

        $elsewhere->refresh();
        $this->assertSame($site->id, $elsewhere->job_site_id);
        $this->assertSame($project->id, $elsewhere->project_id, 'A job site implies its project.');

        Livewire::actingAs($this->admin)->test(JobSiteEquipment::class, ['jobSite' => $site])
            ->call('sendBack', $elsewhere->id)->assertHasNoErrors();
        $this->assertFalse($elsewhere->fresh()->isAssigned());
    }

    /*
    |---------------------------------------------------------------------------
    | Maintenance, readings and findings
    |---------------------------------------------------------------------------
    */

    public function test_maintaining_is_its_own_grant_and_the_page_runs_the_whole_cycle(): void
    {
        $equipment = $this->makeEquipment(['meter_type' => 'km']);
        $other = $this->makeEquipment(['name' => 'Other machine']);
        $foreign = $other->maintenances()->create(['title' => 'Theirs', 'maintenance_type' => 'corrective', 'status' => 'scheduled', 'scheduled_date' => now()->toDateString()]);

        $assigner = $this->roleWith(['equipment.view', 'equipment.edit', 'equipment.assign']);
        foreach (['startReading', 'startPlan', 'startSchedule', 'startFinding'] as $method) {
            Livewire::actingAs($assigner)->test(EquipmentShow::class, ['equipment' => $equipment])->call($method)->assertForbidden();
        }

        $mechanic = $this->roleWith(['equipment.view', 'equipment.maintain']);
        $page = Livewire::actingAs($mechanic)->test(EquipmentShow::class, ['equipment' => $equipment]);

        // A reading, then a plan that schedules from it.
        $page->call('startReading')->set('reading_value', '10000')->set('reading_date', now()->toDateString())->call('saveReading')->assertHasNoErrors();
        $this->assertEquals(10000, $equipment->fresh()->current_meter);

        $page->call('startPlan')->set('plan_title', 'Oil change')->set('plan_interval_days', '180')->set('plan_interval_meter', '10000')->call('savePlan')->assertHasNoErrors();
        $this->assertSame(1, $equipment->plans()->count());
        $next = $equipment->maintenances()->open()->sole();
        $this->assertEquals(20000, $next->due_meter);

        // A plan without any interval is refused.
        $page->call('startPlan')->set('plan_title', 'Nothing')->set('plan_interval_days', '')->set('plan_interval_meter', '')->call('savePlan')->assertHasErrors(['plan_interval_days']);

        // A one-off with neither date nor meter is refused; with a date it is scheduled.
        $page->call('startSchedule')->set('m_title', 'Fix mirror')->set('m_scheduled_date', '')->set('m_due_meter', '')->call('saveSchedule')->assertHasErrors(['m_scheduled_date']);
        $page->call('startSchedule')->set('m_title', 'Fix mirror')->set('m_scheduled_date', now()->toDateString())->call('saveSchedule')->assertHasNoErrors();
        $repair = $equipment->maintenances()->where('title', 'Fix mirror')->sole();

        // Another machine's maintenance is not reachable through this page.
        try {
            $page->call('startMaintenance', $foreign->id);
            $this->fail('A maintenance of another machine was accepted.');
        } catch (ModelNotFoundException) {
        }
        $this->assertSame('scheduled', $foreign->fresh()->status);

        // Start, record a finding, complete with the finding ticked.
        $page->call('startMaintenance', $repair->id)->assertHasNoErrors();
        $this->assertSame('in_progress', $repair->fresh()->status);
        $this->assertSame('in_maintenance', $equipment->fresh()->status);

        $page->call('startFinding', $repair->id)->set('f_description', 'Cracked hose')->set('f_severity', 'high')->call('saveFinding')->assertHasNoErrors();
        $finding = $equipment->findings()->sole();

        $page->call('startComplete', $repair->id)
            ->set('c_completed_date', now()->toDateString())
            ->set('c_meter', '9000')   // lower than the last reading: refused
            ->call('saveComplete')
            ->assertHasErrors(['c_meter']);
        $this->assertSame('in_progress', $repair->fresh()->status);

        $page->set('c_meter', '10200')->set('c_performed_by', 'internal')->set('c_resolve', [(string) $finding->id])->call('saveComplete')->assertHasNoErrors();
        $this->assertSame('completed', $repair->fresh()->status);
        $this->assertFalse($finding->fresh()->isOpen());
        $this->assertEquals(10200, $equipment->fresh()->current_meter);
        $this->assertSame('active', $equipment->fresh()->status);

        // Every tab renders with data on it.
        foreach (['maintenance' => 'Oil change', 'readings' => '10,200.0 km', 'findings' => 'Cracked hose'] as $tab => $expected) {
            Livewire::actingAs($mechanic)->test(EquipmentShow::class, ['equipment' => $equipment])->call('setActiveTab', $tab)->assertOk()->assertSee($expected);
        }

        // The cancel path, with its reason.
        $page->call('startCancel', $next->id)->set('cancel_reason', 'Sold the truck')->call('saveCancel')->assertHasNoErrors();
        $this->assertSame('cancelled', $next->fresh()->status);
        $this->assertSame(1, $equipment->maintenances()->open()->count(), 'The plan regenerated its next occurrence.');
    }

    /*
    |---------------------------------------------------------------------------
    | Costs — expenses tagged to a piece of equipment
    |---------------------------------------------------------------------------
    */

    protected function lineItem(string $name, float $amount): array
    {
        return ['budget_item_id' => null, 'cost_code' => null, 'catalog_item_id' => null, 'item_name' => $name, 'item_type' => 'custom', 'description' => '', 'quantity' => 1, 'unit' => '', 'unit_price' => $amount, 'total_amount' => $amount];
    }

    public function test_an_expense_can_be_tagged_to_equipment_and_its_maintenance_from_either_side(): void
    {
        $truck = $this->makeEquipment(['name' => 'Truck', 'meter_type' => 'km', 'purchase_cost' => 50000]);
        $other = $this->makeEquipment(['name' => 'Other']);
        $service = $truck->maintenances()->create(['title' => 'Service', 'maintenance_type' => 'preventive', 'status' => 'in_progress', 'scheduled_date' => now()->toDateString()]);
        $foreign = $other->maintenances()->create(['title' => 'Theirs', 'maintenance_type' => 'preventive', 'status' => 'scheduled', 'scheduled_date' => now()->toDateString()]);
        $project = $this->makeProject('Ours');
        $rent = ExpenseCategory::where('key', 'overhead.rent')->firstOrFail();

        // A company expense, pre-tagged by the link on the equipment page.
        $this->get(route('company-expenses.create', ['equipment' => $truck->id, 'maintenance' => $service->id]));
        Livewire::withQueryParams(['equipment' => $truck->id, 'maintenance' => $service->id])
            ->actingAs($this->admin)
            ->test(CompanyExpenseCreate::class)
            ->assertSet('expense_equipment_id', (string) $truck->id)
            ->assertSet('expense_equipment_maintenance_id', (string) $service->id)
            ->set('expense_date', now()->toDateString())
            ->set('expense_category_id', $rent->id)
            ->call('openAddItemModal')->set('item_name', 'Oil and filter')->set('item_quantity', 1)->set('item_unit_price', 300)->call('saveItem')
            ->set('expense_status', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $oil = Expense::where('equipment_id', $truck->id)->sole();
        $this->assertSame($service->id, $oil->equipment_maintenance_id);
        $this->assertSame(30000, $service->fresh()->costInCents($this->admin));

        // A project expense tagged to the same truck; another machine's maintenance is refused.
        $page = Livewire::actingAs($this->admin)->test(ExpenseCreate::class, ['project' => $project])
            ->set('expense_date', now()->toDateString())
            ->call('openAddItemModal')->set('item_name', 'Diesel')->set('item_quantity', 1)->set('item_unit_price', 200)->call('saveItem')
            ->set('expense_status', 'paid')
            ->set('expense_equipment_id', (string) $truck->id)
            ->set('expense_equipment_maintenance_id', (string) $foreign->id)
            ->call('save')
            ->assertHasErrors(['expense_equipment_maintenance_id']);

        $page->set('expense_equipment_maintenance_id', '')->call('save')->assertHasNoErrors();
        $this->assertSame(2, $truck->expenses()->count());
        $this->assertEquals(50500, $truck->fresh()->totalCostOfOwnership($this->admin), 'Purchase plus both tagged expenses.');

        // The costs tab, and the narrowing for somebody confined to another project.
        Livewire::actingAs($this->admin)->test(EquipmentShow::class, ['equipment' => $truck])
            ->call('setActiveTab', 'costs')->assertOk()->assertSee('Oil and filter')->assertSee('Diesel');

        $outsider = $this->memberOf($this->makeProject('Theirs'), ['equipment.view']);
        $this->assertEquals(50000, $truck->totalCostOfOwnership($outsider), 'Neither the company\'s row nor another project\'s counts for them.');
        Livewire::actingAs($outsider)->test(EquipmentShow::class, ['equipment' => $truck])
            ->call('setActiveTab', 'costs')->assertOk()->assertDontSee('Diesel')->assertDontSee('Oil and filter');

        // With expenses on it, the truck cannot be deleted.
        Livewire::actingAs($this->admin)->test(EquipmentShow::class, ['equipment' => $truck])->call('confirmDelete')->call('delete');
        $this->assertNotNull($truck->fresh());
        $this->assertSame(['expenses' => 2], $truck->deleteBlockers());
    }

    public function test_an_equipment_photo_is_served_to_somebody_who_may_see_it_and_refused_to_others(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('equipment/photos/truck.jpg', 'jpg');
        $equipment = $this->makeEquipment(['photo_path' => 'equipment/photos/truck.jpg']);

        $this->actingAs($this->admin)->get(route('files.show', ['path' => 'equipment/photos/truck.jpg']))->assertOk();
        $this->actingAs($this->roleWith(['projects.view']))->get(route('files.show', ['path' => 'equipment/photos/truck.jpg']))->assertForbidden();
        $this->actingAs($this->admin)->get(route('files.show', ['path' => 'equipment/photos/nobody.jpg']))->assertNotFound();
    }

    public function test_a_confined_member_cannot_assign_to_a_project_they_cannot_open(): void
    {
        $theirs = $this->makeProject('Theirs');
        $ours = $this->makeProject('Ours');
        $equipment = $this->makeEquipment();
        $member = $this->memberOf($ours, ['equipment.view', 'equipment.assign']);

        Livewire::actingAs($member)->test(EquipmentShow::class, ['equipment' => $equipment])
            ->call('startAssign')
            ->set('assign_project_id', (string) $theirs->id)
            ->set('assign_started_at', now()->toDateString())
            ->call('assign')
            ->assertHasErrors(['assign_project_id']);
        $this->assertNull($equipment->fresh()->project_id);

        Livewire::actingAs($member)->test(EquipmentShow::class, ['equipment' => $equipment])
            ->call('startAssign')
            ->set('assign_project_id', (string) $ours->id)
            ->set('assign_started_at', now()->toDateString())
            ->call('assign')
            ->assertHasNoErrors();
        $this->assertSame($ours->id, $equipment->fresh()->project_id);
    }

    public function test_the_legacy_project_modal_validates_the_equipment_tag_too(): void
    {
        $project = $this->makeProject('Ours');
        $truck = $this->makeEquipment();
        $other = $this->makeEquipment(['name' => 'Other']);
        $foreign = $other->maintenances()->create(['title' => 'Theirs', 'maintenance_type' => 'corrective', 'status' => 'scheduled', 'scheduled_date' => now()->toDateString()]);

        Livewire::actingAs($this->admin)->test(ProjectShow::class, ['project' => $project])
            ->call('openExpenseCreateModal')
            ->set('expense_date', now()->toDateString())
            ->set('expense_equipment_id', (string) $truck->id)
            ->set('expense_equipment_maintenance_id', (string) $foreign->id)
            ->call('saveExpense')
            ->assertHasErrors(['expense_equipment_maintenance_id']);

        $this->assertSame(0, Expense::where('equipment_id', $truck->id)->count());
    }

    public function test_deleting_a_project_ends_the_stays_there(): void
    {
        $project = $this->makeProject('Ours');
        $equipment = $this->makeEquipment();
        $equipment->assignTo($project->id, null, null, '2026-09-01');

        EquipmentAssignment::closeFor($project);

        $this->assertSame(0, $equipment->assignments()->open()->count());
        $this->assertNotNull($equipment->assignments()->first()->ended_at);
        $this->assertFalse($equipment->fresh()->isAssigned());
        $this->assertContains('unassigned', $equipment->histories()->pluck('action')->all(), 'The log says it left.');

        // The job-site page's own Delete button closes the stay as well.
        $site = $this->makeSite($this->makeProject('Two'), 'Site B');
        $loader = $this->makeEquipment(['name' => 'Loader']);
        $loader->assignTo($site->project_id, $site->id, null, '2026-09-01');
        Livewire::actingAs($this->admin)->test(JobSiteShow::class, ['jobSite' => $site])
            ->call('confirmDeleteJobSite')->call('deleteJobSite');
        $this->assertNull($site->fresh());
        $this->assertFalse($loader->fresh()->isAssigned());
        $this->assertSame(0, $loader->assignments()->open()->count());
    }

    public function test_the_show_page_prints_everything_the_record_knows(): void
    {
        $equipment = $this->makeEquipment([
            'name' => 'Bobcat S650',
            'asset_tag' => 'M-014',
            'serial_number' => 'SN-998877',
            'purchase_cost' => 65000,
            'purchase_date' => '2020-01-15',
            'warranty_until' => '2019-01-01',
            'notes' => 'Keys in the office drawer',
        ]);

        $this->actingAs($this->admin)
            ->get(route('equipment.show', $equipment))
            ->assertOk()
            ->assertSee('Bobcat S650')
            ->assertSee('M-014')
            ->assertSee('SN-998877')
            ->assertSee(__('Warranty expired'))
            ->assertSee(__('Total cost of ownership'))
            ->assertSee('Keys in the office drawer')
            ->assertSee($this->admin->name)
            ->assertSee(__('Nothing scheduled'));
    }
}
