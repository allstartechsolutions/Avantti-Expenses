<?php

namespace Tests\Feature\Collaboration;

use App\Models\Client;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Collaboration\Signature;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Collaboration\Fixtures\FakeDocument;
use Tests\TestCase;

/**
 * The shared engine's behaviour, exercised through a stand-in document before
 * RFIs and approvals exist to carry it.
 */
class CollaborationConcernsTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('fake_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('number')->nullable();
            $table->string('subject')->nullable();
            $table->string('discipline')->nullable();
            $table->text('answer')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('ball_in_court_id')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        $this->seed(RoleSeeder::class);

        $this->user = User::factory()->create([
            'name' => 'Ana Souza',
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $this->user->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $this->user->id,
        ]);
    }

    protected function document(array $attributes = []): FakeDocument
    {
        return FakeDocument::create(array_merge([
            'project_id' => $this->project->id,
            'subject' => 'Detalhe da esquadria',
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | HasSequentialNumber
    |---------------------------------------------------------------------------
    */

    public function test_a_document_is_numbered_as_it_is_created(): void
    {
        $this->assertSame('RFI-001', $this->document()->number);
        $this->assertSame('RFI-002', $this->document()->number);
    }

    public function test_a_number_supplied_by_the_caller_is_left_alone(): void
    {
        // Importing history from a spreadsheet must not consume the sequence.
        $this->assertSame('OLD-99', $this->document(['number' => 'OLD-99'])->number);
        $this->assertSame('RFI-001', $this->document()->number);
    }

    public function test_the_documents_own_tokens_reach_the_template(): void
    {
        app(\App\Services\Collaboration\NumberSequenceService::class)->configure(
            app(\App\Services\Collaboration\NumberSequenceService::class)->sequenceFor($this->project, 'rfi'),
            template: 'SI-{discipline}-{seq:000}',
        );

        $this->assertSame('SI-ARQ-001', $this->document(['discipline' => 'ARQ'])->number);
    }

    /*
    |---------------------------------------------------------------------------
    | LogsCollaborationActivity
    |---------------------------------------------------------------------------
    */

    public function test_activity_is_recorded_against_the_document_with_its_actor(): void
    {
        $this->actingAs($this->user);
        $document = $this->document();

        $document->logActivity(ActivityLogEntry::ANSWERED, ['answered_by' => 'projetista']);

        $entry = $document->activity()->first();
        $this->assertSame(ActivityLogEntry::ANSWERED, $entry->action);
        $this->assertSame($this->user->id, $entry->user_id);
        $this->assertSame(['answered_by' => 'projetista'], $entry->context);
    }

    /**
     * Views are logged — that is the point of the table — but not the same
     * person's reload, over and over, or the history becomes unreadable.
     */
    public function test_a_repeated_view_by_the_same_person_is_not_logged_twice(): void
    {
        $this->actingAs($this->user);
        $document = $this->document();

        $this->assertNotNull($document->logView());
        $this->assertNull($document->logView());
        $this->assertNull($document->logView());

        $this->assertSame(1, $document->activity()->count());
    }

    public function test_a_view_by_somebody_else_is_logged(): void
    {
        $document = $this->document();
        $other = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->actingAs($this->user);
        $document->logView();

        $this->actingAs($other);
        $this->assertNotNull($document->logView());

        $this->assertSame(2, $document->activity()->count());
    }

    public function test_a_view_after_another_action_is_logged_again(): void
    {
        $this->actingAs($this->user);
        $document = $this->document();

        $document->logView();
        $document->logActivity(ActivityLogEntry::ANSWERED);

        $this->assertNotNull($document->logView());
        $this->assertSame(3, $document->activity()->count());
    }

    /** An audit trail that vanishes when somebody leaves is not one. */
    public function test_activity_survives_the_actor_being_deleted(): void
    {
        $actor = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->actingAs($actor);

        $document = $this->document();
        $document->logActivity(ActivityLogEntry::CLOSED);

        $actor->delete();

        $entry = $document->activity()->first();
        $this->assertSame(ActivityLogEntry::CLOSED, $entry->action);
        $this->assertNull($entry->fresh()->user_id);
        $this->assertSame(__('collaboration.label.removed_user'), $entry->fresh()->getActorName());
    }

    public function test_an_action_is_never_printed_raw(): void
    {
        $this->assertSame(__('collaboration.activity.closed'), ActivityLogEntry::actionLabel(ActivityLogEntry::CLOSED));
        $this->assertNotSame('closed', ActivityLogEntry::actionLabel(ActivityLogEntry::CLOSED));
    }

    /*
    |---------------------------------------------------------------------------
    | HasSignatures
    |---------------------------------------------------------------------------
    */

    public function test_signing_records_who_signed_and_what_they_signed(): void
    {
        $document = $this->document(['answer' => 'Usar o detalhe A.']);

        $signature = $document->sign($this->user, 'CREA 12345-D', 'ART 987');

        $this->assertSame('Ana Souza', $signature->signer_name);
        $this->assertSame('CREA 12345-D', $signature->signer_document);
        $this->assertSame('ART 987', $signature->art_number);
        $this->assertSame(Signature::METHOD_DRAWN, $signature->method);
        $this->assertSame(64, strlen($signature->payload_hash));
        $this->assertTrue($document->isSigned());
    }

    public function test_a_signature_holds_while_the_document_is_unchanged(): void
    {
        $document = $this->document(['answer' => 'Usar o detalhe A.']);
        $signature = $document->sign($this->user);

        $document->touch();

        $this->assertTrue($document->fresh()->signatureIsIntact($signature));
        $this->assertTrue($document->fresh()->signaturesAreIntact());
    }

    /**
     * The reason the hash exists. Change what was signed and the signature no
     * longer covers it — the screen must be able to say so.
     */
    public function test_a_signature_stops_matching_when_the_signed_text_changes(): void
    {
        $document = $this->document(['answer' => 'Usar o detalhe A.']);
        $signature = $document->sign($this->user);

        $document->update(['answer' => 'Usar o detalhe B.']);

        $this->assertFalse($document->fresh()->signatureIsIntact($signature));
        $this->assertFalse($document->fresh()->signaturesAreIntact());
    }

    /**
     * The hash is a function of the stored document and nothing else — not of
     * when it was read, and not of unsaved edits in one request's memory.
     */
    public function test_the_hash_is_stable_across_reads_and_ignores_unsaved_edits(): void
    {
        $document = $this->document(['answer' => 'Mesmo texto']);

        $this->assertSame(
            $document->fresh()->signatureHash(),
            $document->fresh()->signatureHash(),
        );

        // An unsaved edit does not change what is stored, so it must not
        // change the hash a signature would be checked against.
        $stored = $document->fresh()->storedSignatureHash();
        $document->answer = 'Texto que ninguém salvou';

        $this->assertSame($stored, $document->storedSignatureHash());
        $this->assertNotSame($stored, $document->signatureHash());
    }

    public function test_the_signer_line_carries_the_registration_when_there_is_one(): void
    {
        $document = $this->document();

        $this->assertSame('Ana Souza — CREA 12345-D', $document->sign($this->user, 'CREA 12345-D')->getSignerLine());
        $this->assertSame('Ana Souza', $this->document()->sign($this->user)->getSignerLine());
    }

    public function test_an_unsigned_document_is_not_reported_as_intact(): void
    {
        // Vacuous truth would be the wrong answer: "every signature holds" on a
        // document with none must not read as "this document is signed".
        $this->assertFalse($this->document()->signaturesAreIntact());
    }

    /*
    |---------------------------------------------------------------------------
    | HasDistributionList
    |---------------------------------------------------------------------------
    */

    public function test_a_distribution_list_takes_users_and_bare_addresses(): void
    {
        $document = $this->document();

        $document->syncDistribution([
            ['user_id' => $this->user->id, 'role' => 'interno'],
            ['external_name' => 'Studio Arq', 'external_email' => 'arq@studio.test', 'role' => 'projetista'],
        ]);

        $this->assertSame(2, $document->distribution()->count());
        $this->assertEqualsCanonicalizing(
            [$this->user->email, 'arq@studio.test'],
            $document->distributionRecipients()->keys()->all(),
        );
    }

    public function test_syncing_replaces_the_list_rather_than_adding_to_it(): void
    {
        $document = $this->document();

        $document->syncDistribution([['external_name' => 'A', 'external_email' => 'a@test.test']]);
        $document->syncDistribution([['external_name' => 'B', 'external_email' => 'b@test.test']]);

        $this->assertSame(1, $document->distribution()->count());
        $this->assertSame(['b@test.test'], $document->distributionRecipients()->keys()->all());
    }

    /** A line naming nobody reachable would fail silently at send time. */
    public function test_an_entry_with_no_user_and_no_address_is_dropped(): void
    {
        $document = $this->document();

        $document->syncDistribution([
            ['external_name' => 'Somebody'],
            ['role' => 'cliente'],
            ['external_name' => 'Real', 'external_email' => 'real@test.test'],
        ]);

        $this->assertSame(1, $document->distribution()->count());
    }

    public function test_one_person_gets_one_copy(): void
    {
        $document = $this->document();

        $document->syncDistribution([
            ['external_name' => 'Studio', 'external_email' => 'arq@studio.test'],
            ['external_name' => 'Studio again', 'external_email' => 'ARQ@Studio.test'],
            ['user_id' => $this->user->id],
            ['user_id' => $this->user->id],
        ]);

        $this->assertSame(2, $document->distribution()->count());
        $this->assertSame(2, $document->distributionRecipients()->count());
    }

    public function test_a_distribution_role_is_never_printed_raw(): void
    {
        $this->assertSame(__('collaboration.role.fiscalizacao'), \App\Models\Collaboration\DistributionEntry::roleLabel('fiscalizacao'));
        $this->assertArrayHasKey('projetista', \App\Models\Collaboration\DistributionEntry::roleOptions());
    }

    /*
    |---------------------------------------------------------------------------
    | BallInCourt
    |---------------------------------------------------------------------------
    */

    public function test_a_document_can_be_handed_to_somebody_with_a_date(): void
    {
        $document = $this->document();

        $document->passTo($this->user, now()->addDays(5)->toDateString());

        $this->assertSame($this->user->id, $document->fresh()->ball_in_court_id);
        $this->assertSame(5, $document->fresh()->daysRemaining());
        $this->assertFalse($document->fresh()->isOverdue());
    }

    public function test_a_past_due_date_makes_it_overdue(): void
    {
        $document = $this->document(['due_date' => now()->subDays(3)->toDateString()]);

        $this->assertTrue($document->isOverdue());
        $this->assertSame(3, $document->daysOverdue());
        $this->assertSame(-3, $document->daysRemaining());
    }

    /**
     * A closed document is never late. A list that keeps flagging finished work
     * is a list people stop reading.
     */
    public function test_a_closed_document_is_never_overdue(): void
    {
        $document = $this->document([
            'due_date' => now()->subDays(30)->toDateString(),
            'status' => 'closed',
        ]);

        $this->assertFalse($document->isOverdue());
        $this->assertNull($document->daysOverdue());
    }

    public function test_the_overdue_scope_matches_the_method(): void
    {
        $late = $this->document(['due_date' => now()->subDay()->toDateString()]);
        $closed = $this->document(['due_date' => now()->subDay()->toDateString(), 'status' => 'closed']);
        $soon = $this->document(['due_date' => now()->addDay()->toDateString()]);
        $undated = $this->document();

        $overdue = FakeDocument::overdue()->pluck('id')->all();

        $this->assertSame([$late->id], $overdue);
        foreach ([$closed, $soon, $undated] as $document) {
            $this->assertFalse(in_array($document->id, $overdue, true));
            $this->assertFalse($document->fresh()->isOverdue());
        }
    }

    public function test_waiting_on_finds_what_is_with_one_person(): void
    {
        $other = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $mine = $this->document(['ball_in_court_id' => $this->user->id]);
        $this->document(['ball_in_court_id' => $other->id]);

        $this->assertSame([$mine->id], FakeDocument::waitingOn($this->user)->pluck('id')->all());
        $this->assertSame([$mine->id], FakeDocument::waitingOn($this->user->id)->pluck('id')->all());
    }

    public function test_due_within_excludes_what_is_already_late(): void
    {
        $soon = $this->document(['due_date' => now()->addDays(3)->toDateString()]);
        $this->document(['due_date' => now()->subDay()->toDateString()]);
        $this->document(['due_date' => now()->addDays(30)->toDateString()]);

        $this->assertSame([$soon->id], FakeDocument::dueWithin(7)->pluck('id')->all());
    }

    public function test_handing_it_to_nobody_clears_the_ball_in_court(): void
    {
        $document = $this->document(['ball_in_court_id' => $this->user->id]);

        $document->passTo(null);

        $this->assertNull($document->fresh()->ball_in_court_id);
    }
}
