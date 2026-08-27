<?php

namespace Tests\Feature\Collaboration;

use App\Enums\JobSiteStatus;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Collaboration\ResponseCode;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The revision cycle, which is the whole of this module.
 *
 * The rules under test: a rejection belongs to the submission that was
 * rejected and not to the material; the last coded word belongs to the last
 * reviewer in sequence; and a send-back ends the round at once, whoever says
 * it, because there is no sense asking the engineer to review a drawing the
 * architect has already returned.
 */
class ApprovalCycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CollaborationResponseCodeSeeder::class);

        $this->author = $this->person('Ana Souza');

        $client = Client::create([
            'company_name' => 'Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $this->author->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $this->author->id,
        ]);
    }

    protected function person(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);
    }

    protected function approval(array $attributes = []): Approval
    {
        return Approval::create(array_merge([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato do hall',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->author->id,
        ], $attributes));
    }

    protected function code(string $canonical): ResponseCode
    {
        return ResponseCode::offered('approval')->firstWhere('canonical', $canonical);
    }

    /*
    |---------------------------------------------------------------------------
    | Numbering and setup
    |---------------------------------------------------------------------------
    */

    public function test_an_approval_is_numbered_from_its_own_sequence(): void
    {
        $first = $this->approval()->number;
        $second = $this->approval()->number;

        // The prefix is the install's, so the increment is what is asserted
        // here; which prefix it is has a test of its own below.
        $this->assertStringEndsWith('-001', $first);
        $this->assertStringEndsWith('-002', $second);
    }

    /**
     * The prefix follows the install's country, and is the one thing about a
     * document number that does.
     *
     * Written out both ways because the suite pins APP_COUNTRY to US: without
     * this, the BR half of the behaviour would never be exercised — which is
     * exactly what happened until a real BR install found it.
     */
    public function test_the_number_prefix_follows_the_installs_country(): void
    {
        config(['app.country' => 'US']);
        $this->assertSame('SUB-001', $this->approval()->number);

        config(['app.country' => 'BR']);
        $this->assertSame('APR-002', $this->approval()->number);
    }

    public function test_approvals_and_rfis_count_separately(): void
    {
        $rfi = \App\Models\Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Uma pergunta',
            'question' => 'Qual?',
            'created_by_id' => $this->author->id,
        ]);

        // Two sequences, each starting at 1 — the prefixes differ per install
        // and are not the point here.
        $this->assertStringEndsWith('-001', $rfi->number);
        $this->assertStringEndsWith('-001', $this->approval()->number);
        $this->assertNotSame($rfi->number, $this->approval()->number);
    }

    /*
    |---------------------------------------------------------------------------
    | Submitting
    |---------------------------------------------------------------------------
    */

    public function test_submitting_opens_revision_zero_and_hands_it_to_the_reviewer(): void
    {
        $projetista = $this->person('Studio Arq');
        $approval = $this->approval();

        $revision = $approval->submit([['user_id' => $projetista->id]], $this->author);

        $this->assertSame('0', $revision->revision);
        $this->assertSame('0', $approval->fresh()->current_revision);
        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);
        $this->assertSame($projetista->id, $approval->fresh()->ball_in_court_id);
        $this->assertSame(1, $revision->reviewers()->count());
    }

    /** A submission nobody was asked to look at can never come back. */
    public function test_a_submission_with_no_reviewer_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->approval()->submit([], $this->author);
    }

    public function test_a_second_submission_is_refused_while_one_is_out(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();

        $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $this->expectException(ValidationException::class);

        $approval->submit([['user_id' => $reviewer->id]], $this->author);
    }

    public function test_the_same_person_named_twice_reviews_once(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();

        $revision = $approval->submit([
            ['user_id' => $reviewer->id, 'sequence' => 1],
            ['user_id' => $reviewer->id, 'sequence' => 2],
        ], $this->author);

        $this->assertSame(1, $revision->reviewers()->count());
    }

    /*
    |---------------------------------------------------------------------------
    | One reviewer
    |---------------------------------------------------------------------------
    */

    public function test_an_approval_closes_when_the_reviewer_approves(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $reviewer, 'Conforme.');

        $approval->refresh();
        $this->assertSame(Approval::APPROVED, $approval->status);
        $this->assertNull($approval->ball_in_court_id);
        $this->assertTrue($approval->isClosed());
        $this->assertSame('Conforme.', $approval->revisions()->first()->comments);
    }

    public function test_approved_as_noted_also_closes_the_cycle(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $approval->recordResponse($this->code(ResponseCode::APPROVED_AS_NOTED), $reviewer);

        $this->assertSame(Approval::APPROVED, $approval->fresh()->status);
    }

    /**
     * The round-trip the whole cycle exists for.
     */
    public function test_revise_and_resubmit_opens_the_next_revision(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $approval->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $reviewer, 'Trocar o rejunte.');

        $approval->refresh();
        $this->assertSame(Approval::IN_REVIEW, $approval->status);
        $this->assertFalse($approval->isClosed());
        // Back with whoever raised it.
        $this->assertSame($this->author->id, $approval->ball_in_court_id);

        // And the next submission is revision 1.
        $second = $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $this->assertSame('1', $second->revision);
        $this->assertSame('1', $approval->fresh()->current_revision);
        $this->assertSame(2, $approval->revisions()->count());

        // The first round's answer is still on the record.
        $first = $approval->revisions()->first();
        $this->assertSame('Trocar o rejunte.', $first->comments);
        $this->assertSame(ResponseCode::REVISE_RESUBMIT, $first->responseCode->canonical);
    }

    /** A rejection ends the revision, not the approval. */
    public function test_a_rejection_leaves_the_approval_open_to_a_fresh_submission(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $approval->recordResponse($this->code(ResponseCode::REJECTED), $reviewer);

        $approval->refresh();
        $this->assertSame(Approval::REJECTED, $approval->status);
        $this->assertFalse($approval->isClosed());

        $second = $approval->submit([['user_id' => $reviewer->id]], $this->author);
        $this->assertSame('1', $second->revision);
    }

    /** Nothing more goes onto an approval that has been accepted. */
    public function test_a_closed_approval_refuses_a_further_submission(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->author);
        $approval->recordResponse($this->code(ResponseCode::APPROVED), $reviewer);

        $this->expectException(ValidationException::class);

        $approval->fresh()->submit([['user_id' => $reviewer->id]], $this->author);
    }

    /*
    |---------------------------------------------------------------------------
    | Sequential review — the US chain
    |---------------------------------------------------------------------------
    */

    public function test_a_chain_asks_each_reviewer_in_turn(): void
    {
        $gc = $this->person('GC');
        $architect = $this->person('Architect');
        $engineer = $this->person('Engineer');

        $approval = $this->approval();
        $revision = $approval->submit([
            ['user_id' => $gc->id, 'sequence' => 1],
            ['user_id' => $architect->id, 'sequence' => 2],
            ['user_id' => $engineer->id, 'sequence' => 3],
        ], $this->author);

        // Only the first is being waited on.
        $this->assertTrue($revision->isWaitingOn($gc));
        $this->assertFalse($revision->isWaitingOn($architect));
        $this->assertSame($gc->id, $approval->fresh()->ball_in_court_id);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $gc);

        $revision = $approval->fresh()->openRevision()->load('reviewers');
        $this->assertTrue($revision->isWaitingOn($architect));
        $this->assertFalse($revision->isWaitingOn($engineer));
        $this->assertSame($architect->id, $approval->fresh()->ball_in_court_id);

        // Still open — the chain is not finished.
        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);
    }

    public function test_a_reviewer_cannot_answer_out_of_turn(): void
    {
        $gc = $this->person('GC');
        $architect = $this->person('Architect');

        $approval = $this->approval();
        $approval->submit([
            ['user_id' => $gc->id, 'sequence' => 1],
            ['user_id' => $architect->id, 'sequence' => 2],
        ], $this->author);

        $this->expectException(ValidationException::class);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $architect);
    }

    /** The last coded word belongs to the last reviewer. */
    public function test_the_chain_closes_only_when_the_last_reviewer_answers(): void
    {
        $gc = $this->person('GC');
        $architect = $this->person('Architect');

        $approval = $this->approval();
        $approval->submit([
            ['user_id' => $gc->id, 'sequence' => 1],
            ['user_id' => $architect->id, 'sequence' => 2],
        ], $this->author);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $gc);
        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);

        $approval->fresh()->recordResponse($this->code(ResponseCode::APPROVED), $architect);
        $this->assertSame(Approval::APPROVED, $approval->fresh()->status);
    }

    /**
     * A send-back ends the round at once. There is no sense asking the
     * engineer to review a drawing the architect has already returned.
     */
    public function test_a_send_back_from_an_early_reviewer_ends_the_round(): void
    {
        $architect = $this->person('Architect');
        $engineer = $this->person('Engineer');

        $approval = $this->approval();
        $approval->submit([
            ['user_id' => $architect->id, 'sequence' => 1],
            ['user_id' => $engineer->id, 'sequence' => 2],
        ], $this->author);

        $approval->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $architect, 'Rever o detalhe.');

        $approval->refresh();
        $this->assertNull($approval->openRevision(), 'The round should be finished.');
        $this->assertSame($this->author->id, $approval->ball_in_court_id);

        // The engineer was never asked, and the round is on the record as sent back.
        $this->assertSame(ResponseCode::REVISE_RESUBMIT, $approval->revisions()->first()->responseCode->canonical);
    }

    /*
    |---------------------------------------------------------------------------
    | Parallel review
    |---------------------------------------------------------------------------
    */

    public function test_reviewers_sharing_a_sequence_are_asked_together(): void
    {
        $one = $this->person('Estrutura');
        $two = $this->person('Instalações');

        $approval = $this->approval();
        $revision = $approval->submit([
            ['user_id' => $one->id, 'sequence' => 1],
            ['user_id' => $two->id, 'sequence' => 1],
        ], $this->author);

        $this->assertTrue($revision->isWaitingOn($one));
        $this->assertTrue($revision->isWaitingOn($two));
        $this->assertSame(2, $revision->currentReviewers()->count());
    }

    public function test_a_parallel_round_closes_when_both_have_answered(): void
    {
        $one = $this->person('Estrutura');
        $two = $this->person('Instalações');

        $approval = $this->approval();
        $approval->submit([
            ['user_id' => $one->id, 'sequence' => 1],
            ['user_id' => $two->id, 'sequence' => 1],
        ], $this->author);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $one);
        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);

        $approval->fresh()->recordResponse($this->code(ResponseCode::APPROVED), $two);
        $this->assertSame(Approval::APPROVED, $approval->fresh()->status);
    }

    /** Either of two parallel reviewers can send it back on their own. */
    public function test_one_parallel_reviewer_can_send_it_back_alone(): void
    {
        $one = $this->person('Estrutura');
        $two = $this->person('Instalações');

        $approval = $this->approval();
        $approval->submit([
            ['user_id' => $one->id, 'sequence' => 1],
            ['user_id' => $two->id, 'sequence' => 1],
        ], $this->author);

        $approval->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $one, 'Não atende.');

        $this->assertNull($approval->fresh()->openRevision());
        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | Guards on responding
    |---------------------------------------------------------------------------
    */

    public function test_somebody_who_is_not_a_reviewer_cannot_respond(): void
    {
        $reviewer = $this->person('Revisor');
        $outsider = $this->person('Outro');

        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->author);

        $this->expectException(ValidationException::class);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $outsider);
    }

    public function test_a_reviewer_cannot_answer_twice(): void
    {
        $one = $this->person('Estrutura');
        $two = $this->person('Instalações');

        $approval = $this->approval();
        $approval->submit([
            ['user_id' => $one->id, 'sequence' => 1],
            ['user_id' => $two->id, 'sequence' => 1],
        ], $this->author);

        $approval->recordResponse($this->code(ResponseCode::APPROVED), $one);

        $this->expectException(ValidationException::class);

        $approval->fresh()->recordResponse($this->code(ResponseCode::APPROVED), $one);
    }

    public function test_responding_with_no_open_revision_is_refused(): void
    {
        $reviewer = $this->person('Revisor');

        $this->expectException(ValidationException::class);

        $this->approval()->recordResponse($this->code(ResponseCode::APPROVED), $reviewer);
    }

    /*
    |---------------------------------------------------------------------------
    | History
    |---------------------------------------------------------------------------
    */

    public function test_the_cycle_is_written_to_the_history(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();

        $approval->submit([['user_id' => $reviewer->id]], $this->author);
        $approval->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $reviewer);

        $actions = $approval->activity()->pluck('action')->all();

        $this->assertContains(ActivityLogEntry::SUBMITTED, $actions);
        $this->assertContains(ActivityLogEntry::RESPONDED, $actions);

        $responded = $approval->activity()->where('action', ActivityLogEntry::RESPONDED)->first();
        $this->assertSame(ResponseCode::REVISE_RESUBMIT, $responded->context['canonical']);
    }

    /*
    |---------------------------------------------------------------------------
    | Overdue — the card and the rows must agree
    |---------------------------------------------------------------------------
    */

    /**
     * A settled approval is not overdue, whatever its date.
     *
     * `scopeOverdue()` used to hardcode "not closed or void", which are an
     * RFI's settled statuses and not an approval's. An approved approval past
     * its date was therefore counted in the Overdue card while the row below
     * rendered as not overdue — the count and the rows disagreeing.
     */
    public function test_a_settled_approval_is_not_overdue_in_the_scope_or_the_method(): void
    {
        $reviewer = $this->person('Revisor');

        $approved = $this->approval(['title' => 'Aprovada', 'due_date' => now()->subDays(10)->toDateString()]);
        $approved->submit([['user_id' => $reviewer->id]], $this->author);
        $approved->recordResponse($this->code(ResponseCode::APPROVED), $reviewer);

        $rejected = $this->approval(['title' => 'Reprovada', 'due_date' => now()->subDays(10)->toDateString()]);
        $rejected->submit([['user_id' => $reviewer->id]], $this->author);
        $rejected->recordResponse($this->code(ResponseCode::REJECTED), $reviewer);

        $live = $this->approval(['title' => 'Em análise', 'due_date' => now()->subDays(10)->toDateString()]);
        $live->submit([['user_id' => $reviewer->id]], $this->author);

        $overdue = Approval::overdue()->pluck('title')->all();

        // Approved is settled and drops out; in-review and rejected are both
        // still somebody's move, so both stay in.
        $this->assertEqualsCanonicalizing(['Em análise', 'Reprovada'], $overdue);

        // The scope and the method agree on every one of them.
        $this->assertFalse($approved->fresh()->isOverdue());
        $this->assertTrue($live->fresh()->isOverdue());
        $this->assertTrue($rejected->fresh()->isOverdue());
    }

    /** Due today is not late — in the list or on the record. */
    public function test_a_document_due_today_is_not_overdue_either_way(): void
    {
        $today = $this->approval(['title' => 'Hoje', 'due_date' => now()->toDateString()]);
        $yesterday = $this->approval(['title' => 'Ontem', 'due_date' => now()->subDay()->toDateString()]);

        $this->assertFalse($today->fresh()->isOverdue(), 'A document due today is not yet late.');
        $this->assertTrue($yesterday->fresh()->isOverdue());

        $this->assertSame(['Ontem'], Approval::overdue()->pluck('title')->all());
    }

    /*
    |---------------------------------------------------------------------------
    | Signatures survive the workflow
    |---------------------------------------------------------------------------
    */

    /**
     * A signature must not be broken by the cycle carrying on.
     *
     * The payload used to include `status` and `current_revision`, so the very
     * next response reported the signature as broken — on a document nobody
     * had tampered with, which is the opposite of what the hash is for.
     */
    public function test_a_signature_survives_the_next_round(): void
    {
        $reviewer = $this->person('Revisor');
        $approval = $this->approval();

        $approval->submit([['user_id' => $reviewer->id]], $this->author);
        $signature = $approval->fresh()->sign($this->author, 'CREA 12345-D');

        $approval->fresh()->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $reviewer);
        $this->assertTrue($approval->fresh()->signatureIsIntact($signature), 'A response broke the signature.');

        $approval->fresh()->submit([['user_id' => $reviewer->id]], $this->author);
        $this->assertTrue($approval->fresh()->signatureIsIntact($signature), 'A new revision broke the signature.');

        // But changing what was signed still breaks it.
        $approval->fresh()->update(['title' => 'Outro material']);
        $this->assertFalse($approval->fresh()->signatureIsIntact($signature));
    }

    /** Revision letters carry rather than running off the end of the alphabet. */
    public function test_revision_labels_do_not_overflow_past_z(): void
    {
        $approval = $this->approval();
        $approval->revisions()->create(['revision' => 'Z', 'submitted_at' => now(), 'responded_at' => now()]);

        $reviewer = $this->person('Revisor');
        $next = $approval->fresh()->submit([['user_id' => $reviewer->id]], $this->author);

        $this->assertSame('AA', $next->revision);
    }

    /*
    |---------------------------------------------------------------------------
    | Certificates
    |---------------------------------------------------------------------------
    */

    public function test_a_certificate_warns_when_it_has_expired_or_is_about_to(): void
    {
        $expired = $this->approval(['type' => Approval::TYPE_CERTIFICATE]);
        $expired->certificate()->create([
            'issuing_body' => 'INMETRO',
            'certificate_number' => 'ABC-123',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $soon = $this->approval(['type' => Approval::TYPE_CERTIFICATE]);
        $soon->certificate()->create([
            'issuing_body' => 'INMETRO',
            'valid_until' => now()->addDays(10)->toDateString(),
        ]);

        $fine = $this->approval(['type' => Approval::TYPE_CERTIFICATE]);
        $fine->certificate()->create([
            'issuing_body' => 'INMETRO',
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $this->assertTrue($expired->fresh()->certificateNeedsAttention());
        $this->assertTrue($expired->fresh()->certificate->hasExpired());

        $this->assertTrue($soon->fresh()->certificateNeedsAttention());
        $this->assertFalse($soon->fresh()->certificate->hasExpired());

        $this->assertFalse($fine->fresh()->certificateNeedsAttention());
    }

    /** A material has no certificate, and must not claim to need attention. */
    public function test_a_non_certificate_never_needs_certificate_attention(): void
    {
        $this->assertFalse($this->approval()->certificateNeedsAttention());
    }

    /*
    |---------------------------------------------------------------------------
    | Labels and scopes
    |---------------------------------------------------------------------------
    */

    public function test_a_status_or_type_is_never_printed_raw(): void
    {
        $this->assertSame(__('collaboration.approval.status.approved'), Approval::statusLabel(Approval::APPROVED));
        $this->assertSame(__('collaboration.approval.type.certificate'), Approval::typeLabel(Approval::TYPE_CERTIFICATE));
        $this->assertArrayHasKey(Approval::TYPE_SHOP_DRAWING, Approval::typeOptions());
    }

    /** *Aprovação* is feminine — the participle has to agree. */
    public function test_the_pt_br_status_words_agree_with_a_feminine_subject(): void
    {
        $this->app->setLocale('pt_BR');

        $this->assertSame('Aprovada', Approval::statusLabel(Approval::APPROVED));
        $this->assertSame('Reprovada', Approval::statusLabel(Approval::REJECTED));
    }

    public function test_awaiting_review_by_finds_what_is_on_somebodys_desk(): void
    {
        $mine = $this->person('Meu');
        $theirs = $this->person('Outro');

        $a = $this->approval(['title' => 'Comigo']);
        $a->submit([['user_id' => $mine->id]], $this->author);

        $b = $this->approval(['title' => 'Com outro']);
        $b->submit([['user_id' => $theirs->id]], $this->author);

        $c = $this->approval(['title' => 'Já respondida']);
        $c->submit([['user_id' => $mine->id]], $this->author);
        $c->recordResponse($this->code(ResponseCode::APPROVED), $mine);

        $waiting = Approval::awaitingReviewBy($mine)->pluck('title')->all();

        $this->assertSame(['Comigo'], $waiting);
    }
}
