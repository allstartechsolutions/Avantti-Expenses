<?php

namespace Tests\Feature\Meetings;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\MeetingSeries;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\MeetingAgendaService;
use App\Services\MeetingService;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Getting rid of a meeting, and of an agenda built wrongly.
 *
 * The module had neither: `meetings.delete` was declared and grantable but only
 * ever guarded deleting an empty *series*, and an agenda could only be emptied
 * one line at a time.
 *
 * The line that matters most here is the one that refuses: a **published**
 * minute has been frozen, filed and mailed, so it is corrected or its meeting
 * is cancelled — never removed.
 */
class MeetingDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MeetingSeries $series;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $this->series = MeetingSeries::create([
            'name' => 'Weekly', 'code' => 'OBRA', 'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Alpha',
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Del Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => 'alpha-del@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | What may not be deleted
    |---------------------------------------------------------------------------
    */

    public function test_a_published_minute_cannot_be_deleted(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        $this->addLine($meeting, $this->makeTask('One'));
        $meeting->update(['status' => 'published', 'published_at' => now()]);

        $this->assertFalse($meeting->canDelete($this->admin));

        try {
            app(MeetingService::class)->delete($meeting, $this->admin);
            $this->fail('A published minute was deleted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('published minute cannot be deleted', $e->getMessage());
        }

        $this->assertNotNull($meeting->fresh());
        $this->assertSame(1, MeetingItem::where('meeting_id', $meeting->id)->count());
    }

    public function test_the_button_is_not_offered_on_a_published_minute(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        $meeting->update(['status' => 'published', 'published_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingShow::class, ['meeting' => $meeting])
            ->assertSee('Correct the Record')
            ->assertDontSee('Delete :number', false);
    }

    public function test_deleting_needs_the_grant(): void
    {
        $role = Role::create(['name' => 'chair-'.uniqid()]);
        $role->syncAbilities(['meetings.view', 'meetings.edit']);
        $user = User::factory()->create(['role_id' => $role->id]);
        app(PermissionResolver::class)->flush();

        $meeting = $this->makeMeeting('2026-08-12');

        // They may edit it and cancel it, but not delete it.
        $this->assertTrue($meeting->canEdit($user));
        $this->assertTrue($meeting->canCancel($user));
        $this->assertFalse($meeting->canDelete($user));

        $this->expectException(RuntimeException::class);
        app(MeetingService::class)->delete($meeting, $user);
    }

    /*
    |---------------------------------------------------------------------------
    | What happens when one is
    |---------------------------------------------------------------------------
    */

    public function test_a_draft_goes_and_its_tasks_stay_open(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        $task = $this->makeTask('Rebar');
        $task->update(['origin_meeting_id' => $meeting->id]);
        $this->addLine($meeting, $task);

        app(MeetingService::class)->delete($meeting, $this->admin);

        // Soft, so the number is never reissued; its lines go outright.
        $this->assertNull(Meeting::find($meeting->id));
        $this->assertNotNull(Meeting::withTrashed()->find($meeting->id));
        $this->assertSame(0, MeetingItem::where('meeting_id', $meeting->id)->count());

        // The work outlives the meeting that raised it.
        $task->refresh();
        $this->assertSame('open', $task->status);
        $this->assertNull($task->origin_meeting_id);
    }

    public function test_a_cancelled_meeting_can_also_be_deleted(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        app(MeetingService::class)->cancel($meeting, $this->admin, 'Nobody could attend.');

        $this->assertTrue($meeting->fresh()->canDelete($this->admin));

        app(MeetingService::class)->delete($meeting->fresh(), $this->admin);
        $this->assertNull(Meeting::find($meeting->id));
    }

    public function test_the_chain_closes_over_the_gap(): void
    {
        $first = $this->makeMeeting('2026-08-05');
        $middle = $this->makeMeeting('2026-08-12', $first);
        $last = $this->makeMeeting('2026-08-19', $middle);

        $first->update(['next_meeting_id' => $middle->id]);
        $middle->update(['next_meeting_id' => $last->id]);

        app(MeetingService::class)->delete($middle, $this->admin);

        // The one before and the one after now point at each other, so "the
        // meeting this one follows" still answers.
        $this->assertSame($last->id, $first->fresh()->next_meeting_id);
        $this->assertSame($first->id, $last->fresh()->previous_meeting_id);
    }

    public function test_a_later_minute_is_not_left_pointing_at_a_deleted_one(): void
    {
        $first = $this->makeMeeting('2026-08-12');
        $task = $this->makeTask('Rebar');
        $this->addLine($first, $task);

        $second = $this->makeMeeting('2026-08-19', $first);
        app(MeetingAgendaService::class)->carryForward($second, collect([$task]), $this->admin);

        $carried = $second->items()->first();
        $this->assertNotNull($carried->carried_from_item_id);

        app(MeetingService::class)->delete($first, $this->admin);

        // The foreign key nulls itself, so the badge simply stops claiming a
        // meeting that no longer exists.
        $this->assertNull($carried->fresh()->carried_from_item_id);
        $this->assertFalse($carried->fresh()->isCarriedForward());
    }

    /*
    |---------------------------------------------------------------------------
    | Clearing an agenda
    |---------------------------------------------------------------------------
    */

    public function test_clearing_an_agenda_leaves_every_task_open(): void
    {
        // Discussed at a meeting that has been and gone …
        $first = $this->makeMeeting('2026-08-05');
        $foundation = $this->makeTask('Foundation');
        $rebar = $this->makeTask('Rebar');
        $this->addLine($first, $foundation);
        $this->addLine($first, $rebar);

        // … and carried onto this one, which is then built wrongly.
        $meeting = $this->makeMeeting('2026-08-12', $first);
        $parent = $this->addLine($meeting, $foundation);
        $this->addLine($meeting, $rebar, $parent);
        $this->addLine($meeting, $this->makeTask('Roof'));

        $component = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Meeting\MeetingAgenda::class, ['meeting' => $meeting])
            ->call('clearAgenda');

        $this->assertSame(0, MeetingItem::where('meeting_id', $meeting->id)->count());
        $this->assertSame(3, Task::where('status', 'open')->count());
        $this->assertSame(2, MeetingItem::where('meeting_id', $first->id)->count());

        // Nothing was closed, so the earlier meeting's work is proposed again
        // straight away — starting over is one press, not twenty.
        $component->assertSee('Foundation')->assertSee('Rebar');
    }

    public function test_a_task_whose_only_agenda_is_cleared_stops_being_meeting_tracked(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        $only = $this->makeTask('Raised here and nowhere else');
        $this->addLine($meeting, $only);

        $this->assertSame(1, Task::meetingTracked()->count());

        app(MeetingAgendaService::class)->clear($meeting);

        // Its one agenda line is gone, so no meeting has discussed it: it drops
        // back to being somebody's own task and is no longer proposed on its
        // own. It is still open, still on the project, and can be put back on
        // an agenda from the "not on the agenda" drawer.
        $this->assertSame(0, Task::meetingTracked()->count());
        $this->assertSame(1, Task::direct()->where('status', 'open')->count());
    }

    public function test_clearing_is_refused_on_a_published_minute(): void
    {
        $meeting = $this->makeMeeting('2026-08-12');
        $this->addLine($meeting, $this->makeTask('One'));
        $meeting->update(['status' => 'published', 'published_at' => now()]);

        try {
            app(MeetingAgendaService::class)->clear($meeting);
            $this->fail('A published minute was emptied.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(1, MeetingItem::where('meeting_id', $meeting->id)->count());
    }

    public function test_clearing_touches_no_other_meeting(): void
    {
        $theirs = $this->makeMeeting('2026-08-05');
        $this->addLine($theirs, $this->makeTask('Theirs'));

        $ours = $this->makeMeeting('2026-08-12', $theirs);
        $this->addLine($ours, $this->makeTask('Ours'));

        app(MeetingAgendaService::class)->clear($ours);

        $this->assertSame(0, MeetingItem::where('meeting_id', $ours->id)->count());
        $this->assertSame(1, MeetingItem::where('meeting_id', $theirs->id)->count());
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function makeMeeting(string $date, ?Meeting $previous = null): Meeting
    {
        return Meeting::create([
            'meeting_series_id' => $this->series->id,
            'number' => 'OBRA-'.str_replace('-', '', $date),
            'title' => 'Review '.$date,
            'meeting_date' => $date,
            'previous_meeting_id' => $previous?->id,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeTask(string $title): Task
    {
        return Task::create([
            'uuid' => (string) str()->uuid(),
            'title' => $title,
            'project_id' => $this->project->id,
            'owner_id' => $this->admin->id,
            'priority' => 'normal',
            'status' => 'open',
            'progress' => 0,
            'due_date' => '2026-09-01',
        ]);
    }

    protected function addLine(Meeting $meeting, Task $task, ?MeetingItem $parent = null): MeetingItem
    {
        return MeetingItem::create([
            'meeting_id' => $meeting->id,
            'parent_id' => $parent?->id,
            'position' => MeetingItem::where('meeting_id', $meeting->id)->where('parent_id', $parent?->id)->count(),
            'project_id' => $task->project_id,
            'type' => 'action',
            'title' => $task->title,
            'task_id' => $task->id,
            'created_by' => $this->admin->id,
        ]);
    }
}
