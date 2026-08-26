<?php

namespace Database\Seeders;

use App\Models\JobSite;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\MeetingSeries;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A worked example of the agenda ordering, to be looked at rather than tested.
 *
 * It builds one series — code **DEMO** — with three meetings that tell the
 * story of the complaint this work answers:
 *
 *   1. **Two weeks ago, published.** An agenda deliberately *not* in due-date
 *      order: the chair dragged it into the order they wanted, across three
 *      locations, with one line carrying two sub-items.
 *   2. **Last week, published.** The same work carried forward, plus one item
 *      raised on the day.
 *   3. **Today, draft and empty.** The one to open. Press *Carry N items
 *      forward* and watch them land grouped by location, in the order of
 *      meeting 2 — not sorted by due date, and with the sub-items still nested.
 *   4. **Next week, draft and empty.** Open its agenda to see the banner that
 *      warns meeting 3 has not been published yet.
 *
 * Deliberately additive: it uses whatever projects and job sites the database
 * already has and touches nothing outside its own series. Running it again
 * removes what it made last time and rebuilds it, so it is safe to repeat.
 *
 * php artisan db:seed --class=DemoAgendaOrderSeeder
 *
 * See docs/meetings-agenda-order-plan.md.
 */
class DemoAgendaOrderSeeder extends Seeder
{
    protected const CODE = 'DEMO';

