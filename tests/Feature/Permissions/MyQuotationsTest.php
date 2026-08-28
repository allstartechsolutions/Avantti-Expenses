<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Quotation\MyQuotations;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 6 of docs/procurement-assignment-plan.md: the queue.
 *
 * "The three groups list correctly under `visibleTo()`." The page has no
 * project of its own, so there is no route to guard — a guard answers "may
 * you open this record?", only a filter answers "which records may you see?",
 * and a cross-project queue that forgot to filter is a leak by aggregate.
 */
class MyQuotationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
        $this->site = $this->makeSite($this->project, 'Site A');
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Queue Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-queue@example.test',
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
            'email' => str($name)->slug().'-queue@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function memberOf(Project|JobSite $scope, array $abilities, string $name = 'Member'): User
    {
        $user = $this->user('employee', [
            'name' => $name,
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** Somebody confined to one project who buys there. */
    protected function buyer(Project|JobSite $scope, string $name = 'The Buyer', array $extra = []): User
    {
        return $this->memberOf($scope, array_merge([
            'requisitions.view', 'quotations.view', 'quotations.create', 'quotations.edit',
        ], $extra), $name);
    }

    protected function makeRequisition(array $attributes = [], ?Project $project = null): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::createWithNumber(array_merge([
            'project_id' => ($project ?? $this->project)->id,
            'type' => 'material',
            'title' => 'Cement and rebar',
            'priority' => 'normal',
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
            'created_by' => $this->admin->id,
        ], $attributes));

        $requisition->items()->create([
            'item_name' => 'Cement',
            'item_type' => 'custom',
            'quantity' => 20,
            'unit' => 'bag',
            'sort_order' => 0,
        ]);

        return $requisition;
    }

    protected function makeQuotation(array $attributes = [], ?Project $project = null): Quotation
    {
        return Quotation::createWithNumber(array_merge([
            'project_id' => ($project ?? $this->project)->id,
            'type' => 'material',
            'title' => 'Cement round',
            'status' => 'sent',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Reaching the page
    |---------------------------------------------------------------------------
    */

    public function test_the_queue_needs_the_view_grant(): void
    {
        $this->actingAs($this->admin)->get(route('quotations.mine'))->assertOk();

        $blind = $this->user('employee');
        $blind->abilityOverrides()->create(['ability' => 'quotations.view', 'granted' => false]);

        $this->actingAs($blind)->get(route('quotations.mine'))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Group 1 — to start
    |---------------------------------------------------------------------------
    */

    public function test_to_start_lists_approved_requisitions_handed_to_me_with_no_round(): void
    {
        $buyer = $this->buyer($this->project);
        $other = $this->buyer($this->project, 'Somebody Else');

        $this->makeRequisition(['title' => 'Mine to quote', 'assigned_buyer_id' => $buyer->id, 'assigned_at' => now()]);
        $this->makeRequisition(['title' => 'Theirs to quote', 'assigned_buyer_id' => $other->id, 'assigned_at' => now()]);
        $this->makeRequisition(['title' => 'Nobody has this']);

        Livewire::actingAs($buyer)
            ->test(MyQuotations::class)
            ->assertSee('Mine to quote')
            ->assertDontSee('Theirs to quote')
            ->assertDontSee('Nobody has this');
    }

    public function test_a_requisition_that_already_has_a_round_drops_off_the_queue(): void
    {
        $buyer = $this->buyer($this->project);

        $done = $this->makeRequisition([
            'title' => 'Already quoted',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        $this->makeQuotation(['purchase_requisition_id' => $done->id, 'status' => 'sent']);

        Livewire::actingAs($buyer)
            ->test(MyQuotations::class)
            ->assertDontSee('Already quoted')
            ->assertSet('tab', 'to_start');
    }

    public function test_a_cancelled_round_does_not_count_as_started(): void
    {
        $buyer = $this->buyer($this->project);

        $requisition = $this->makeRequisition([
            'title' => 'Round was cancelled',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        $this->makeQuotation(['purchase_requisition_id' => $requisition->id, 'status' => 'cancelled']);

        Livewire::actingAs($buyer)
            ->test(MyQuotations::class)
            ->assertSee('Round was cancelled');
    }

    public function test_an_unapproved_requisition_is_not_on_the_queue(): void
    {
        $buyer = $this->buyer($this->project);

        $this->makeRequisition([
            'title' => 'Only a suggestion',
            'status' => 'draft',
            'assigned_buyer_id' => $buyer->id,
        ]);

        Livewire::actingAs($buyer)
            ->test(MyQuotations::class)
            ->assertDontSee('Only a suggestion');
    }

    /*
    |---------------------------------------------------------------------------
    | Group 2 — in progress
    |---------------------------------------------------------------------------
    */

    public function test_in_progress_covers_both_owning_and_collaborating(): void
    {
        $me = $this->buyer($this->project, 'Me');
        $other = $this->buyer($this->project, 'Other');

        $this->makeQuotation(['title' => 'Round I own', 'assigned_to' => $me->id, 'assigned_at' => now()]);
        $helping = $this->makeQuotation(['title' => 'Round I help on', 'assigned_to' => $other->id, 'assigned_at' => now()]);
        $this->makeQuotation(['title' => 'Round of theirs', 'assigned_to' => $other->id, 'assigned_at' => now()]);

        $helping->assignees()->attach($me->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        Livewire::actingAs($me)
            ->test(MyQuotations::class)
            ->call('setTab', 'in_progress')
            ->assertSee('Round I own')
            ->assertSee('Round I help on')
            ->assertDontSee('Round of theirs');
    }

    public function test_an_awarded_round_is_no_longer_in_progress(): void
    {
        $me = $this->buyer($this->project, 'Me');

        $this->makeQuotation(['title' => 'Still running', 'assigned_to' => $me->id, 'assigned_at' => now()]);
        $this->makeQuotation(['title' => 'Finished round', 'status' => 'awarded', 'assigned_to' => $me->id, 'assigned_at' => now()]);

        Livewire::actingAs($me)
            ->test(MyQuotations::class)
            ->call('setTab', 'in_progress')
            ->assertSee('Still running')
            ->assertDontSee('Finished round');
    }

    /*
    |---------------------------------------------------------------------------
    | Group 3 — the unassigned bucket
    |---------------------------------------------------------------------------
    */

    public function test_the_unassigned_bucket_needs_the_assign_grant(): void
    {
        $plainBuyer = $this->buyer($this->project, 'Plain Buyer');
        $assigner = $this->buyer($this->project, 'The Assigner', ['requisitions.assign']);

        $this->assertFalse(
            Livewire::actingAs($plainBuyer)->test(MyQuotations::class)->instance()->canSeeUnassigned()
        );
        $this->assertTrue(
            Livewire::actingAs($assigner)->test(MyQuotations::class)->instance()->canSeeUnassigned()
        );
    }

    public function test_the_bucket_lists_approved_requisitions_nobody_has(): void
    {
        $assigner = $this->buyer($this->project, 'The Assigner', ['requisitions.assign']);
        $someone = $this->buyer($this->project, 'Someone');

        $this->makeRequisition(['title' => 'Orphan requisition']);
        $this->makeRequisition(['title' => 'Taken requisition', 'assigned_buyer_id' => $someone->id, 'assigned_at' => now()]);

        Livewire::actingAs($assigner)
            ->test(MyQuotations::class)
            ->call('setTab', 'unassigned')
            ->assertSet('tab', 'unassigned')
            ->assertSee('Orphan requisition')
            ->assertDontSee('Taken requisition');
    }

    public function test_somebody_without_the_grant_cannot_switch_to_the_bucket(): void
    {
        $plainBuyer = $this->buyer($this->project, 'Plain Buyer');

        $this->makeRequisition(['title' => 'Orphan requisition']);

        Livewire::actingAs($plainBuyer)
            ->test(MyQuotations::class)
            ->call('setTab', 'unassigned')
            ->assertSet('tab', 'to_start', 'Hiding the tab is not protection; the endpoint behind it has to refuse too.')
            ->assertDontSee('Orphan requisition');
    }

    public function test_the_bucket_cannot_be_reached_through_the_query_string_either(): void
    {
        $plainBuyer = $this->buyer($this->project, 'Plain Buyer');

        $this->makeRequisition(['title' => 'Orphan requisition']);

        Livewire::actingAs($plainBuyer)
            ->withQueryParams(['tab' => 'unassigned'])
            ->test(MyQuotations::class)
            ->assertSet('tab', 'to_start')
            ->assertDontSee('Orphan requisition');
    }

    /*
    |---------------------------------------------------------------------------
    | The filter, which is the whole point
    |---------------------------------------------------------------------------
    */

    public function test_a_confined_person_never_sees_work_on_a_project_they_cannot_open(): void
    {
        $elsewhere = $this->makeProject('Not Ours');
        $buyer = $this->buyer($this->project, 'The Buyer', ['requisitions.assign']);

        // Assigned to them, but on a project they hold nothing on. This is the
        // leak the filter exists to stop.
        $this->makeRequisition([
            'title' => 'Elsewhere requisition',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ], $elsewhere);

        $this->makeQuotation([
            'title' => 'Elsewhere round',
            'assigned_to' => $buyer->id,
            'assigned_at' => now(),
        ], $elsewhere);

        $this->makeRequisition(['title' => 'Elsewhere orphan'], $elsewhere);

        $this->makeRequisition([
            'title' => 'Here requisition',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        $page = Livewire::actingAs($buyer)->test(MyQuotations::class);

        $page->assertSee('Here requisition')->assertDontSee('Elsewhere requisition');

        $page->call('setTab', 'in_progress')->assertDontSee('Elsewhere round');

        $page->call('setTab', 'unassigned')->assertDontSee('Elsewhere orphan');
    }

    public function test_the_totals_do_not_count_what_the_person_cannot_see(): void
    {
        $elsewhere = $this->makeProject('Not Ours');
        $buyer = $this->buyer($this->project, 'The Buyer');

        $this->makeRequisition([
            'title' => 'Elsewhere requisition',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ], $elsewhere);

        $this->makeRequisition([
            'title' => 'Here requisition',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        $stats = Livewire::actingAs($buyer)->test(MyQuotations::class)->instance()->stats();

        $this->assertSame(
            1,
            $stats['to_start'],
            'A total across projects somebody cannot open is a leak by aggregate.'
        );
    }

    public function test_a_job_site_member_sees_that_sites_work(): void
    {
        $siteBuyer = $this->buyer($this->site, 'Site Buyer');

        $this->makeRequisition([
            'title' => 'Site requisition',
            'job_site_id' => $this->site->id,
            'assigned_buyer_id' => $siteBuyer->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($siteBuyer)
            ->test(MyQuotations::class)
            ->assertSee('Site requisition');
    }

    /*
    |---------------------------------------------------------------------------
    | The figures and the badge
    |---------------------------------------------------------------------------
    */

    public function test_the_totals_report_the_queue_and_what_is_being_chased(): void
    {
        $buyer = $this->buyer($this->project);

        $this->makeRequisition(['title' => 'Fresh', 'assigned_buyer_id' => $buyer->id, 'assigned_at' => now()]);
        $this->makeRequisition(['title' => 'Stale', 'assigned_buyer_id' => $buyer->id, 'assigned_at' => now()->subDays(9)]);

        $this->makeQuotation([
            'title' => 'Late round',
            'assigned_to' => $buyer->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->subDays(2),
        ]);

        $stats = Livewire::actingAs($buyer)->test(MyQuotations::class)->instance()->stats();

        $this->assertSame(2, $stats['to_start']);
        $this->assertSame(1, $stats['waiting_a_week']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(1, $stats['responses_overdue']);
    }

    public function test_the_nav_badge_counts_only_work_not_yet_started(): void
    {
        $buyer = $this->buyer($this->project);

        $this->makeRequisition(['assigned_buyer_id' => $buyer->id, 'assigned_at' => now()]);

        $started = $this->makeRequisition(['assigned_buyer_id' => $buyer->id, 'assigned_at' => now()]);
        $this->makeQuotation(['purchase_requisition_id' => $started->id, 'status' => 'sent']);

        $this->assertSame(1, MyQuotations::navBadge($buyer));
    }

    public function test_the_badge_respects_the_filter_and_the_grant(): void
    {
        $elsewhere = $this->makeProject('Not Ours');
        $buyer = $this->buyer($this->project);

        $this->makeRequisition(['assigned_buyer_id' => $buyer->id, 'assigned_at' => now()], $elsewhere);

        $this->assertSame(0, MyQuotations::navBadge($buyer), 'Never a count of what they cannot open.');

        $blind = $this->user('employee');
        $blind->abilityOverrides()->create(['ability' => 'quotations.view', 'granted' => false]);

        $this->assertSame(0, MyQuotations::navBadge($blind));
    }

    public function test_the_menu_entry_carries_the_badge(): void
    {
        $buyer = $this->buyer($this->project);

        $entries = collect(app(Navigation::class)->sidebar($buyer))
            ->flatMap(fn (array $entry) => $entry['type'] === 'group' ? $entry['items'] : [$entry]);

        $entry = $entries->firstWhere('key', 'my-quotations');

        $this->assertNotNull($entry, 'The queue has to be reachable from the menu.');
        $this->assertNull($entry['badge'], 'Nothing waiting means no badge — a badge reading 0 is worse than none.');

        $this->makeRequisition(['assigned_buyer_id' => $buyer->id, 'assigned_at' => now()]);

        $entries = collect(app(Navigation::class)->sidebar($buyer))
            ->flatMap(fn (array $entry) => $entry['type'] === 'group' ? $entry['items'] : [$entry]);

        $this->assertSame(1, $entries->firstWhere('key', 'my-quotations')['badge']);
    }

    public function test_the_menu_entry_is_hidden_from_somebody_without_the_grant(): void
    {
        $blind = $this->user('employee');
        $blind->abilityOverrides()->create(['ability' => 'quotations.view', 'granted' => false]);

        $entries = collect(app(Navigation::class)->sidebar($blind))
            ->flatMap(fn (array $entry) => $entry['type'] === 'group' ? $entry['items'] : [$entry]);

        $this->assertNull($entries->firstWhere('key', 'my-quotations'));
    }

    /*
    |---------------------------------------------------------------------------
    | The screen itself
    |---------------------------------------------------------------------------
    */

    public function test_the_page_renders_with_its_three_groups(): void
    {
        $assigner = $this->buyer($this->project, 'The Assigner', ['requisitions.assign']);

        $this->actingAs($assigner)
            ->get(route('quotations.mine'))
            ->assertOk()
            ->assertSee(__('My Quotations'))
            ->assertSee(__('To start'))
            ->assertSee(__('In progress'))
            ->assertSee(__('Unassigned'));
    }

    public function test_the_bucket_tab_is_not_rendered_without_the_grant(): void
    {
        $plainBuyer = $this->buyer($this->project, 'Plain Buyer');

        $this->actingAs($plainBuyer)
            ->get(route('quotations.mine'))
            ->assertOk()
            ->assertSee(__('To start'))
            ->assertDontSee(__('Unassigned'));
    }

    public function test_the_empty_states_say_what_to_expect(): void
    {
        $buyer = $this->buyer($this->project);

        Livewire::actingAs($buyer)
            ->test(MyQuotations::class)
            ->assertSee(__('Nothing is waiting on you.'))
            ->call('setTab', 'in_progress')
            ->assertSee(__('You are not running any rounds at the moment.'));
    }

    public function test_the_search_and_project_filter_narrow_the_list(): void
    {
        $buyer = $this->buyer($this->project);
        $second = $this->makeProject('Second');

        Membership::create([
            'user_id' => $buyer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $second->id,
            'status' => MembershipStatus::ACTIVE,
        ])->syncAbilities(['project.view', 'requisitions.view', 'quotations.view', 'quotations.create']);

        app(PermissionResolver::class)->flush();

        $this->makeRequisition(['title' => 'Steel here', 'assigned_buyer_id' => $buyer->id, 'assigned_at' => now()]);
        $this->makeRequisition(['title' => 'Cement there', 'assigned_buyer_id' => $buyer->id, 'assigned_at' => now()], $second);

        $page = Livewire::actingAs($buyer)->test(MyQuotations::class);

        $page->set('search', 'Steel')->assertSee('Steel here')->assertDontSee('Cement there');

        $page->set('search', '')
            ->set('projectFilter', (string) $second->id)
            ->assertSee('Cement there')
            ->assertDontSee('Steel here');
    }
}
