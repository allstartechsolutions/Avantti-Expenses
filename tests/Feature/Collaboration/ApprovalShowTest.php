<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Approval\ApprovalShow;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Collaboration\ResponseCode;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The approval detail page: submitting a round, responding to one, and the
 * history that has to survive both.
 */
class ApprovalShowTest extends TestCase
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
        $this->seed(CollaborationResponseCodeSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Ana Souza',
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
            'created_by' => $this->admin->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'Contact',
            'email' => 'site@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function approval(array $attributes = []): Approval
    {
        return Approval::create(array_merge([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato do hall',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ], $attributes));
    }

    protected function memberWith(string $templateKey, array $override = []): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $template = \App\Models\PermissionTemplate::where('key', $templateKey)->firstOrFail();

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'permission_template_id' => $template->id,
            'can_see_money' => $template->can_see_money,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);

        $membership->syncAbilities(AbilityCatalog::filter(
            $override ?: $template->abilityRows->pluck('ability')->all(),
            'project',
        ));

        return $user;
    }

    protected function code(string $canonical): ResponseCode
    {
        return ResponseCode::offered('approval')->firstWhere('canonical', $canonical);
    }

    /*
    |---------------------------------------------------------------------------
    | What it shows
    |---------------------------------------------------------------------------
    */

    public function test_the_page_shows_the_approval_and_its_record(): void
    {
        $approval = $this->approval(['description' => 'Porcelanato 90x90 acetinado.']);

        $this->actingAs($this->admin)
            ->get(route('approvals.show', $approval))
            ->assertOk()
            ->assertSee($approval->number)
            ->assertSee('Porcelanato do hall')
            ->assertSee('Porcelanato 90x90 acetinado.')
            ->assertSee(__('collaboration.label.raised'))
            ->assertSee('Ana Souza');
    }

    /** Design standard 5 — an empty panel says what to do about it. */
    public function test_an_unsubmitted_approval_says_what_happens_next(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $this->approval()])
            ->assertSee(__('collaboration.message.submitted'))
            ->assertSee(__('collaboration.help.name_who_should_review_submit'));
    }

    /** The history is the substance of the page, not a footnote. */
    public function test_every_round_stays_on_the_page_after_the_next_one(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();

        $approval->submit([['user_id' => $reviewer->id]], $this->admin);
        $approval->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $reviewer, 'Trocar o rejunte.');
        $approval->fresh()->submit([['user_id' => $reviewer->id]], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->assertSee(__('collaboration.label.revision', ['revision' => '0']))
            ->assertSee(__('collaboration.label.revision', ['revision' => '1']))
            // The first round's answer survives the second submission.
            ->assertSee('Trocar o rejunte.');
    }

    public function test_the_page_says_whether_review_is_parallel_or_in_turn(): void
    {
        $a = $this->memberWith('projetista-project');
        $b = $this->memberWith('projetista-project');

        $together = $this->approval(['title' => 'Em conjunto']);
        $together->submit([
            ['user_id' => $a->id, 'sequence' => 1],
            ['user_id' => $b->id, 'sequence' => 1],
        ], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $together])
            ->assertSee(__('collaboration.help.reviewed_together_everyone_same_step'));

        $inTurn = $this->approval(['title' => 'Em sequência']);
        $inTurn->submit([
            ['user_id' => $a->id, 'sequence' => 1],
            ['user_id' => $b->id, 'sequence' => 2],
        ], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $inTurn])
            ->assertSee(__('collaboration.help.reviewed_turn_each_step_waits'));
    }

    public function test_a_lapsing_certificate_is_said_at_the_top(): void
    {
        $approval = $this->approval(['type' => Approval::TYPE_CERTIFICATE]);
        $approval->certificate()->create([
            'issuing_body' => 'INMETRO',
            'certificate_number' => 'ABC-123',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $expiredOn = __('collaboration.message.certificate_expired', [
            'date' => now()->subDay()->appDate(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->assertSee($expiredOn)
            ->assertSee('INMETRO')
            ->assertSee('ABC-123');
    }

    /** A material has no certificate panel to be blank. */
    public function test_a_material_shows_no_certificate_panel(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $this->approval()])
            ->assertDontSee(__('collaboration.label.certificate_number'));
    }

    /*
    |---------------------------------------------------------------------------
    | Submitting
    |---------------------------------------------------------------------------
    */

    public function test_a_revision_can_be_submitted_from_the_page(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('reviewerRows', [['user_id' => (string) $reviewer->id, 'sequence' => 1, 'role' => 'projetista']])
            ->call('submitRevision')
            ->assertHasNoErrors();

        $approval->refresh();
        $this->assertSame('0', $approval->current_revision);
        $this->assertSame(Approval::IN_REVIEW, $approval->status);
        $this->assertSame($reviewer->id, $approval->ball_in_court_id);
    }

    public function test_submitting_with_nobody_named_is_refused_with_a_reason(): void
    {
        $approval = $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('reviewerRows', [['user_id' => '', 'sequence' => 1, 'role' => '']])
            ->call('submitRevision')
            ->assertHasErrors('reviewers');

        $this->assertSame(0, $approval->fresh()->revisions()->count());
    }

    /**
     * A reviewer id came from the browser. Somebody who is not on this project
     * must not be named a reviewer of its work.
     */
    public function test_somebody_not_on_the_project_cannot_be_named_a_reviewer(): void
    {
        $outsider = User::factory()->create(['role_id' => Role::where('name', 'employee')->value('id')]);
        $approval = $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('reviewerRows', [['user_id' => (string) $outsider->id, 'sequence' => 1, 'role' => '']])
            ->call('submitRevision')
            // Filtered out, leaving nothing — so the same refusal as an empty list.
            ->assertHasErrors('reviewers');

        $this->assertSame(0, $approval->fresh()->revisions()->count());
    }

    /** The shortcut for a resubmission, which is the common case. */
    public function test_the_last_rounds_reviewers_can_be_reused(): void
    {
        $a = $this->memberWith('projetista-project');
        $b = $this->memberWith('projetista-project');
        $approval = $this->approval();

        $approval->submit([
            ['user_id' => $a->id, 'sequence' => 1, 'role' => 'projetista'],
            ['user_id' => $b->id, 'sequence' => 2],
        ], $this->admin);
        $approval->recordResponse($this->code(ResponseCode::REVISE_RESUBMIT), $a);

        $rows = Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->call('reuseLastReviewers')
            ->get('reviewerRows');

        $this->assertCount(2, $rows);
        $this->assertSame((string) $a->id, $rows[0]['user_id']);
        $this->assertSame('projetista', $rows[0]['role']);
        $this->assertSame(2, $rows[1]['sequence']);
    }

    public function test_submitting_is_not_offered_while_a_round_is_out(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()]);

        $this->assertFalse($component->instance()->canSubmit);
    }

    /*
    |---------------------------------------------------------------------------
    | Responding
    |---------------------------------------------------------------------------
    */

    public function test_a_reviewer_records_a_response(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        Livewire::actingAs($reviewer)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->set('responseCodeId', (string) $this->code(ResponseCode::APPROVED)->id)
            ->set('responseComments', 'Conforme a especificação.')
            ->call('recordResponse')
            ->assertHasNoErrors();

        $this->assertSame(Approval::APPROVED, $approval->fresh()->status);
        $this->assertSame('Conforme a especificação.', $approval->fresh()->revisions()->first()->comments);
    }

    public function test_a_send_back_from_the_page_opens_the_next_round(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        Livewire::actingAs($reviewer)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->set('responseCodeId', (string) $this->code(ResponseCode::REVISE_RESUBMIT)->id)
            ->set('responseComments', 'Rever o detalhe.')
            ->call('recordResponse')
            ->assertHasNoErrors();

        $approval->refresh();
        $this->assertSame(Approval::IN_REVIEW, $approval->status);
        $this->assertNull($approval->openRevision());

        // And the raiser can put the next one forward.
        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('reviewerRows', [['user_id' => (string) $reviewer->id, 'sequence' => 1, 'role' => '']])
            ->call('submitRevision')
            ->assertHasNoErrors();

        $this->assertSame('1', $approval->fresh()->current_revision);
    }

    /**
     * Holding `approvals.respond` across a project is not the same as being a
     * reviewer of this round.
     */
    public function test_somebody_who_is_not_a_reviewer_cannot_respond(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $other = $this->memberWith('projetista-project');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        $component = Livewire::actingAs($other)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()]);

        $this->assertFalse($component->instance()->canRespond);

        $component
            ->set('responseCodeId', (string) $this->code(ResponseCode::APPROVED)->id)
            ->call('recordResponse')
            ->assertHasErrors('response');

        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);
    }

    public function test_a_reviewer_further_down_the_chain_cannot_answer_early(): void
    {
        $first = $this->memberWith('projetista-project');
        $second = $this->memberWith('projetista-project');
        $approval = $this->approval();

        $approval->submit([
            ['user_id' => $first->id, 'sequence' => 1],
            ['user_id' => $second->id, 'sequence' => 2],
        ], $this->admin);

        Livewire::actingAs($second)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->set('responseCodeId', (string) $this->code(ResponseCode::APPROVED)->id)
            ->call('recordResponse')
            ->assertHasErrors('response');
    }

    /** A response code id from the browser must be one actually on offer. */
    public function test_an_unknown_response_code_is_refused(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        Livewire::actingAs($reviewer)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->set('responseCodeId', '999999')
            ->call('recordResponse')
            ->assertStatus(422);
    }

    /*
    |---------------------------------------------------------------------------
    | Guards
    |---------------------------------------------------------------------------
    */

    public function test_a_stranger_cannot_open_it(): void
    {
        $stranger = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Livewire::actingAs($stranger)
            ->test(ApprovalShow::class, ['approval' => $this->approval()])
            ->assertForbidden();
    }

    public function test_a_member_of_another_project_cannot_open_it(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $theirs = $this->approval(['project_id' => $other->id]);

        Livewire::actingAs($this->memberWith('projetista-project'))
            ->test(ApprovalShow::class, ['approval' => $theirs])
            ->assertForbidden();
    }

    /** The projetista template holds respond, not submit. */
    public function test_submitting_is_refused_without_the_grant(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $approval = $this->approval();

        Livewire::actingAs($projetista)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('reviewerRows', [['user_id' => (string) $projetista->id, 'sequence' => 1, 'role' => '']])
            ->call('submitRevision')
            ->assertForbidden();

        $this->assertSame(0, $approval->fresh()->revisions()->count());
    }

    public function test_responding_is_refused_without_the_grant(): void
    {
        // A viewer who is even named a reviewer still needs the grant.
        $viewer = $this->memberWith('projetista-project', ['project.view', 'approvals.view']);
        $approval = $this->approval();
        $approval->submit([['user_id' => $viewer->id]], $this->admin);

        Livewire::actingAs($viewer)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->set('responseCodeId', (string) $this->code(ResponseCode::APPROVED)->id)
            ->call('recordResponse')
            ->assertForbidden();

        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | Locale
    |---------------------------------------------------------------------------
    */

    public function test_the_page_renders_in_pt_br_with_its_words_translated(): void
    {
        $this->app->setLocale('pt_BR');

        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);
        $approval->recordResponse($this->code(ResponseCode::APPROVED), $reviewer);

        $this->actingAs($this->admin)
            ->get(route('approvals.show', $approval->fresh()))
            ->assertOk()
            ->assertSee('Aprovada')          // status, feminine
            ->assertSee('Revisões')
            ->assertSee('Revisores')
            ->assertSee('Aberta por')
            ->assertDontSee('Reviewers');
    }
}