    public function run(): void
    {
        $actor = User::orderBy('id')->first();

        if (! $actor) {
            $this->command?->error('No users in the database — nothing to own the demo tasks.');

            return;
        }

        $projects = Project::with('jobSites')->orderBy('id')->take(2)->get();

        if ($projects->isEmpty()) {
            $this->command?->error('No projects in the database. The demo needs at least one.');

            return;
        }

        $this->clearPreviousRun();

        // Three locations if the database can offer them, so the grouping has
        // something to group. A job site of the first project is used in
        // preference to a second project: project › job site is the harder
        // case and the one worth looking at.
        $first = $projects->first();
        $site = $first->jobSites->first();
        $second = $projects->skip(1)->first();

        $places = collect([
            ['project' => $first, 'site' => null, 'label' => $first->project_name],
        ]);

        if ($site) {
            $places->push(['project' => $first, 'site' => $site, 'label' => $first->project_name.' › '.$site->job_site_name]);
        }

        if ($second) {
            $places->push(['project' => $second, 'site' => null, 'label' => $second->project_name]);
        }

        DB::transaction(function () use ($actor, $places) {
            $series = MeetingSeries::create([
                'name' => 'Demo — Weekly Site Review',
                'code' => self::CODE,
                'description' => 'Seeded to demonstrate agenda ordering. Safe to delete.',
                'cadence' => 'weekly',
                'agenda_order' => 'last_meeting',
                'default_location' => 'Site office',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($places as $place) {
                $series->scopes()->create([
                    'project_id' => $place['project']->id,
                    'job_site_id' => $place['site']?->id,
                ]);
            }

            $tasks = $this->makeTasks($places, $actor);

            $firstMeeting = $this->makeMeeting($series, 'DEMO-2026-001', Carbon::today()->subWeeks(2), $actor);
            $this->buildFirstAgenda($firstMeeting, $tasks, $actor);
            $this->publish($firstMeeting, $actor);

            $secondMeeting = $this->makeMeeting($series, 'DEMO-2026-002', Carbon::today()->subWeek(), $actor, $firstMeeting);
            $this->buildSecondAgenda($secondMeeting, $tasks, $actor);
            $this->publish($secondMeeting, $actor);

            $today = $this->makeMeeting($series, 'DEMO-2026-003', Carbon::today(), $actor, $secondMeeting);
            $next = $this->makeMeeting($series, 'DEMO-2026-004', Carbon::today()->addWeek(), $actor, $today);

            $this->report($series, $firstMeeting, $secondMeeting, $today, $next);
        });
    }

    /*
    |---------------------------------------------------------------------------
    | The work being tracked
    |---------------------------------------------------------------------------
    */

    /**
     * Open tasks across the locations, with the due dates deliberately at odds
     * with the order the chair will put them in — that disagreement is the
     * whole point of the demonstration.
     *
     * @return array<string, Task>
     */
    protected function makeTasks($places, User $actor): array
    {
        $home = $places[0];
        $middle = $places[1] ?? $places[0];
        $last = $places[2] ?? $places[0];

        $rows = [
            // key                    place     title                                   due (days from today)
            'foundation' => [$home,   'Foundation pour — sign-off outstanding',          21,  40],
            'rebar' => [$home,        'Rebar delivery short by 4 tonnes',               -12,  10],
            'inspection' => [$home,   'Council inspection to be rebooked',               -3,   0],
            'drainage' => [$home,     'Drainage falls to be re-surveyed',                 5,  70],
            'scaffold' => [$middle,   'Scaffold licence expires — renew',                -6,  25],
            'windows' => [$middle,    'Window schedule not signed off by the client',    14,  55],
            'crane' => [$last,        'Crane hire quote still unconfirmed',              -1,   0],
            'power' => [$last,        'Temporary power connection date',                 30,  15],
            'fencing' => [$last,      'Hoarding and site fencing quote',                  9,   0],
        ];

        $tasks = [];

        foreach ($rows as $key => [$place, $title, $dueInDays, $progress]) {
            $tasks[$key] = Task::create([
                'uuid' => (string) str()->uuid(),
                'title' => $title,
                'description' => 'Seeded by DemoAgendaOrderSeeder to demonstrate agenda ordering.',
                'project_id' => $place['project']->id,
                'job_site_id' => $place['site']?->id,
                'owner_id' => $actor->id,
                'priority' => $dueInDays < 0 ? 'high' : 'normal',
                'status' => 'open',
                'progress' => $progress,
                'due_date' => Carbon::today()->addDays($dueInDays),
                'created_by' => $actor->id,
            ]);
        }

        return $tasks;
    }

    /*
    |---------------------------------------------------------------------------
    | The agendas
    |---------------------------------------------------------------------------
    */

    /**
     * Two weeks ago: the order a chair actually wanted.
     *
     * Note what it is *not* — by due date this would read rebar, scaffold,
     * crane, inspection, … with the three locations interleaved. It reads by
     * location instead, and inside each one in the order that made sense on the
     * day.
     */
    protected function buildFirstAgenda(Meeting $meeting, array $tasks, User $actor): void
    {
        $this->layOut($meeting, $tasks, $actor);

        $meeting->allItems()->whereNotNull('task_id')->get()->each(
            fn (MeetingItem $item) => $item->update([
                'discussion' => 'Discussed. Carried to the next meeting.',
            ])
        );
    }

    /** Last week: the same work, plus one raised on the day. */
    protected function buildSecondAgenda(Meeting $meeting, array $tasks, User $actor): void
    {
        $this->layOut($meeting, $tasks, $actor);

        // Raised at this meeting, so it has no earlier position at all — it
        // should land at the end of its own location's block, not the page.
        $this->line($meeting, $tasks['fencing'], $actor, 6);

        $meeting->allItems()->whereNotNull('task_id')->get()->each(
            fn (MeetingItem $item) => $item->update([
                'discussion' => 'Reviewed. Still open.',
            ])
        );
    }

    /**
     * The shape both published agendas share.
     *
     * In every location an on-time line sits **above** a late one, which is the
     * point: the two ordering modes then read differently, and switching the
     * series between them shows what each one does. Left as it was, the late
     * work already happened to be first and the demonstration proved nothing.
     */
    protected function layOut(Meeting $meeting, array $tasks, User $actor): void
    {
        // Project level: an on-time line, then a parent whose two sub-items are
        // both late — so "past due first" lifts the parent with its children.
        $this->line($meeting, $tasks['drainage'], $actor, 0);
        $foundation = $this->line($meeting, $tasks['foundation'], $actor, 1);
        $this->line($meeting, $tasks['rebar'], $actor, 0, $foundation);
        $this->line($meeting, $tasks['inspection'], $actor, 1, $foundation);

        // Job site: on time above late.
        $this->line($meeting, $tasks['windows'], $actor, 2);
        $this->line($meeting, $tasks['scaffold'], $actor, 3);

        // Second project: the same again.
        $this->line($meeting, $tasks['power'], $actor, 4);
        $this->line($meeting, $tasks['crane'], $actor, 5);
    }

    protected function line(Meeting $meeting, Task $task, User $actor, int $position, ?MeetingItem $parent = null): MeetingItem
    {
        $carriedFrom = MeetingItem::where('task_id', $task->id)
            ->where('meeting_id', '!=', $meeting->id)
            ->orderByDesc('id')
            ->first();

        return $meeting->allItems()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'project_id' => $task->project_id,
            'job_site_id' => $task->job_site_id,
            'type' => 'action',
            'title' => $task->title,
            'task_id' => $task->id,
            'carried_from_item_id' => $carriedFrom?->id,
            'created_by' => $actor->id,
        ]);
    }

