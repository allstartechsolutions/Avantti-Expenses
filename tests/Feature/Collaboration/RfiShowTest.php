<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Rfi\RfiShow;
use App\Models\Client;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The RFI detail page: what it shows, what each action does, and — the part
 * that matters — that every action refuses somebody who lacks its grant even
 * when the button that calls it was never rendered.
 */
class RfiShowTest extends TestCase
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

    protected function rfi(array $attributes = []): Rfi
    {
        return Rfi::create(array_merge([
            'project_id' => $this->project->id,
            'subject' => 'Detalhe da esquadria',
            'question' => 'Qual perfil usar no caixilho?',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ], $attributes));
    }

    protected function memberWith(string $templateKey, ?Project $project = null): User
    {
        $project ??= $this->project;

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $template = PermissionTemplate::where('key', $templateKey)->firstOrFail();

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'permission_template_id' => $template->id,
            'can_see_money' => $template->can_see_money,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);

        $membership->syncAbilities(
            AbilityCatalog::filter($template->abilityRows->pluck('ability')->all(), 'project')
        );

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | What it shows
    |---------------------------------------------------------------------------
    */

    public function test_the_page_shows_the_question_and_the_record(): void
    {
        $rfi = $this->rfi([
            'discipline' => 'Arquitetura',
            'drawing_ref' => 'ARQ-04 rev.C',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('rfis.show', $rfi))
            ->assertOk()
            ->assertSee($rfi->number)
            ->assertSee('Detalhe da esquadria')
            ->assertSee('Qual perfil usar no caixilho?')
            ->assertSee('Arquitetura')
            ->assertSee('Obra Central')
            ->assertSee(__('collaboration.label.raised'))
            ->assertSee('Ana Souza');
    }

    /** Design standard 5: an empty panel says what is missing, never nothing. */
    public function test_an_unanswered_rfi_says_so_and_says_who_it_waits_on(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi(['ball_in_court_id' => $projetista->id]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->assertSee(__('collaboration.message.answered'))
            ->assertSee($projetista->name);
    }

    public function test_an_unassigned_rfi_says_nobody_has_been_asked(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->assertSee(__('collaboration.help.nobody_been_asked_set_who'));
    }

    public function test_empty_panels_are_designed_rather_than_blank(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->assertSee(__('collaboration.message.nobody_copied_rfi'))
            ->assertSee(__('collaboration.message.files_attached'));
    }

    /** Opening it is recorded — that is what the log is for. */
    public function test_opening_the_page_records_a_view(): void
    {
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)->test(RfiShow::class, ['rfi' => $rfi]);

        $this->assertSame(
            1,
            $rfi->activity()->where('action', ActivityLogEntry::VIEWED)->count(),
        );
    }

    /**
     * The screen in Portuguese, which is the locale most of these installs run.
     *
     * Renders the whole page rather than checking a label in isolation: a key
     * that was never added to pt_BR.json falls back to its English source
     * silently, and only a real render catches it.
     */
    public function test_the_page_renders_in_pt_br_with_its_words_translated(): void
    {
        $this->app->setLocale('pt_BR');

        $rfi = $this->rfi(['discipline' => 'Arquitetura']);
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->close();

        $this->actingAs($this->admin)
            ->get(route('rfis.show', $rfi))
            ->assertOk()
            ->assertSee('Encerrada')            // status, feminine
            ->assertSee('Histórico')            // History
            ->assertSee('Detalhes')             // Details
            ->assertSee('Aberta por')           // Raised by
            ->assertSee('Distribuição')         // Distribution
            ->assertDontSee('Raised by')
            ->assertDontSee('History');
    }

    /*
    |---------------------------------------------------------------------------
    | Answering, closing, reopening
    |---------------------------------------------------------------------------
    */

    public function test_an_answer_can_be_recorded(): void
    {
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('answerText', 'Perfil série 25.')
            ->call('recordAnswer')
            ->assertHasNoErrors();

        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);
        $this->assertSame(Rfi::ANSWERED, $rfi->fresh()->status);
    }

    public function test_an_empty_answer_is_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->set('answerText', '')
            ->call('recordAnswer')
            ->assertHasErrors('answerText');
    }

    public function test_an_answered_rfi_can_be_closed(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('close')
            ->assertHasNoErrors();

        $this->assertSame(Rfi::CLOSED, $rfi->fresh()->status);
    }

    /** The screen must not promise something the record refuses. */
    public function test_an_unanswered_rfi_cannot_be_closed(): void
    {
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('close')
            ->assertHasErrors('answerText');

        $this->assertSame(Rfi::OPEN, $rfi->fresh()->status);
    }

    public function test_a_closed_rfi_shows_that_its_answer_is_frozen(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee(__('collaboration.label.frozen_rfi_closed'));
    }

    public function test_a_closed_rfi_can_be_reopened_and_answered_again(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('reopen')
            ->assertHasNoErrors()
            ->set('answerText', 'Perfil série 30.')
            ->call('recordAnswer')
            ->assertHasNoErrors();

        // A second reply is kept beside the first rather than replacing it —
        // which of them counts is a decision, not a side effect of typing.
        $this->assertSame(2, $rfi->fresh()->replies()->count());
        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);

        $newest = $rfi->fresh()->replies()->first();
        $rfi->fresh()->markReplyValid($newest, $this->admin);

        $this->assertSame('Perfil série 30.', $rfi->fresh()->answer);
    }

    /*
    |---------------------------------------------------------------------------
    | The correction
    |---------------------------------------------------------------------------
    */

    /** Correcting a closed SI still has to be explicable. */
    public function test_a_correction_needs_a_reason(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('startEditingReply', $rfi->fresh()->valid_reply_id)
            ->set('editingReplyBody', 'Perfil série 30.')
            ->set('editingReplyReason', '')
            ->call('saveReplyEdit')
            ->assertHasErrors('editingReplyReason');

        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);
    }

    /** While it is open, editing your own words needs no ceremony. */
    public function test_an_open_rfi_needs_no_reason_to_edit_its_reply(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('startEditingReply', $rfi->fresh()->valid_reply_id)
            ->set('editingReplyBody', 'Perfil série 30.')
            ->call('saveReplyEdit')
            ->assertHasNoErrors();

        $this->assertSame('Perfil série 30.', $rfi->fresh()->answer);
    }

    public function test_a_correction_is_recorded_and_shown_in_the_history(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('startEditingReply', $rfi->fresh()->valid_reply_id)
            ->set('editingReplyBody', 'Perfil série 30.')
            ->set('editingReplyReason', 'Série trocada após medição.')
            ->call('saveReplyEdit')
            ->assertHasNoErrors();

        $rfi->refresh();
        $this->assertSame('Perfil série 30.', $rfi->answer);

        // The change is on the record: what it said before, and why.
        $entry = $rfi->activity()->where('action', \App\Models\Collaboration\ActivityLogEntry::REVISED)->first();
        $this->assertSame('Perfil série 25.', $entry->context['previous_answer']);
        $this->assertSame('Série trocada após medição.', $entry->context['reason']);

        // And the reply itself is stamped as edited.
        $this->assertTrue($rfi->validReply->wasEdited());
    }

    /*
    |---------------------------------------------------------------------------
    | Ball in court
    |---------------------------------------------------------------------------
    */

    public function test_the_rfi_can_be_handed_to_a_project_member(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('passToUserId', (string) $projetista->id)
            ->set('passToDueDate', now()->addDays(7)->toDateString())
            ->call('passBall')
            ->assertHasNoErrors();

        $this->assertSame($projetista->id, $rfi->fresh()->ball_in_court_id);
        $this->assertSame(7, $rfi->fresh()->daysRemaining());
    }

    /** Offering every user in the company would be a staff directory. */
    public function test_only_people_on_this_project_are_offered(): void
    {
        $onProject = $this->memberWith('projetista-project');

        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);
        $elsewhere = $this->memberWith('projetista-project', $other);

        $offered = Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->viewData('assignableUsers');

        $this->assertArrayHasKey($onProject->id, $offered);
        $this->assertArrayNotHasKey($elsewhere->id, $offered);
    }

    /**
     * Reopening checks the state, not only the grant.
     *
     * The button is only rendered on a closed RFI, but the `wire:click` behind
     * it is a public endpoint. Reopening a draft used to set it to "answered"
     * with no answer — a state no transition should produce, and one `close()`
     * then refuses, leaving the record stuck.
     */
    public function test_reopening_something_that_is_not_closed_is_refused(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('reopen')
            ->assertHasErrors('answerText');

        $this->assertSame(Rfi::OPEN, $rfi->fresh()->status);
        $this->assertNull($rfi->fresh()->answered_at);
    }

    /**
     * The ball may only be passed to somebody on this project — `exists` is
     * not belonging, and the holder's name goes out on the distributed PDF.
     */
    public function test_the_ball_cannot_be_passed_to_somebody_off_the_project(): void
    {
        $outsider = User::factory()->create(['role_id' => Role::where('name', 'employee')->value('id')]);
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('passToUserId', (string) $outsider->id)
            ->call('passBall')
            ->assertNotFound();

        $this->assertNull($rfi->fresh()->ball_in_court_id);
    }

    /*
    |---------------------------------------------------------------------------
    | The aditivo, and which answer justified it
    |---------------------------------------------------------------------------
    */

    /**
     * Linking copies the answer across, and that copy is the point.
     *
     * A change order is argued from the answer as it read when it was raised.
     * An RFI can be corrected afterwards by somebody holding `rfis.revise`, and
     * a justification that silently rewrote itself is worth nothing later.
     */
    public function test_linking_a_change_order_keeps_the_answer_that_justified_it(): void
    {
        $rfi = $this->rfi(['cost_impact' => true]);
        $rfi->recordAnswer('Usar perfil série 25.', $this->admin);
        $rfi->close();

        $changeOrder = \App\Models\ChangeOrder::create([
            'project_id' => $this->project->id,
            'co_number' => 'AD-001',
            'title' => 'Fachada',
            'requested_date' => now()->toDateString(),
            'amount' => 12500,
            'created_by' => $this->admin->id,
        ]);

        $rfi->fresh()->linkChangeOrder($changeOrder);
        $rfi->refresh();

        $this->assertSame($changeOrder->id, $rfi->change_order_id);
        $this->assertSame('Usar perfil série 25.', $rfi->change_order_answer);
        $this->assertNotNull($rfi->change_order_linked_at);
        $this->assertFalse($rfi->answerChangedSinceChangeOrder());

        // The reverse direction resolves too.
        $this->assertSame($rfi->id, $changeOrder->fresh()->rfi->id);

        // Correct the answer, and the two now differ — which the page says.
        $rfi->revise('Usar perfil série 30.', 'Medição em obra.', $this->admin);

        $this->assertTrue($rfi->fresh()->answerChangedSinceChangeOrder());
        $this->assertSame('Usar perfil série 25.', $rfi->fresh()->change_order_answer);
    }

    public function test_the_page_says_when_the_answer_moved_after_the_change_order(): void
    {
        $rfi = $this->rfi(['cost_impact' => true]);
        $rfi->recordAnswer('Usar perfil série 25.', $this->admin);
        $rfi->close();

        $changeOrder = \App\Models\ChangeOrder::create([
            'project_id' => $this->project->id,
            'co_number' => 'AD-001',
            'title' => 'Fachada',
            'requested_date' => now()->toDateString(),
            'amount' => 12500,
            'created_by' => $this->admin->id,
        ]);
        $rfi->fresh()->linkChangeOrder($changeOrder);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee('AD-001')
            ->assertDontSee(__('collaboration.help.answer_corrected_since'));

        $rfi->fresh()->revise('Usar perfil série 30.', 'Medição em obra.', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee(__('collaboration.help.answer_corrected_since'))
            // and the wording the aditivo was actually raised from.
            ->assertSee('Usar perfil série 25.');
    }

    /** An RFI already covered stops offering to raise another. */
    public function test_a_linked_rfi_no_longer_offers_a_change_order(): void
    {
        $rfi = $this->rfi(['cost_impact' => true]);
        $rfi->recordAnswer('Resposta.', $this->admin);
        $rfi->close();

        $changeOrder = \App\Models\ChangeOrder::create([
            'project_id' => $this->project->id,
            'co_number' => 'AD-001',
            'title' => 'Fachada',
            'requested_date' => now()->toDateString(),
            'amount' => 12500,
            'created_by' => $this->admin->id,
        ]);
        $rfi->fresh()->linkChangeOrder($changeOrder);

        $this->assertFalse($rfi->fresh()->suggestsChangeOrder());

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertDontSee(__('collaboration.help.this_rfi_closed_with_impact_recorded'));
    }

    /**
     * The change-order form arrives carrying what the RFI knows.
     *
     * Pre-filled, not created: the guardrail is that every money-touching
     * artifact is confirmed by a person, so the fields are filled in and the
     * form waits.
     */
    public function test_the_change_order_form_opens_pre_filled_from_the_rfi(): void
    {
        $rfi = $this->rfi([
            'subject' => 'Detalhe da esquadria',
            'cost_impact' => true,
            'cost_impact_amount' => 12500,
        ]);
        $rfi->recordAnswer('Usar perfil série 30.', $this->admin);

        $component = Livewire::actingAs($this->admin)
            ->withQueryParams(['fromRfi' => $rfi->id])
            ->test(\App\Livewire\Project\ProjectChangeOrders::class, ['project' => $this->project]);

        $component->assertSet('co_fromRfi', $rfi->id);
        $this->assertStringContainsString($rfi->fresh()->number, $component->get('co_title'));
        $this->assertStringContainsString('Usar perfil série 30.', $component->get('co_description'));
        $this->assertSame('12500', (string) $component->get('co_amount'));

        // Nothing has been created by merely arriving.
        $this->assertSame(0, \App\Models\ChangeOrder::count());
    }

    /** Saving it is what links the two. */
    public function test_saving_that_form_links_the_change_order_to_the_rfi(): void
    {
        $rfi = $this->rfi(['cost_impact' => true, 'cost_impact_amount' => 12500]);
        $rfi->recordAnswer('Usar perfil série 30.', $this->admin);

        Livewire::actingAs($this->admin)
            ->withQueryParams(['fromRfi' => $rfi->id])
            ->test(\App\Livewire\Project\ProjectChangeOrders::class, ['project' => $this->project])
            ->set('co_requested_date', now()->toDateString())
            ->call('saveChangeOrder')
            ->assertHasNoErrors();

        $changeOrder = \App\Models\ChangeOrder::first();
        $rfi->refresh();

        $this->assertNotNull($changeOrder);
        $this->assertSame($changeOrder->id, $rfi->change_order_id);
        $this->assertSame('Usar perfil série 30.', $rfi->change_order_answer);
    }

    /**
     * The id arrives in a URL, so it must be an RFI on this project that this
     * person may read — otherwise the form would leak another project's
     * subject and answer into a pre-filled field.
     */
    public function test_an_rfi_from_another_project_does_not_pre_fill_anything(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $theirs = Rfi::create([
            'project_id' => $other->id,
            'subject' => 'Segredo de outra obra',
            'question' => 'Pergunta',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->withQueryParams(['fromRfi' => $theirs->id])
            ->test(\App\Livewire\Project\ProjectChangeOrders::class, ['project' => $this->project]);

        $component->assertSet('co_fromRfi', null);
        $this->assertStringNotContainsString('Segredo de outra obra', (string) $component->get('co_title'));
    }

    /*
    |---------------------------------------------------------------------------
    | Which answer counts
    |---------------------------------------------------------------------------
    */

    /**
     * Several people answer an SI, and one of the answers is the one the work
     * is built to. The page marks it and lets somebody change the choice.
     */
    public function test_replies_accumulate_and_the_valid_one_is_marked(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();

        $first = $rfi->addReply('Perfil série 25.', $projetista);

        // The first reply is the answer by default — with one there is nothing
        // to choose between.
        $this->assertSame($first->id, $rfi->fresh()->valid_reply_id);
        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);

        // A second person answers. It does NOT take over on its own.
        $second = $rfi->fresh()->addReply('Verificar carga antes de trocar.', $this->admin);

        $this->assertSame(2, $rfi->fresh()->replies()->count());
        $this->assertSame($first->id, $rfi->fresh()->valid_reply_id);
        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);

        // Both are on the page, and the newest-is-not-valid warning shows.
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee('Perfil série 25.')
            ->assertSee('Verificar carga antes de trocar.')
            ->assertSee(__('collaboration.help.newer_reply_is_not_the_valid_one'));

        // Choosing the second makes it the answer, and the warning clears.
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('chooseReply', $second->id)
            ->assertHasNoErrors()
            ->assertDontSee(__('collaboration.help.newer_reply_is_not_the_valid_one'));

        $this->assertSame($second->id, $rfi->fresh()->valid_reply_id);
        $this->assertSame('Verificar carga antes de trocar.', $rfi->fresh()->answer);
    }

    /** A reply can be corrected, and says that it was. */
    public function test_a_reply_can_be_edited_and_the_edit_is_shown(): void
    {
        $rfi = $this->rfi();
        $reply = $rfi->addReply('Perfil série 25.', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('startEditingReply', $reply->id)
            ->assertSet('editingReplyBody', 'Perfil série 25.')
            ->set('editingReplyBody', 'Perfil série 30.')
            ->call('saveReplyEdit')
            ->assertHasNoErrors();

        $reply->refresh();
        $this->assertSame('Perfil série 30.', $reply->body);
        $this->assertTrue($reply->wasEdited());
        $this->assertSame($this->admin->id, $reply->edited_by_id);

        // It was the valid reply, so the answer follows the edit.
        $this->assertSame('Perfil série 30.', $rfi->fresh()->answer);
    }

    /**
     * A reply carries its own files.
     *
     * The projetista answers with a marked-up prancha; the file belongs to
     * what was said, not to the SI as a whole — otherwise three replies later
     * nobody knows which drawing went with which answer.
     */
    /**
     * A second drop adds to an answer's files.
     *
     * The box the drop zone writes to is emptied on every change, so two drags
     * both arrive — Livewire's `uploadMultiple` runs with `append = false`, and
     * binding it straight to the queue lost the first batch in silence.
     */
    public function test_files_dropped_in_two_goes_all_go_up_with_the_answer(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('documents.disk', 'local'));

        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('answerText', 'Ver os dois anexos.')
            ->set('newReplyUploads', [\Illuminate\Http\UploadedFile::fake()->create('prancha.pdf', 20, 'application/pdf')])
            ->assertSet('newReplyUploads', [])
            ->set('newReplyUploads', [\Illuminate\Http\UploadedFile::fake()->create('detalhe.pdf', 20, 'application/pdf')])
            ->assertCount('replyUploads', 2)
            ->call('recordAnswer')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['prancha.pdf', 'detalhe.pdf'],
            $rfi->fresh()->replies()->first()->availableFiles()->pluck('original_name')->all(),
        );
    }

    public function test_a_reply_carries_its_own_attachments(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('documents.disk', 'local'));

        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('answerText', 'Usar o detalhe anexo.')
            ->set('replyUploads', [
                \Illuminate\Http\UploadedFile::fake()->create('prancha-marcada.pdf', 60, 'application/pdf'),
            ])
            ->call('recordAnswer')
            ->assertHasNoErrors();

        $reply = $rfi->fresh()->replies()->first();

        $this->assertSame(['prancha-marcada.pdf'], $reply->availableFiles()->pluck('original_name')->all());
        // It hangs on the reply, not on the SI.
        $this->assertSame(0, $rfi->fresh()->availableFiles()->count());

        // And it is reachable from the page.
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee('prancha-marcada.pdf');
    }

    /** The download guard accepts a reply's file — and nothing else. */
    public function test_a_reply_attachment_can_be_downloaded_but_another_rfis_cannot(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('documents.disk', 'local'));

        $rfi = $this->rfi();
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('answerText', 'Resposta.')
            ->set('replyUploads', [\Illuminate\Http\UploadedFile::fake()->create('meu.pdf', 10, 'application/pdf')])
            ->call('recordAnswer');

        $mine = $rfi->fresh()->replies()->first()->availableFiles()->first();

        $other = $this->rfi(['subject' => 'Outra']);
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $other])
            ->set('answerText', 'Resposta.')
            ->set('replyUploads', [\Illuminate\Http\UploadedFile::fake()->create('alheio.pdf', 10, 'application/pdf')])
            ->call('recordAnswer');

        $theirs = $other->fresh()->replies()->first()->availableFiles()->first();

        // Own reply's file: served.
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('downloadFile', $mine->id)
            ->assertOk();

        // Another SI's reply file, asked for from this page: refused.
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('downloadFile', $theirs->id)
            ->assertNotFound();
    }

    /** Somebody else's words are not yours to rewrite without `rfis.revise`. */
    public function test_a_reply_by_somebody_else_cannot_be_edited_without_the_grant(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();
        $mine = $rfi->addReply('Minha resposta.', $projetista);
        $theirs = $rfi->fresh()->addReply('Resposta do admin.', $this->admin);

        $component = Livewire::actingAs($projetista)->test(RfiShow::class, ['rfi' => $rfi->fresh()]);

        // Their own, yes.
        $this->assertTrue($component->instance()->canEditReply($mine->fresh()));
        // Somebody else's, no — and the endpoint refuses, not just the button.
        $this->assertFalse($component->instance()->canEditReply($theirs->fresh()));

        $component->call('startEditingReply', $theirs->id)->assertForbidden();
    }

    /** Choosing which answer counts follows the authority to close. */
    public function test_choosing_the_valid_reply_is_refused_without_the_grant(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();
        $rfi->addReply('Primeira.', $this->admin);
        $second = $rfi->fresh()->addReply('Segunda.', $projetista);

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('chooseReply', $second->id)
            ->assertForbidden();

        $this->assertSame('Primeira.', $rfi->fresh()->answer);
    }

    /** A reply id from the browser must belong to this SI. */
    public function test_a_reply_from_another_rfi_cannot_be_chosen(): void
    {
        $rfi = $this->rfi();
        $rfi->addReply('Desta SI.', $this->admin);

        $other = $this->rfi(['subject' => 'Outra']);
        $foreign = $other->addReply('De outra SI.', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('chooseReply', $foreign->id)
            ->assertNotFound();

        $this->assertSame('Desta SI.', $rfi->fresh()->answer);
    }

    /*
    |---------------------------------------------------------------------------
    | Guards — the part that matters
    |---------------------------------------------------------------------------
    | A hidden button is not protection. Each of these calls the action
    | directly, as the browser can.
    */

    public function test_a_stranger_cannot_open_it(): void
    {
        $stranger = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Livewire::actingAs($stranger)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->assertForbidden();
    }

    /**
     * The id came from the browser; existing is not the same as being allowed.
     * A member of one project must not reach another project's RFI.
     */
    public function test_a_member_of_another_project_cannot_open_it(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $elsewhere = $this->memberWith('projetista-project', $other);

        Livewire::actingAs($elsewhere)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->assertForbidden();
    }

    public function test_closing_is_refused_without_the_grant(): void
    {
        // The projetista template holds rfis.answer but not rfis.close.
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();
        $rfi->recordAnswer('Resposta.', $this->admin);

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('close')
            ->assertForbidden();

        $this->assertSame(Rfi::ANSWERED, $rfi->fresh()->status);
    }

    public function test_correcting_is_refused_without_the_grant(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();
        $rfi->recordAnswer('Resposta.', $this->admin);
        $rfi->close();

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('startEditingReply', $rfi->fresh()->valid_reply_id)
            ->assertForbidden();

        $this->assertSame('Resposta.', $rfi->fresh()->answer);
    }

    public function test_handing_it_on_is_refused_without_the_grant(): void
    {
        // projetista holds rfis.view and rfis.answer, not rfis.edit.
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('passToUserId', (string) $projetista->id)
            ->call('passBall')
            ->assertForbidden();

        $this->assertNull($rfi->fresh()->ball_in_court_id);
    }

    /** What the projetista template exists to allow. */
    public function test_the_projetista_can_answer(): void
    {
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi(['ball_in_court_id' => $projetista->id]);

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->assertOk()
            ->set('answerText', 'Usar o perfil da série 25.')
            ->call('recordAnswer')
            ->assertHasNoErrors();

        $this->assertSame('Usar o perfil da série 25.', $rfi->fresh()->answer);
    }

    /*
    |---------------------------------------------------------------------------
    | Impact
    |---------------------------------------------------------------------------
    */

    public function test_impact_is_hidden_from_somebody_without_the_grant(): void
    {
        $rfi = $this->rfi(['cost_impact' => true, 'schedule_impact' => true, 'schedule_impact_days' => 10]);
        $projetista = $this->memberWith('projetista-project');

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->assertOk()
            ->assertSee('Detalhe da esquadria')
            ->assertDontSee(__('collaboration.label.cost_impact'))
            ->assertDontSee(__('collaboration.label.schedule_impact'));
    }

    public function test_impact_is_shown_with_the_grant(): void
    {
        $rfi = $this->rfi(['cost_impact' => true, 'schedule_impact' => true, 'schedule_impact_days' => 10]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->assertSee(__('collaboration.label.cost_impact'))
            ->assertSee(__('collaboration.label.schedule_impact'));
    }

    /** Offered on close, never created. */
    public function test_a_closed_impacting_rfi_offers_a_change_order_without_making_one(): void
    {
        $rfi = $this->rfi(['cost_impact' => true]);
        $rfi->recordAnswer('Resposta.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee(__('collaboration.help.rfi_closed_impact_recorded_change'))
            ->assertSee(__('collaboration.label.create_change_order'));

        $this->assertSame(0, \App\Models\ChangeOrder::count());
        $this->assertNull($rfi->fresh()->change_order_id);
    }

    public function test_the_offer_is_not_made_when_there_is_no_impact(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Resposta.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertDontSee(__('collaboration.help.rfi_closed_impact_recorded_change'));
    }
}
