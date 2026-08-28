<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Approval\ApprovalShow;
use App\Livewire\Project\ProjectDailyReports;
use App\Livewire\Project\ProjectEdit;
use App\Livewire\PurchaseOrder\PurchaseOrderShow;
use App\Livewire\Report\ExpenseReport;
use App\Models\Approval;
use App\Models\ApprovalPackage;
use App\Models\Client;
use App\Models\DailyReport;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The grants that were declared and enforced nothing.
 *
 * Seven abilities sat in the catalogue showing as real permissions on the
 * access screens while doing absolutely nothing when granted:
 * `reports.export`, `purchase-orders.delete`, `daily-reports.delete`,
 * `approvals.delete`, `approvals.manage_packages`, `project.archive` and a
 * duplicate `projects.archive`.
 *
 * `AbilityCatalogTest::test_every_declared_ability_is_enforced_somewhere` now
 * stops another one appearing. This file proves these seven do what their
 * names say.
 */
class OrphanedGrantsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $jobSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'status' => ProjectStatus::IN_PROGRESS,
            'created_by' => $this->admin->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'Contact',
            'email' => 'torre-a@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function memberOf(array $abilities, Project|JobSite|null $scope = null): User
    {
        $scope ??= $this->project;

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ])->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | reports.export — reading a figure and taking the file are two acts
    |---------------------------------------------------------------------------
    */

    public function test_exporting_a_report_needs_its_own_grant(): void
    {
        // Can open the expense report; cannot walk out with the file.
        $reader = User::factory()->create(['role_id' => Role::where('name', 'employee')->value('id')]);
        $reader->abilityOverrides()->create(['ability' => 'reports.expenses', 'granted' => true]);
        $reader->abilityOverrides()->create(['ability' => 'reports.export', 'granted' => false]);

        $this->actingAs($reader)->get(route('reports.expenses'))->assertOk();

        Livewire::actingAs($reader)
            ->test(ExpenseReport::class)
            ->call('exportCsv')
            ->assertForbidden();
    }

    public function test_the_export_grant_lets_the_file_out(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ExpenseReport::class)
            ->call('exportCsv')
            ->assertOk();
    }

    public function test_the_export_button_is_hidden_without_the_grant(): void
    {
        $reader = User::factory()->create(['role_id' => Role::where('name', 'employee')->value('id')]);
        $reader->abilityOverrides()->create(['ability' => 'reports.expenses', 'granted' => true]);
        $reader->abilityOverrides()->create(['ability' => 'reports.export', 'granted' => false]);

        $this->actingAs($reader)->get(route('reports.expenses'))->assertOk()->assertDontSee(__('Export CSV'));
        $this->actingAs($this->admin)->get(route('reports.expenses'))->assertOk()->assertSee(__('Export CSV'));
    }

    /*
    |---------------------------------------------------------------------------
    | purchase-orders.delete
    |---------------------------------------------------------------------------
    */

    protected function purchaseOrder(array $attributes = []): PurchaseOrder
    {
        return PurchaseOrder::create(array_merge([
            'project_id' => $this->project->id,
            'po_number' => 'PO-'.str()->random(5),
            'status' => 'draft',
            'po_date' => now(),
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_a_draft_purchase_order_can_be_deleted(): void
    {
        $po = $this->purchaseOrder();
        $id = $po->id;

        Livewire::actingAs($this->admin)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $po])
            ->call('deletePurchaseOrder');

        $this->assertNull(PurchaseOrder::find($id));
    }

    public function test_an_approved_purchase_order_cannot_be_deleted(): void
    {
        $po = $this->purchaseOrder(['status' => 'approved']);

        $this->assertFalse($po->canBeDeleted());

        Livewire::actingAs($this->admin)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $po])
            ->call('deletePurchaseOrder')
            ->assertForbidden();

        $this->assertNotNull($po->fresh());
    }

    public function test_deleting_a_purchase_order_needs_the_grant(): void
    {
        $editor = $this->memberOf(['purchase-orders.view', 'purchase-orders.edit']);
        $po = $this->purchaseOrder();

        Livewire::actingAs($editor)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $po])
            ->call('deletePurchaseOrder')
            ->assertForbidden();

        $this->assertNotNull($po->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | daily-reports.delete
    |---------------------------------------------------------------------------
    */

    protected function dailyReport(array $attributes = []): DailyReport
    {
        return DailyReport::create(array_merge([
            'project_id' => $this->project->id,
            'report_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_an_unlocked_daily_report_can_be_deleted(): void
    {
        $report = $this->dailyReport();
        $id = $report->id;

        Livewire::actingAs($this->admin)
            ->test(ProjectDailyReports::class, ['project' => $this->project])
            ->call('deleteDailyReport', $id);

        $this->assertNull(DailyReport::find($id));
    }

    public function test_a_locked_daily_report_is_the_record_of_that_day(): void
    {
        $report = $this->dailyReport(['locked_at' => now(), 'locked_by' => $this->admin->id]);

        $this->assertFalse($report->canBeDeleted());

        Livewire::actingAs($this->admin)
            ->test(ProjectDailyReports::class, ['project' => $this->project])
            ->call('deleteDailyReport', $report->id)
            ->assertForbidden();

        $this->assertNotNull($report->fresh());
    }

    public function test_deleting_a_daily_report_needs_the_grant(): void
    {
        $viewer = $this->memberOf(['daily-reports.view', 'daily-reports.edit']);
        $report = $this->dailyReport();

        Livewire::actingAs($viewer)
            ->test(ProjectDailyReports::class, ['project' => $this->project])
            ->call('deleteDailyReport', $report->id)
            ->assertForbidden();

        $this->assertNotNull($report->fresh());
    }

    public function test_a_report_from_another_project_is_refused(): void
    {
        $other = Project::create([
            'project_name' => 'Not Ours',
            'client_id' => $this->project->client_id,
            'contact_person' => 'C',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $elsewhere = DailyReport::create([
            'project_id' => $other->id,
            'report_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
        ]);

        // The lookup is scoped to this page's own project, so an id from
        // another one never resolves — findOrFail is what refuses it.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            Livewire::actingAs($this->admin)
                ->test(ProjectDailyReports::class, ['project' => $this->project])
                ->call('deleteDailyReport', $elsewhere->id);
        } finally {
            $this->assertNotNull($elsewhere->fresh(), 'The other project\'s report is untouched.');
        }
    }

    /*
    |---------------------------------------------------------------------------
    | approvals.delete
    |---------------------------------------------------------------------------
    */

    protected function approval(array $attributes = []): Approval
    {
        return Approval::create(array_merge([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato do hall',
            'type' => Approval::TYPE_MATERIAL,
            'status' => Approval::DRAFT,
            'created_by_id' => $this->admin->id,
        ], $attributes));
    }

    public function test_a_draft_approval_can_be_deleted(): void
    {
        $approval = $this->approval();
        $id = $approval->id;

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->call('deleteApproval');

        $this->assertNull(Approval::find($id));
    }

    public function test_a_submitted_approval_cannot_be_deleted(): void
    {
        $approval = $this->approval(['status' => Approval::IN_REVIEW]);

        $this->assertFalse($approval->canBeDeleted());

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->call('deleteApproval')
            ->assertForbidden();

        $this->assertNotNull($approval->fresh());
    }

    public function test_deleting_an_approval_needs_the_grant(): void
    {
        $editor = $this->memberOf(['approvals.view', 'approvals.edit']);
        $approval = $this->approval();

        Livewire::actingAs($editor)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->call('deleteApproval')
            ->assertForbidden();

        $this->assertNotNull($approval->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | approvals.manage_packages
    |---------------------------------------------------------------------------
    */

    public function test_a_package_can_be_started_and_the_approval_put_in_it(): void
    {
        $approval = $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('newPackageTitle', 'Fachada — revestimentos')
            ->call('createPackage')
            ->assertHasNoErrors();

        $approval->refresh();

        $this->assertNotNull($approval->package);
        $this->assertSame('Fachada — revestimentos', $approval->package->title);
        $this->assertSame('PKG-0001', $approval->package->number, 'Numbered per project.');
        $this->assertSame($this->project->id, $approval->package->project_id);
    }

    public function test_packages_are_numbered_per_project(): void
    {
        $first = ApprovalPackage::createWithNumber([
            'project_id' => $this->project->id, 'title' => 'One', 'created_by_id' => $this->admin->id,
        ]);
        $second = ApprovalPackage::createWithNumber([
            'project_id' => $this->project->id, 'title' => 'Two', 'created_by_id' => $this->admin->id,
        ]);

        $this->assertSame('PKG-0001', $first->number);
        $this->assertSame('PKG-0002', $second->number);
    }

    public function test_an_approval_can_be_moved_between_packages_and_taken_out(): void
    {
        $approval = $this->approval();

        $package = ApprovalPackage::createWithNumber([
            'project_id' => $this->project->id, 'title' => 'Bundle', 'created_by_id' => $this->admin->id,
        ]);

        $page = Livewire::actingAs($this->admin)->test(ApprovalShow::class, ['approval' => $approval]);

        $page->set('packageId', (string) $package->id)->call('setPackage')->assertHasNoErrors();
        $this->assertSame($package->id, $approval->fresh()->package_id);

        $page->set('packageId', '')->call('setPackage')->assertHasNoErrors();
        $this->assertNull($approval->fresh()->package_id);
    }

    public function test_a_package_from_another_project_is_refused(): void
    {
        $other = Project::create([
            'project_name' => 'Not Ours',
            'client_id' => $this->project->client_id,
            'contact_person' => 'C',
            'email' => 'other-pkg@example.test',
            'created_by' => $this->admin->id,
        ]);

        $theirs = ApprovalPackage::createWithNumber([
            'project_id' => $other->id, 'title' => 'Theirs', 'created_by_id' => $this->admin->id,
        ]);

        $approval = $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('packageId', (string) $theirs->id)
            ->call('setPackage')
            ->assertHasErrors('packageId');

        $this->assertNull($approval->fresh()->package_id);
    }

    public function test_a_package_can_be_closed_and_reopened(): void
    {
        $package = ApprovalPackage::createWithNumber([
            'project_id' => $this->project->id, 'title' => 'Bundle', 'created_by_id' => $this->admin->id,
        ]);
        $approval = $this->approval(['package_id' => $package->id]);

        $page = Livewire::actingAs($this->admin)->test(ApprovalShow::class, ['approval' => $approval]);

        $page->call('togglePackageStatus');
        $this->assertSame(ApprovalPackage::CLOSED, $package->fresh()->status);

        $page->call('togglePackageStatus');
        $this->assertSame(ApprovalPackage::OPEN, $package->fresh()->status);
    }

    public function test_managing_packages_needs_the_grant(): void
    {
        $editor = $this->memberOf(['approvals.view', 'approvals.edit']);
        $approval = $this->approval();

        $page = Livewire::actingAs($editor)->test(ApprovalShow::class, ['approval' => $approval]);

        $this->assertFalse($page->instance()->canManagePackages);

        $page->set('newPackageTitle', 'Sneaky')->call('createPackage')->assertForbidden();

        Livewire::actingAs($editor)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('packageId', '1')
            ->call('setPackage')
            ->assertForbidden();

        $this->assertSame(0, ApprovalPackage::count());
    }

    /*
    |---------------------------------------------------------------------------
    | project.archive
    |---------------------------------------------------------------------------
    */

    public function test_closing_a_project_needs_the_archive_grant(): void
    {
        // May edit the project; may not close it down.
        $editor = $this->memberOf(['project.view', 'project.edit']);

        Livewire::actingAs($editor)
            ->test(ProjectEdit::class, ['project' => $this->project])
            ->set('status', ProjectStatus::COMPLETED->value)
            ->call('updateProject')
            ->assertForbidden();

        $this->assertSame(ProjectStatus::IN_PROGRESS, $this->project->fresh()->status);
    }

    public function test_the_archive_grant_closes_the_project(): void
    {
        $closer = $this->memberOf(['project.view', 'project.edit', 'project.archive']);

        Livewire::actingAs($closer)
            ->test(ProjectEdit::class, ['project' => $this->project])
            ->set('status', ProjectStatus::COMPLETED->value)
            ->call('updateProject')
            ->assertHasNoErrors();

        $this->assertSame(ProjectStatus::COMPLETED, $this->project->fresh()->status);
    }

    public function test_ordinary_edits_do_not_ask_for_the_archive_grant(): void
    {
        $editor = $this->memberOf(['project.view', 'project.edit']);

        Livewire::actingAs($editor)
            ->test(ProjectEdit::class, ['project' => $this->project])
            ->set('project_name', 'Obra Central II')
            ->call('updateProject')
            ->assertHasNoErrors();

        $this->assertSame('Obra Central II', $this->project->fresh()->project_name);
    }

    public function test_a_project_already_closed_can_still_be_corrected(): void
    {
        // Re-saving a closed project must not ask again, or nobody could fix a
        // typo on one.
        $this->project->update(['status' => ProjectStatus::COMPLETED]);

        $editor = $this->memberOf(['project.view', 'project.edit']);

        Livewire::actingAs($editor)
            ->test(ProjectEdit::class, ['project' => $this->project])
            ->set('project_name', 'Obra Central (corrigido)')
            ->call('updateProject')
            ->assertHasNoErrors();

        $this->assertSame('Obra Central (corrigido)', $this->project->fresh()->project_name);
    }

    public function test_cancelling_a_project_is_the_same_act_as_completing_it(): void
    {
        $editor = $this->memberOf(['project.view', 'project.edit']);

        Livewire::actingAs($editor)
            ->test(ProjectEdit::class, ['project' => $this->project])
            ->set('status', ProjectStatus::CANCELLED->value)
            ->call('updateProject')
            ->assertForbidden();
    }

    public function test_the_duplicate_global_archive_grant_is_gone(): void
    {
        // Two declarations of one act meant one of them could only ever be a
        // grant that did nothing.
        $this->assertArrayNotHasKey(
            'archive',
            \App\Services\AbilityCatalog::areas()['projects']['actions'],
        );

        $this->assertArrayHasKey(
            'archive',
            \App\Services\AbilityCatalog::areas()['project']['actions'],
        );
    }
}