    protected function makeMeeting(
        MeetingSeries $series,
        string $number,
        Carbon $date,
        User $actor,
        ?Meeting $previous = null,
    ): Meeting {
        $meeting = Meeting::create([
            'meeting_series_id' => $series->id,
            'number' => $number,
            'title' => 'Weekly site review — '.$date->format('d M Y'),
            'meeting_date' => $date,
            'location' => 'Site office',
            'chair_id' => $actor->id,
            'secretary_id' => $actor->id,
            'previous_meeting_id' => $previous?->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $previous?->update(['next_meeting_id' => $meeting->id, 'next_meeting_date' => $date]);

        $meeting->attendees()->create([
            'user_id' => $actor->id,
            'role' => 'chair',
            'attendance' => 'present',
        ]);

        return $meeting;
    }

    /**
     * Publish it the way the application does — freezing each line's figures —
     * without going through `MeetingService`, whose permission checks and
     * e-mails have no place in a seeder.
     */
    protected function publish(Meeting $meeting, User $actor): void
    {
        $meeting->allItems()->with('task.owner')->get()->each(
            fn (MeetingItem $item) => $item->update(['status_at_meeting' => $item->snapshotTask()])
        );

        $meeting->update([
            'status' => 'published',
            'published_at' => $meeting->meeting_date->copy()->setTime(17, 0),
            'published_by' => $actor->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Running it twice
    |---------------------------------------------------------------------------
    */

    /**
     * Take out whatever the last run made.
     *
     * A task is removed only when every agenda line that mentions it belongs to
     * this demo — anything a real meeting has also discussed is left alone.
     */
    protected function clearPreviousRun(): void
    {
        $series = MeetingSeries::withTrashed()->where('code', self::CODE)->first();

        if (! $series) {
            return;
        }

        DB::transaction(function () use ($series) {
            $meetingIds = Meeting::withTrashed()->where('meeting_series_id', $series->id)->pluck('id');

            $taskIds = MeetingItem::whereIn('meeting_id', $meetingIds)
                ->whereNotNull('task_id')
                ->pluck('task_id')
                ->unique();

            MeetingItem::whereIn('meeting_id', $meetingIds)->delete();

            $orphaned = $taskIds->filter(
                fn (int $taskId) => ! MeetingItem::where('task_id', $taskId)->exists()
            );

            Task::whereIn('id', $orphaned)->forceDelete();
            Meeting::withTrashed()->whereIn('id', $meetingIds)->forceDelete();
            $series->forceDelete();
        });
    }

    protected function report(MeetingSeries $series, Meeting $first, Meeting $second, Meeting $today, Meeting $next): void
    {
        $this->command?->info('Seeded the "'.$series->name.'" series ('.$series->code.').');
        $this->command?->line('  '.$first->number.'  '.$first->meeting_date->format('d M').'  published  — the order the chair wanted');
        $this->command?->line('  '.$second->number.'  '.$second->meeting_date->format('d M').'  published  — the same work, plus one raised on the day');
        $this->command?->line('  '.$today->number.'  '.$today->meeting_date->format('d M').'  draft, empty  ← open this one and press "Carry … forward"');
        $this->command?->line('  '.$next->number.'  '.$next->meeting_date->format('d M').'  draft, empty  ← its builder warns that '.$today->number.' is unpublished');
        $this->command?->line('');
        $this->command?->line('  Agenda builder: /meetings/'.$today->id.'/agenda');
        $this->command?->line('  For "past due first", set Agenda Order on the series and carry again.');
    }
}
