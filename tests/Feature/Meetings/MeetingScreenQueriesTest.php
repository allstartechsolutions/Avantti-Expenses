<?php

namespace Tests\Feature\Meetings;

use App\Enums\ProjectStatus;
use App\Livewire\Meeting\MeetingAgenda;
use App\Livewire\Meeting\MeetingShow;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\MeetingSeries;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What the two meeting screens are allowed to cost.
 *
 * A ten-line minute was taking a hundred and thirty queries: the same agenda
 * read four times, a lookup a row for the parent of every sub-item, the
 * publish dialog costing three queries on a minute that can never be published
 * again, and the sort rebuilding its ranking once per location on the page.
 *
 * These are counts rather than timings, so they hold on any machine — and each
 * one is written so that adding a row must not move it. That is the whole
 * point: a screen that costs a query a row passes a spot check and falls over
 * on a real agenda.
 *
 * Every comparison gives each meeting its own series, so the only thing that
 * differs between the two sides is the one being measured.
 */
class MeetingScreenQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    /** Meeting numbers are unique, and these tests make a lot of meetings. */
    protected int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $this->project = Project::create([
            'project_name' => 'Query Project',
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Query Client'],
                ['contact_name' => 'C', 'email' => 'q@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => 'query-project@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Cost that does not grow with the agenda
    |---------------------------------------------------------------------------
    */

    public function test_the_minute_costs_the_same_whether_it_has_three_lines_or_thirty(): void
    {
        $small = $this->meeting(roots: 3, subItemsEach: 1);
        $large = $this->meeting(roots: 30, subItemsEach: 1);

        $this->assertSame(
            $this->queriesWhileRendering(MeetingShow::class, $small),
            $this->queriesWhileRendering(MeetingShow::class, $large),
            'The minute is reading something a row.'
        );
    }

    public function test_the_agenda_costs_the_same_whether_it_has_three_lines_or_thirty(): void
    {
        $small = $this->meeting(roots: 3, subItemsEach: 1);
        $large = $this->meeting(roots: 30, subItemsEach: 1);

        $this->assertSame(
            $this->queriesWhileRendering(MeetingAgenda::class, $small),
            $this->queriesWhileRendering(MeetingAgenda::class, $large),
            'The agenda is reading something a row.'
        );
    }

    /**
     * The ranking that puts carried items back in last meeting's order reads
     * the earlier meetings of the series. Read one at a time — which it was —
     * a series that has been running a year costs fifty queries every time the
     * agenda sorts, and nobody notices until the year is up.
     */
    public function test_the_agenda_costs_the_same_however_long_the_series_has_been_running(): void
    {
        $short = $this->latestOfSeriesRunningFor(2);
        $long = $this->latestOfSeriesRunningFor(12);

        $this->assertSame(
            $this->queriesWhileRendering(MeetingAgenda::class, $short),
            $this->queriesWhileRendering(MeetingAgenda::class, $long),
            'The agenda is reading something per earlier meeting of the series.'
        );
    }

    /**
     * Publishing is offered on a draft and nowhere else, so the dialog that
     * counts unowned actions, looks for later meetings of the series and works
     * out where the minute will be filed must not be built on a minute that is
     * already a record.
     *
     * Asserted on the queries themselves rather than on the total: a published
     * minute renders no textareas and no item form either, so it comes out
     * cheaper whether the dialog is built or not.
     */
    public function test_a_published_minute_does_not_pay_for_the_publish_dialog(): void
    {
        $meeting = $this->meeting(roots: 3, subItemsEach: 1);

        $meeting->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $this->admin->id,
        ]);

        $sql = $this->sqlWhileRendering(MeetingShow::class, $meeting);

        // "Later meetings of this series" — asked by the dialog and nothing
        // else on this screen.
        $this->assertEmpty(
            array_filter($sql, fn (string $query) => str_contains($query, 'meeting_series_id')),
            'A published minute is still looking for later meetings of its series.'
        );

        // Where the minute would be filed, which it already has been.
        $this->assertEmpty(
            array_filter($sql, fn (string $query) => str_contains($query, 'job_site_id')
                && str_contains($query, 'select')
                && ! str_contains($query, 'select *')),
            'A published minute is still working out which project to file itself under.'
        );
    }

    /**
     * A meeting that covers five projects is an ordinary agenda, not an
     * extreme one.
     *
     * `scopeCandidates()` was asked once per location, and each ask was two
     * task reads plus their `owner` / `project` / `jobSite` eager loads — about
     * four queries a location, so ten locations cost fifty-one queries against
     * one location's fifteen. It is one read for the whole agenda now, and the
     * two populations are told apart from the count already loaded.
     */
    public function test_the_agenda_costs_the_same_however_many_locations_it_covers(): void
    {
        $few = $this->meetingSpanning(1);
        $many = $this->meetingSpanning(10);

        $this->assertSame(
            $this->queriesWhileRendering(MeetingAgenda::class, $few),
            $this->queriesWhileRendering(MeetingAgenda::class, $many),
            'The agenda is reading something per location on it.'
        );
    }

    /** The drawers still find what they are for — cheap is not the only test. */
    public function test_every_location_still_finds_its_open_work(): void
    {
        $meeting = $this->meetingSpanning(4);

        $found = Livewire::actingAs($this->admin)
            ->test(MeetingAgenda::class, ['meeting' => $meeting])
            ->instance()
            ->scopeCandidates;

        $this->assertCount(4, $found, 'A location lost its drawer.');
        $this->assertSame(4, $found->sum(fn (array $scope) => $scope['direct']->count()),
            'A location lost the open task that is not on the agenda.');
    }

    /*
    |---------------------------------------------------------------------------
    | And still says the same things
    |---------------------------------------------------------------------------
    */

    public function test_a_sub_item_is_still_numbered_under_its_parent(): void
    {
        $meeting = $this->meeting(roots: 2, subItemsEach: 2);

        Livewire::actingAs($this->admin)
            ->test(MeetingShow::class, ['meeting' => $meeting])
            ->assertSee('1.1')
            ->assertSee('2.2');

        Livewire::actingAs($this->admin)
            ->test(MeetingAgenda::class, ['meeting' => $meeting])
            ->assertSee('1.1')
            ->assertSee('2.2');
    }

    /**
     * A task's notes are read four at a time, and the heading still tells the
     * truth about how many there are.
     *
     * The panel shows the newest few, so a task carried through twenty meetings
     * was pulling every note it had ever collected into memory to display four.
     * Both halves matter: the count must stay honest (it is what the "see all"
     * link promises), and the four must be the NEWEST four — reading the wrong
     * end of the list would be a worse bug than the one being fixed.
     */
    public function test_a_tasks_notes_are_read_a_few_at_a_time_and_still_counted_in_full(): void
    {
        $meeting = $this->meeting(roots: 1, subItemsEach: 0);
        $task = MeetingItem::where('meeting_id', $meeting->id)->first()->task;

        foreach (range(1, 20) as $i) {
            TaskNote::create([
                'task_id' => $task->id,
                'user_id' => $this->admin->id,
                'body' => "note number {$i}",
            ])->forceFill(['created_at' => now()->addMinutes($i)])->saveQuietly();
        }

        $page = Livewire::actingAs($this->admin)->test(MeetingShow::class, ['meeting' => $meeting]);
        $loaded = $page->instance()->items->first()->task;

        $this->assertCount(MeetingShow::NOTES_SHOWN, $loaded->notes,
            'Every note was read to display a handful.');

        $this->assertSame(
            ['note number 20', 'note number 19', 'note number 18', 'note number 17'],
            $loaded->notes->pluck('body')->all(),
            'The oldest notes were read instead of the newest.'
        );

        $this->assertSame(20, (int) $loaded->notes_count, 'The count stopped being the real total.');

        $page->assertSee('20 notes')->assertSee('See all 20 notes');
    }

    /** The dialog is on the page from the start; only its fields wait. */
    public function test_the_task_form_fills_in_when_it_is_opened(): void
    {
        $meeting = $this->meeting(roots: 1, subItemsEach: 0);

        $screen = Livewire::actingAs($this->admin)
            ->test(MeetingShow::class, ['meeting' => $meeting])
            ->assertSet('showTaskForm', false)
            ->assertDontSee('Create Task');

        $screen->call('openTaskForm')
            ->assertSet('showTaskForm', true)
            ->assertSee('Create Task')
            ->assertSee('Query Project')
            ->assertDispatched('open-modal');

        $screen->call('closeTaskForm')
            ->assertSet('showTaskForm', false)
            ->assertDontSee('Create Task');
    }

    /*
    |---------------------------------------------------------------------------
    | Helpers
    |---------------------------------------------------------------------------
    */

    /**
     * How many queries one render of a screen takes.
     *
     * Rendered once first and thrown away: which modules are switched on and
     * what a role may do are read once per application, so the very first
     * render of a test pays for them and no other one does. Counting that
     * would make the first meeting measured look dearer than the second
     * whatever they contained.
     */
    protected function queriesWhileRendering(string $component, Meeting $meeting): int
    {
        return count($this->sqlWhileRendering($component, $meeting));
    }

    /**
     * The SQL one render of a screen runs.
     *
     * @return array<int, string>
     */
    protected function sqlWhileRendering(string $component, Meeting $meeting): array
    {
        Livewire::actingAs($this->admin)->test($component, ['meeting' => $meeting]);

        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        Livewire::actingAs($this->admin)->test($component, ['meeting' => $meeting]);

        return $queries;
    }

    /**
     * A meeting whose agenda covers `$scopes` different projects, each with one
     * line on the agenda and one open task that is not.
     */
    protected function meetingSpanning(int $scopes): Meeting
    {
        $meeting = $this->meetingOn($this->newSeries(), roots: 0, subItemsEach: 0);

        for ($i = 0; $i < $scopes; $i++) {
            $this->sequence++;

            $project = Project::create([
                'project_name' => "Spanned {$this->sequence}",
                'client_id' => Client::firstOrCreate(
                    ['company_name' => 'Query Client'],
                    ['contact_name' => 'C', 'email' => 'q@example.test', 'created_by' => $this->admin->id],
                )->id,
                'contact_person' => 'C',
                'email' => "spanned-{$this->sequence}@example.test",
                'status' => ProjectStatus::CREATED,
                'created_by' => $this->admin->id,
            ]);

            // One on the agenda, one open and off it — which is what puts the
            // location in the "not on the agenda" drawers.
            $this->line($meeting, $i, "On {$this->sequence}", null, $project);
            $this->task($project, "Off {$this->sequence}");
        }

        return $meeting;
    }

    /** A meeting on a series of its own, so nothing else can move the count. */
    protected function meeting(int $roots, int $subItemsEach): Meeting
    {
        return $this->meetingOn($this->newSeries(), $roots, $subItemsEach);
    }

    protected function newSeries(): MeetingSeries
    {
        $this->sequence++;

        return MeetingSeries::create([
            'name' => "Series {$this->sequence}",
            'code' => "S{$this->sequence}",
            'created_by' => $this->admin->id,
        ]);
    }

    protected function meetingOn(MeetingSeries $series, int $roots, int $subItemsEach, ?Meeting $previous = null): Meeting
    {
        $this->sequence++;

        $meeting = Meeting::create([
            'meeting_series_id' => $series->id,
            'number' => "QRY-{$this->sequence}",
            'title' => "Site review {$this->sequence}",
            'meeting_date' => now()->parse('2026-01-07')->addWeeks($this->sequence)->toDateString(),
            'previous_meeting_id' => $previous?->id,
            'chair_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        for ($i = 0; $i < $roots; $i++) {
            $root = $this->line($meeting, $i, "Root {$this->sequence} {$i}");

            for ($j = 0; $j < $subItemsEach; $j++) {
                $this->line($meeting, $j, "Sub {$this->sequence} {$i}.{$j}", $root);
            }
        }

        return $meeting;
    }

    protected function line(
        Meeting $meeting,
        int $position,
        string $title,
        ?MeetingItem $parent = null,
        ?Project $project = null,
    ): MeetingItem {
        $project ??= $this->project;

        return MeetingItem::create([
            'meeting_id' => $meeting->id,
            'parent_id' => $parent?->id,
            'position' => $position,
            'project_id' => $project->id,
            'type' => 'action',
            'title' => $title,
            'task_id' => $this->task($project, $title)->id,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function task(Project $project, string $title): Task
    {
        return Task::create([
            'uuid' => (string) str()->uuid(),
            'title' => $title,
            'project_id' => $project->id,
            'owner_id' => $this->admin->id,
            'priority' => 'normal',
            'status' => 'open',
            'progress' => 0,
            'due_date' => '2026-09-01',
        ]);
    }

    /** The newest meeting of a series that already has `$count` behind it. */
    protected function latestOfSeriesRunningFor(int $count): Meeting
    {
        $series = $this->newSeries();
        $previous = null;

        for ($week = 0; $week <= $count; $week++) {
            $previous = $this->meetingOn($series, roots: 2, subItemsEach: 0, previous: $previous);
        }

        return $previous;
    }
}
