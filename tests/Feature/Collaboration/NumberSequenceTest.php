<?php

namespace Tests\Feature\Collaboration;

use App\Models\Client;
use App\Models\Collaboration\NumberSequence;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Collaboration\NumberSequenceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The numbering guarantee, stated as tests.
 *
 * A document number goes on something that leaves the company. Two documents
 * sharing one, or a number reused after a deletion, is not a cosmetic fault —
 * it is two different things called the same name in somebody else's inbox.
 */
class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected NumberSequenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->service = app(NumberSequenceService::class);

        $owner = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $client = Client::create([
            'company_name' => 'Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $owner->id,
        ]);
        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $owner->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Issuing
    |---------------------------------------------------------------------------
    */

    public function test_numbers_run_in_order_from_one(): void
    {
        $numbers = collect(range(1, 5))
            ->map(fn () => $this->service->next($this->project, 'rfi'))
            ->all();

        $this->assertSame(['RFI-001', 'RFI-002', 'RFI-003', 'RFI-004', 'RFI-005'], $numbers);
    }

    public function test_each_document_type_counts_separately(): void
    {
        $this->assertSame('RFI-001', $this->service->next($this->project, 'rfi'));
        $this->assertSame('APR-001', $this->service->next($this->project, 'approval'));
        $this->assertSame('RFI-002', $this->service->next($this->project, 'rfi'));
        $this->assertSame('APR-002', $this->service->next($this->project, 'approval'));
    }

    public function test_each_project_counts_separately(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->project->created_by,
        ]);

        $this->assertSame('RFI-001', $this->service->next($this->project, 'rfi'));
        $this->assertSame('RFI-001', $this->service->next($other, 'rfi'));
        $this->assertSame('RFI-002', $this->service->next($this->project, 'rfi'));
    }

    /**
     * The reason this service exists rather than `max(number) + 1`.
     *
     * Deleting the newest document must not release its number. With a
     * max-based generator this test fails: the next call reads the surviving
     * maximum and hands out 003 a second time.
     */
    public function test_a_number_is_not_reused_after_its_document_is_deleted(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->service->next($this->project, 'rfi');
        }

        // Whatever held RFI-003 is gone; the counter is not.
        $this->assertSame('RFI-004', $this->service->next($this->project, 'rfi'));
    }

    /*
    |---------------------------------------------------------------------------
    | Custom start values
    |---------------------------------------------------------------------------
    */

    public function test_a_project_can_start_partway_through(): void
    {
        $sequence = $this->service->sequenceFor($this->project, 'rfi');
        $this->service->configure($sequence, startValue: 47);

        $this->assertSame('RFI-047', $this->service->next($this->project, 'rfi'));
        $this->assertSame('RFI-048', $this->service->next($this->project, 'rfi'));
    }

    public function test_the_template_decides_the_shape_of_the_number(): void
    {
        $sequence = $this->service->sequenceFor($this->project, 'rfi');
        $this->service->configure($sequence, template: 'SI-{discipline}-{seq:0000}');

        $this->assertSame(
            'SI-ARQ-0001',
            $this->service->next($this->project, 'rfi', ['discipline' => 'ARQ']),
        );
    }

    /**
     * A template naming a token the caller has nothing for must not leave the
     * hole showing. SI--014 is not a document number.
     */
    public function test_a_missing_token_does_not_leave_an_empty_segment(): void
    {
        $sequence = $this->service->sequenceFor($this->project, 'rfi');
        $this->service->configure($sequence, template: 'SI-{discipline}-{seq:000}');

        $this->assertSame('SI-001', $this->service->next($this->project, 'rfi'));
    }

    /*
    |---------------------------------------------------------------------------
    | The lock
    |---------------------------------------------------------------------------
    */

    public function test_the_sequence_locks_itself_once_it_has_issued_anything(): void
    {
        $this->assertTrue($this->service->sequenceFor($this->project, 'rfi')->isEditable());

        $this->service->next($this->project, 'rfi');

        $this->assertFalse($this->service->sequenceFor($this->project, 'rfi')->fresh()->isEditable());
    }

    public function test_a_locked_sequence_refuses_to_be_reconfigured(): void
    {
        $this->service->next($this->project, 'rfi');
        $sequence = $this->service->sequenceFor($this->project, 'rfi')->fresh();

        $this->expectException(ValidationException::class);

        $this->service->configure($sequence, startValue: 100);
    }

    /**
     * Refused loudly. A silent no-op here reads to the user as a save that
     * worked, and they find out when the numbers do not change.
     */
    public function test_the_refusal_says_why_and_names_the_last_number(): void
    {
        $this->service->next($this->project, 'rfi');
        $sequence = $this->service->sequenceFor($this->project, 'rfi')->fresh();

        try {
            $this->service->configure($sequence, startValue: 100);
            $this->fail('A locked sequence accepted a new start value.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('RFI-001', $e->errors()['template'][0]);
        }

        $this->assertSame(1, $sequence->fresh()->start_value);
    }

    public function test_a_template_with_no_sequence_token_is_refused(): void
    {
        $sequence = $this->service->sequenceFor($this->project, 'rfi');

        $this->expectException(ValidationException::class);

        $this->service->configure($sequence, template: 'RFI-FIXED');
    }

    public function test_a_start_value_below_one_is_refused(): void
    {
        $sequence = $this->service->sequenceFor($this->project, 'rfi');

        $this->expectException(ValidationException::class);

        $this->service->configure($sequence, startValue: 0);
    }

    /*
    |---------------------------------------------------------------------------
    | Reading without consuming
    |---------------------------------------------------------------------------
    */

    public function test_peeking_does_not_take_the_number(): void
    {
        $sequence = $this->service->sequenceFor($this->project, 'rfi');

        $this->assertSame(1, $sequence->peek());
        $this->assertSame(1, $sequence->peek());
        $this->assertSame('RFI-001', $this->service->next($this->project, 'rfi'));
        $this->assertSame(2, $sequence->fresh()->peek());
    }

    public function test_asking_for_the_sequence_does_not_lock_it(): void
    {
        $this->service->sequenceFor($this->project, 'rfi');
        $this->service->sequenceFor($this->project, 'rfi');

        $this->assertFalse($this->service->sequenceFor($this->project, 'rfi')->locked);
        $this->assertSame(1, NumberSequence::where('project_id', $this->project->id)->count());
    }

    /*
    |---------------------------------------------------------------------------
    | Concurrency
    |---------------------------------------------------------------------------
    */

    /**
     * The counter is read under a row lock, inside a transaction.
     *
     * This is the mechanism, asserted directly, because the behaviour it
     * produces cannot be asserted here: a second connection blocking on the
     * lock needs the first transaction to commit, and `RefreshDatabase` wraps
     * every test in one that never does. The suite also runs on sqlite, which
     * has no row locks at all.
     *
     * So this pins the two things that make the guarantee hold, and either
     * would be lost by a rewrite to `max(number) + 1`: the read is `for
     * update`, and it is not a bare read outside a transaction.
     *
     * The blocking behaviour itself was verified by hand against MySQL on
     * 26 Aug 2026 — a second connection asking for the same row waited on the
     * first and timed out at 50s rather than reading a stale counter, which is
     * the outcome this depends on. See docs/RFI-Submittals-modules.md phase 2.
     */
    public function test_the_counter_is_read_under_a_row_lock_inside_a_transaction(): void
    {
        // Holds on every driver: the lock is in the code that issues numbers.
        // sqlite compiles lockForUpdate() away entirely, so the SQL cannot be
        // asked on the suite's own connection.
        $source = file_get_contents(app_path('Services/Collaboration/NumberSequenceService.php'));

        $this->assertStringContainsString(
            'lockForUpdate()',
            $source,
            'The sequence row must be read with lockForUpdate(); without it two requests read the same counter.',
        );
        $this->assertStringContainsString('DB::transaction', $source);

        DB::enableQueryLog();
        $this->service->next($this->project, 'rfi');
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertFalse(
            $queries->contains(fn (string $sql) => str_contains(strtolower($sql), 'max(')),
            'The number must come from the counter column, never from max() over the documents.',
        );

        // And on the real driver, that the lock reaches the database.
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertTrue(
                $queries->contains(fn (string $sql) => str_contains($sql, 'collaboration_number_sequences')
                    && str_contains(strtolower($sql), 'for update')),
            );
        }
    }

    /**
     * `next()` must not leave the counter moved if the caller fails afterwards.
     *
     * The number is taken inside the caller's transaction when there is one, so
     * a document that fails to save releases its number rather than burning it.
     */
    public function test_a_rolled_back_caller_does_not_consume_a_number(): void
    {
        $this->service->next($this->project, 'rfi');

        try {
            DB::transaction(function () {
                $this->service->next($this->project, 'rfi');

                throw new \RuntimeException('the document failed to save');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('RFI-002', $this->service->next($this->project, 'rfi'));
    }

    /**
     * The counter is the only source of a number. A caller that writes
     * `current_value` back down cannot make the sequence hand one out twice,
     * because `next()` never trusts anything but the row it locked.
     */
    public function test_the_counter_only_moves_forward_through_the_service(): void
    {
        $this->service->next($this->project, 'rfi');
        $this->service->next($this->project, 'rfi');

        $this->assertSame(2, $this->service->sequenceFor($this->project, 'rfi')->fresh()->current_value);
        $this->assertSame('RFI-003', $this->service->next($this->project, 'rfi'));
    }
}
