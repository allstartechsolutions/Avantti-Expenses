<?php

namespace Tests\Feature\Meetings;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\MeetingSeries;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\MeetingAgendaService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Carrying items forward without losing the structure.
 *
 * The owner's complaint, 26 Aug 2026: *the items moved to the next one are out
 * of the order and not on the same structure as the one before — they are
 * organized by due date first*. Every test here is one sentence of that.
 *
 * See docs/meetings-agenda-order-plan.md.
 */
class AgendaOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MeetingSeries $series;

    protected Project $alpha;

    protected Project $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $this->series = MeetingSeries::create([
            'name' => 'Weekly Site Review',
            'code' => 'OBRA',
            'created_by' => $this->admin->id,
        ]);

        $this->alpha = $this->makeProject('Alpha');
        $this->beta = $this->makeProject('Beta');
    }

    /*
    |---------------------------------------------------------------------------
    | The order
    |---------------------------------------------------------------------------
    */

    public function test_carried_items_keep_last_meetings_order_within_their_project(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        // Deliberately not in due-date order: this is the order a chair dragged
        // the agenda into, and it is what has to come back.
        $late = $this->makeTask($this->alpha, 'Rebar', '2026-09-30');
        $early = $this->makeTask($this->alpha, 'Drainage', '2026-08-01');

        $this->putOnAgenda($first, [$late, $early]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        $this->assertSame(['Rebar', 'Drainage'], $this->titles($second));
    }

    public function test_projects_stay_together_instead_of_interleaving_by_due_date(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        $alphaOne = $this->makeTask($this->alpha, 'Alpha one', '2026-09-01');
        $betaOne = $this->makeTask($this->beta, 'Beta one', '2026-08-05');
        $alphaTwo = $this->makeTask($this->alpha, 'Alpha two', '2026-08-02');
        $betaTwo = $this->makeTask($this->beta, 'Beta two', '2026-09-05');

        $this->putOnAgenda($first, [$alphaOne, $betaOne, $alphaTwo, $betaTwo]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        // By due date alone this would read Alpha two, Beta one, Alpha one,
        // Beta two — the complaint, exactly.
        $this->assertSame(
            ['Alpha one', 'Alpha two', 'Beta one', 'Beta two'],
            $this->titles($second)
        );
    }

    public function test_a_job_site_is_a_block_of_its_own(): void
    {
        $site = $this->makeSite($this->alpha, 'North Wing');

        $first = $this->makeMeeting('2026-08-12');

        $project = $this->makeTask($this->alpha, 'Project level', '2026-09-01');
        $onSite = $this->makeTask($this->alpha, 'Site level', '2026-08-01', $site);

        $this->putOnAgenda($first, [$project, $onSite]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        $this->assertSame(['Project level', 'Site level'], $this->titles($second));
    }

    public function test_an_item_skipped_at_the_last_meeting_still_comes_back(): void
    {
        $first = $this->makeMeeting('2026-08-05');
        $skipped = $this->makeTask($this->alpha, 'Skipped', '2026-08-01');
        $this->putOnAgenda($first, [$skipped]);

        $second = $this->makeMeeting('2026-08-12', $first);
        $kept = $this->makeTask($this->alpha, 'Kept', '2026-09-01');
        $this->putOnAgenda($second, [$kept]);

        $third = $this->makeMeeting('2026-08-19', $second);
        $this->carry($third);

        // The template meeting's own row leads; the one it skipped follows.
        $this->assertSame(['Kept', 'Skipped'], $this->titles($third));
    }

    public function test_an_empty_meeting_does_not_blank_the_ordering(): void
    {
        $first = $this->makeMeeting('2026-08-05');
        $one = $this->makeTask($this->alpha, 'One', '2026-09-30');
        $two = $this->makeTask($this->alpha, 'Two', '2026-08-01');
        $this->putOnAgenda($first, [$one, $two]);

        // Created and abandoned without an agenda.
        $this->makeMeeting('2026-08-12', $first);

        $third = $this->makeMeeting('2026-08-19', $first);
        $this->carry($third);

        $this->assertSame(['One', 'Two'], $this->titles($third));
    }

    /*
    |---------------------------------------------------------------------------
    | The structure
    |---------------------------------------------------------------------------
    */

    public function test_a_sub_item_is_re_nested_under_its_carried_parent(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        $parent = $this->makeTask($this->alpha, 'Foundation', '2026-09-01');
        $childOne = $this->makeTask($this->alpha, 'Rebar', '2026-09-10');
        $childTwo = $this->makeTask($this->alpha, 'Inspection', '2026-08-01');

        $parentItem = $this->putOnAgenda($first, [$parent])[0];
        $this->putOnAgenda($first, [$childOne, $childTwo], $parentItem);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        $roots = $second->items()->get();
        $this->assertCount(1, $roots);
        $this->assertSame('Foundation', $roots[0]->title);

        $children = $roots[0]->children()->get();
        $this->assertSame(['Rebar', 'Inspection'], $children->pluck('title')->all());
        $this->assertSame([0, 1], $children->pluck('position')->all());
        $this->assertSame('1.1', $children[0]->number());
    }

    public function test_a_sub_item_is_promoted_when_its_parent_does_not_come_along(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        $parent = $this->makeTask($this->alpha, 'Foundation', '2026-09-01');
        $child = $this->makeTask($this->alpha, 'Rebar', '2026-09-10');
        $after = $this->makeTask($this->alpha, 'Roof', '2026-09-20');

        $parentItem = $this->putOnAgenda($first, [$parent])[0];
        $this->putOnAgenda($first, [$child], $parentItem);
        $this->putOnAgenda($first, [$after]);

        // The parent is closed, so only the child and the row after it carry.
        $parent->update(['status' => 'completed']);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        // Promoted, and it keeps the parent's slot rather than falling to the end.
        $this->assertSame(['Rebar', 'Roof'], $this->titles($second));
        $this->assertNull($second->items()->first()->parent_id);
    }

    /*
    |---------------------------------------------------------------------------
    | Past due first
    |---------------------------------------------------------------------------
    */

    public function test_past_due_lifts_to_the_top_of_its_own_block_when_the_series_asks(): void
    {
        $this->series->update(['agenda_order' => 'overdue_first']);

        $first = $this->makeMeeting('2026-08-12');

        $alphaOnTime = $this->makeTask($this->alpha, 'Alpha on time', now()->addMonth()->toDateString());
        $alphaLate = $this->makeTask($this->alpha, 'Alpha late', now()->subWeek()->toDateString());
        $betaLate = $this->makeTask($this->beta, 'Beta late', now()->subDays(3)->toDateString());
        $betaOnTime = $this->makeTask($this->beta, 'Beta on time', now()->addMonth()->toDateString());

        $this->putOnAgenda($first, [$alphaOnTime, $alphaLate, $betaLate, $betaOnTime]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        // Late first inside each project — the projects are not broken apart.
        $this->assertSame(
            ['Alpha late', 'Alpha on time', 'Beta late', 'Beta on time'],
            $this->titles($second)
        );
    }

    public function test_a_parent_lifts_with_its_children_rather_than_being_left_behind(): void
    {
        $this->series->update(['agenda_order' => 'overdue_first']);

        $first = $this->makeMeeting('2026-08-12');

        $onTime = $this->makeTask($this->alpha, 'On time', now()->addMonth()->toDateString());
        $parent = $this->makeTask($this->alpha, 'Foundation', now()->addMonth()->toDateString());
        $lateChild = $this->makeTask($this->alpha, 'Rebar', now()->subWeek()->toDateString());

        $this->putOnAgenda($first, [$onTime]);
        $parentItem = $this->putOnAgenda($first, [$parent])[0];
        $this->putOnAgenda($first, [$lateChild], $parentItem);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        // The parent is not itself late, but its child is, so the whole line
        // lifts — a sub-item must never float away from what it belongs under.
        $this->assertSame(['Foundation', 'On time'], $this->titles($second));
        $this->assertSame(['Rebar'], $second->items()->first()->children()->pluck('title')->all());
    }

    public function test_the_default_series_leaves_past_due_where_last_meeting_had_it(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        $onTime = $this->makeTask($this->alpha, 'On time', now()->addMonth()->toDateString());
        $late = $this->makeTask($this->alpha, 'Late', now()->subWeek()->toDateString());

        $this->putOnAgenda($first, [$onTime, $late]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        $this->assertSame(['On time', 'Late'], $this->titles($second));
    }

    /*
    |---------------------------------------------------------------------------
    | Nothing reaches back into the previous meeting
    |---------------------------------------------------------------------------
    */

    public function test_building_the_new_agenda_leaves_the_previous_meeting_byte_identical(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        $parent = $this->makeTask($this->alpha, 'Foundation', '2026-09-01');
        $child = $this->makeTask($this->alpha, 'Rebar', '2026-09-10');
        $other = $this->makeTask($this->beta, 'Beta one', '2026-08-01');

        $parentItem = $this->putOnAgenda($first, [$parent])[0];
        $this->putOnAgenda($first, [$child], $parentItem);
        $this->putOnAgenda($first, [$other]);

        $before = $this->snapshotOf($first);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        // And a third, so the second meeting's own rows shift as well.
        $extra = $this->makeTask($this->alpha, 'Raised later', '2026-08-03');
        app(MeetingAgendaService::class)->addTask($second, $extra, $this->admin);

        $this->assertSame($before, $this->snapshotOf($first));
    }

    public function test_a_position_shift_never_crosses_into_another_meeting(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $this->putOnAgenda($first, [
            $this->makeTask($this->beta, 'Beta first', '2026-09-01'),
        ]);

        $second = $this->makeMeeting('2026-08-19', $first);

        $agenda = app(MeetingAgendaService::class);
        $agenda->addTask($second, $this->makeTask($this->alpha, 'Alpha', '2026-09-01'), $this->admin);
        $agenda->addTask($second, $this->makeTask($this->beta, 'Beta', '2026-09-01'), $this->admin);

        // Inserting into Alpha's block shifts Beta's row on this meeting only.
        $agenda->addTask($second, $this->makeTask($this->alpha, 'Alpha again', '2026-09-01'), $this->admin);

        $this->assertSame(['Alpha', 'Alpha again', 'Beta'], $this->titles($second));
        $this->assertSame([0], $first->items()->pluck('position')->all());
    }

    /*
    |---------------------------------------------------------------------------
    | Moving things about
    |---------------------------------------------------------------------------
    */

    public function test_a_row_stops_at_the_edge_of_its_own_project(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $items = $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-02'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-03'),
        ]);

        $agenda = app(MeetingAgendaService::class);

        // The first row of Beta's block cannot walk into Alpha's.
        $this->assertFalse($agenda->canMove($items[2], 'up'));
        $agenda->move($items[2], 'up');
        $this->assertSame(['Alpha one', 'Alpha two', 'Beta one'], $this->titles($meeting));

        // Nor can Alpha's last row walk down into Beta's.
        $this->assertFalse($agenda->canMove($items[1], 'down'));
        $agenda->move($items[1], 'down');
        $this->assertSame(['Alpha one', 'Alpha two', 'Beta one'], $this->titles($meeting));

        // Inside the block it moves as it always did.
        $this->assertTrue($agenda->canMove($items[1], 'up'));
        $agenda->move($items[1], 'up');
        $this->assertSame(['Alpha two', 'Alpha one', 'Beta one'], $this->titles($meeting));
    }

    public function test_a_whole_location_moves_above_its_neighbour(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-02'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-03'),
            $this->makeTask($this->beta, 'Beta two', '2026-09-04'),
        ]);

        app(MeetingAgendaService::class)->moveGroup($meeting, $this->beta->id, null, 'up');

        $this->assertSame(
            ['Beta one', 'Beta two', 'Alpha one', 'Alpha two'],
            $this->titles($meeting)
        );
        $this->assertSame([0, 1, 2, 3], $meeting->items()->pluck('position')->all());
    }

    public function test_moving_the_first_or_last_location_does_nothing(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-03'),
        ]);

        $agenda = app(MeetingAgendaService::class);
        $agenda->moveGroup($meeting, $this->alpha->id, null, 'up');
        $agenda->moveGroup($meeting, $this->beta->id, null, 'down');

        $this->assertSame(['Alpha one', 'Beta one'], $this->titles($meeting));
    }

    public function test_a_drag_rewrites_only_the_slots_its_own_block_occupies(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $items = $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-02'),
            $this->makeTask($this->alpha, 'Alpha three', '2026-09-03'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-04'),
        ]);

        // Alpha's rows dragged into a new order; Beta was not part of the drag.
        app(MeetingAgendaService::class)->reorder(
            $meeting,
            [$items[2]->id, $items[0]->id, $items[1]->id]
        );

        $this->assertSame(
            ['Alpha three', 'Alpha one', 'Alpha two', 'Beta one'],
            $this->titles($meeting)
        );
        $this->assertSame(3, $items[3]->fresh()->position);
    }

    public function test_a_drag_refuses_ids_from_another_block(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $items = $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-02'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-03'),
        ]);

        // A stale or tampered page asking for Beta to be interleaved with Alpha.
        app(MeetingAgendaService::class)->reorder(
            $meeting,
            [$items[1]->id, $items[2]->id, $items[0]->id]
        );

        // Beta is ignored; only Alpha's own two slots are rewritten.
        $this->assertSame(['Alpha two', 'Alpha one', 'Beta one'], $this->titles($meeting));
    }

    public function test_a_drag_cannot_reach_another_meetings_rows(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $firstItems = $this->putOnAgenda($first, [
            $this->makeTask($this->alpha, 'Theirs one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Theirs two', '2026-09-02'),
        ]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $items = $this->putOnAgenda($second, [
            $this->makeTask($this->alpha, 'Ours one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Ours two', '2026-09-02'),
        ]);

        $before = $this->snapshotOf($first);

        app(MeetingAgendaService::class)->reorder(
            $second,
            [$firstItems[1]->id, $items[1]->id, $items[0]->id, $firstItems[0]->id]
        );

        $this->assertSame(['Ours two', 'Ours one'], $this->titles($second));
        $this->assertSame($before, $this->snapshotOf($first));
    }

    /*
    |---------------------------------------------------------------------------
    | Tidying up
    |---------------------------------------------------------------------------
    */

    public function test_tidying_brings_a_location_together_without_reordering_it(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-02'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-03'),
            $this->makeTask($this->beta, 'Beta two', '2026-09-04'),
        ]);

        app(MeetingAgendaService::class)->regroup($meeting);

        // Grouped, and inside each group the chair's own order is untouched.
        $this->assertSame(
            ['Alpha one', 'Alpha two', 'Beta one', 'Beta two'],
            $this->titles($meeting)
        );
    }

    public function test_sorting_past_due_first_leaves_lines_raised_today_at_the_end(): void
    {
        $first = $this->makeMeeting('2026-08-12');

        $late = $this->makeTask($this->alpha, 'Late', now()->subWeek()->toDateString());
        $onTime = $this->makeTask($this->alpha, 'On time', now()->addMonth()->toDateString());
        $this->putOnAgenda($first, [$onTime, $late]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->carry($second);

        // Something raised at this meeting, with no previous position at all.
        app(MeetingAgendaService::class)->addItem($second, [
            'project_id' => $this->alpha->id,
            'type' => 'information',
            'title' => 'Raised today',
        ], $this->admin);

        app(MeetingAgendaService::class)->applyOrder($second, 'overdue_first');

        $this->assertSame(['Late', 'On time', 'Raised today'], $this->titles($second));

        app(MeetingAgendaService::class)->applyOrder($second, 'last_meeting');

        $this->assertSame(['On time', 'Late', 'Raised today'], $this->titles($second));
    }

    public function test_none_of_the_group_actions_touch_a_published_minute(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');
        $this->putOnAgenda($meeting, [$this->makeTask($this->alpha, 'One', '2026-09-01')]);
        $meeting->update(['status' => 'published']);

        $agenda = app(MeetingAgendaService::class);

        foreach ([
            fn () => $agenda->regroup($meeting),
            fn () => $agenda->applyOrder($meeting, 'overdue_first'),
            fn () => $agenda->moveGroup($meeting, $this->alpha->id, null, 'up'),
            fn () => $agenda->reorder($meeting, []),
        ] as $action) {
            try {
                $action();
                $this->fail('A published minute accepted a change to its agenda.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_a_line_cannot_be_hung_off_another_meetings_item(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $theirs = $this->putOnAgenda($first, [$this->makeTask($this->alpha, 'Theirs', '2026-09-01')])[0];

        $second = $this->makeMeeting('2026-08-19', $first);

        // An id from the browser proves the row exists, not that it is ours.
        try {
            app(MeetingAgendaService::class)->addItem($second, [
                'parent_id' => $theirs->id,
                'project_id' => $this->alpha->id,
                'type' => 'information',
                'title' => 'Smuggled in',
            ], $this->admin);

            $this->fail('A line was hung off another meeting\'s item.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $this->assertCount(0, $theirs->children()->get());
    }

    public function test_a_third_level_cannot_be_built(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $agenda = app(MeetingAgendaService::class);
        $root = $this->putOnAgenda($meeting, [$this->makeTask($this->alpha, 'Root', '2026-09-01')])[0];
        $child = $this->putOnAgenda($meeting, [$this->makeTask($this->alpha, 'Child', '2026-09-02')], $root)[0];

        try {
            $agenda->addItem($meeting, [
                'parent_id' => $child->id,
                'project_id' => $this->alpha->id,
                'type' => 'information',
                'title' => 'Third level',
            ], $this->admin);

            $this->fail('A third level was built under a sub-item.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /*
    |---------------------------------------------------------------------------
    | The screen
    |---------------------------------------------------------------------------
    */

    public function test_the_builder_renders_one_heading_per_location(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-02'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-03'),
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $meeting])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Alpha one')
            ->assertSee('Beta one')
            ->assertSet('meeting.id', $meeting->id);
    }

    public function test_the_builder_moves_a_location_and_tidies_an_interleaved_agenda(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-02'),
            $this->makeTask($this->alpha, 'Alpha two', '2026-09-03'),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $meeting]);

        // Alpha is split in two, so the tidy button is offered.
        $this->assertTrue($component->instance()->isInterleaved);

        $component->call('tidyAgenda');
        $this->assertSame(['Alpha one', 'Alpha two', 'Beta one'], $this->titles($meeting));

        $component->call('moveGroup', $this->beta->id, null, 'up');
        $this->assertSame(['Beta one', 'Alpha one', 'Alpha two'], $this->titles($meeting));

        $component->call('sortAgenda', 'overdue_first');
    }

    public function test_the_builder_refuses_a_line_from_another_meeting(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $theirs = $this->putOnAgenda($first, [$this->makeTask($this->alpha, 'Theirs', '2026-09-01')])[0];

        $second = $this->makeMeeting('2026-08-19', $first);

        // Not found *for this meeting* — a 404 in a real request. The point is
        // that the other meeting's line is never reached, let alone removed.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            Livewire::actingAs($this->admin)
                ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $second])
                ->call('removeItem', $theirs->id);
        } finally {
            $this->assertNotNull($theirs->fresh());
        }
    }

    public function test_the_builder_refuses_a_sort_mode_it_does_not_know(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $meeting])
            ->call('sortAgenda', 'whatever')
            ->assertStatus(400);
    }

    /*
    |---------------------------------------------------------------------------
    | The minute and the ata
    |---------------------------------------------------------------------------
    */

    public function test_the_running_minute_carries_the_same_headings(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $this->putOnAgenda($meeting, [
            $this->makeTask($this->alpha, 'Alpha one', '2026-09-01'),
            $this->makeTask($this->beta, 'Beta one', '2026-09-02'),
        ]);

        $blocks = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingShow::class, ['meeting' => $meeting])
            ->instance()->itemBlocks;

        $this->assertSame(['Alpha', 'Beta'], $blocks->pluck('label')->all());
        $this->assertSame([1, 1], $blocks->pluck('items')->map->count()->all());
    }

    public function test_the_ata_renders_grouped_with_children_in_reading_order(): void
    {
        $meeting = $this->makeMeeting('2026-08-19');

        $root = $this->putOnAgenda($meeting, [$this->makeTask($this->alpha, 'Foundation', '2026-09-01')])[0];
        $this->putOnAgenda($meeting, [$this->makeTask($this->alpha, 'Rebar', '2026-09-02')], $root);
        $this->putOnAgenda($meeting, [$this->makeTask($this->beta, 'Beta one', '2026-09-03')]);

        $html = app(\App\Services\MeetingMinuteRenderer::class)->html($meeting);

        // Both headings are there, and the parent's child travels with it.
        $this->assertStringContainsString('Alpha', $html);
        $this->assertStringContainsString('Beta', $html);
        $this->assertStringContainsString('Foundation', $html);
        $this->assertStringContainsString('Rebar', $html);

        $this->assertTrue(
            strpos($html, 'Rebar') < strpos($html, 'Beta one'),
            "A sub-item must stay under its parent's heading, not drift past the next location."
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Saying which figures the record holds
    |---------------------------------------------------------------------------
    */

    public function test_an_unpublished_earlier_minute_is_flagged_on_the_builder(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $this->putOnAgenda($first, [$this->makeTask($this->alpha, 'One', '2026-09-01')]);

        $second = $this->makeMeeting('2026-08-19', $first);

        $component = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $second]);

        $this->assertSame([$first->id], $component->instance()->unpublishedEarlier->pluck('id')->all());
        $component->assertSee($first->number);

        // Once it has gone out, its figures are frozen and there is nothing to say.
        $first->update(['status' => 'published', 'published_at' => now()]);

        $this->assertCount(0, Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $second])
            ->instance()->unpublishedEarlier);
    }

    public function test_publishing_out_of_order_is_warned_about(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $this->putOnAgenda($first, [$this->makeTask($this->alpha, 'One', '2026-09-01')]);

        $second = $this->makeMeeting('2026-08-19', $first);
        $this->putOnAgenda($second, [$this->makeTask($this->alpha, 'Two', '2026-09-02')]);

        // The later meeting is already prepared, so the earlier one is late.
        $this->assertSame([$second->id], Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingShow::class, ['meeting' => $first])
            ->instance()->laterMeetings->pluck('id')->all());

        // The later meeting itself has nothing after it.
        $this->assertCount(0, Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingShow::class, ['meeting' => $second])
            ->instance()->laterMeetings);
    }

    public function test_the_ata_says_when_its_figures_were_taken(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        $this->putOnAgenda($meeting, [$this->makeTask($this->alpha, 'One', '2026-09-01')]);

        // Written up a fortnight late.
        $meeting->update([
            'status' => 'published',
            'published_at' => \Illuminate\Support\Carbon::parse('2026-08-26 10:00'),
        ]);

        $html = app(\App\Services\MeetingMinuteRenderer::class)->html($meeting->fresh());
        $this->assertStringContainsString('Figures as at publication', $html);

        // Published on the day, and there is nothing to explain.
        $meeting->update(['published_at' => \Illuminate\Support\Carbon::parse('2026-08-12 18:00')]);

        $this->assertStringNotContainsString(
            'Figures as at publication',
            app(\App\Services\MeetingMinuteRenderer::class)->html($meeting->fresh())
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function carry(Meeting $meeting): int
    {
        $agenda = app(MeetingAgendaService::class);

        return $agenda->carryForward($meeting, $agenda->carryForwardCandidates($meeting), $this->admin);
    }

    /** @return array<int, string> */
    protected function titles(Meeting $meeting): array
    {
        return $meeting->items()->get()->pluck('title')->all();
    }

    /** Everything the previous meeting's rows are made of, in a comparable shape. */
    protected function snapshotOf(Meeting $meeting): array
    {
        return MeetingItem::where('meeting_id', $meeting->id)
            ->orderBy('id')
            ->get(['id', 'parent_id', 'position', 'project_id', 'job_site_id', 'type', 'title', 'task_id', 'status_at_meeting'])
            ->map->toArray()
            ->all();
    }

    /**
     * Put rows on an agenda directly, in the order given — this is the chair
     * having dragged them there, not the code under test.
     *
     * @return array<int, MeetingItem>
     */
    protected function putOnAgenda(Meeting $meeting, array $tasks, ?MeetingItem $parent = null): array
    {
        $start = MeetingItem::where('meeting_id', $meeting->id)
            ->where('parent_id', $parent?->id)
            ->count();

        $items = [];

        foreach (array_values($tasks) as $offset => $task) {
            $items[] = MeetingItem::create([
                'meeting_id' => $meeting->id,
                'parent_id' => $parent?->id,
                'position' => $start + $offset,
                'project_id' => $task->project_id,
                'job_site_id' => $task->job_site_id,
                'type' => 'action',
                'title' => $task->title,
                'task_id' => $task->id,
                'created_by' => $this->admin->id,
            ]);
        }

        return $items;
    }

    protected function makeMeeting(string $date, ?Meeting $previous = null): Meeting
    {
        return Meeting::create([
            'meeting_series_id' => $this->series->id,
            'number' => 'OBRA-'.str_replace('-', '', $date),
            'title' => 'Site review '.$date,
            'meeting_date' => $date,
            'previous_meeting_id' => $previous?->id,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeTask(Project $project, string $title, string $due, ?JobSite $site = null): Task
    {
        return Task::create([
            'uuid' => (string) str()->uuid(),
            'title' => $title,
            'project_id' => $project->id,
            'job_site_id' => $site?->id,
            'owner_id' => $this->admin->id,
            'priority' => 'normal',
            'status' => 'open',
            'progress' => 0,
            'due_date' => $due,
        ]);
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Agenda Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-ag@example.test',
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
            'email' => str($name)->slug().'-ag@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }
}
