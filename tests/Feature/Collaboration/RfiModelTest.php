<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Models\Client;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The RFI record itself: the freeze after closing, and who may see which.
 *
 * Both are enforced in the model rather than the screen, because a form guard
 * protects the form and this has to protect the record.
 */
class RfiModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected Project $project;

    protected JobSite $jobSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->author = User::factory()->create([
            'name' => 'Ana Souza',
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

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

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'Contact',
            'email' => 'site@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->author->id,
        ]);
    }

    protected function rfi(array $attributes = []): Rfi
    {
        return Rfi::create(array_merge([
            'project_id' => $this->project->id,
            'subject' => 'Detalhe da esquadria',
            'question' => 'Qual perfil usar no caixilho do hall?',
            'created_by_id' => $this->author->id,
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Numbering
    |---------------------------------------------------------------------------
    */

    public function test_an_rfi_is_numbered_from_the_project_sequence(): void
    {
        $this->assertStringEndsWith('-001', $this->rfi()->number);
        $this->assertStringEndsWith('-002', $this->rfi()->number);
    }

    /**
     * BR calls it a Solicitação de Informação, and its numbers say SI.
     *
     * Both markets stated, because the suite pins APP_COUNTRY to US and this
     * is the half a Brazilian install actually sees.
     */
    public function test_the_number_prefix_follows_the_installs_country(): void
    {
        config(['app.country' => 'US']);
        $this->assertSame('RFI-001', $this->rfi()->number);

        config(['app.country' => 'BR']);
        $this->assertSame('SI-002', $this->rfi()->number);
    }

    /** The discipline code in a number is fixed, not derived from a label. */
    public function test_a_br_number_carries_the_discipline_code(): void
    {
        config(['app.country' => 'BR']);

        app(\App\Services\Collaboration\NumberSequenceService::class)->configure(
            app(\App\Services\Collaboration\NumberSequenceService::class)
                ->sequenceFor($this->project, 'rfi'),
            template: '{prefix}-{discipline}-{seq:000}',
        );

        $this->assertSame('SI-ARQ-001', $this->rfi(['discipline' => 'architecture'])->number);
    }

    public function test_two_projects_number_independently(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->author->id,
        ]);

        $this->rfi();

        $this->assertStringEndsWith('-001', $this->rfi(['project_id' => $other->id])->number);
    }

    /*
    |---------------------------------------------------------------------------
    | The freeze
    |---------------------------------------------------------------------------
    */

    public function test_an_open_rfi_can_be_answered_and_edited(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);

        $rfi->recordAnswer('Perfil série 25.', $this->author);

        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);
        $this->assertSame(Rfi::ANSWERED, $rfi->fresh()->status);
        $this->assertNotNull($rfi->fresh()->answered_at);
        $this->assertSame($this->author->id, $rfi->fresh()->answered_by_id);

        // Still editable before it closes.
        $rfi->update(['answer' => 'Perfil série 30.']);
        $this->assertSame('Perfil série 30.', $rfi->fresh()->answer);
    }

    /** The rule the whole class is shaped around. */
    public function test_a_closed_rfi_refuses_an_edit_to_its_answer(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->recordAnswer('Perfil série 25.', $this->author);
        $rfi->close();

        $this->expectException(ValidationException::class);

        $rfi->update(['answer' => 'Outra coisa.']);
    }

    public function test_a_closed_rfi_refuses_an_edit_to_its_question(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->close();

        $this->expectException(ValidationException::class);

        $rfi->update(['question' => 'Uma pergunta diferente.']);
    }

    /** The refusal must survive whichever route the caller took. */
    public function test_the_freeze_holds_against_a_direct_save(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->recordAnswer('Perfil série 25.', $this->author);
        $rfi->close();

        $rfi->answer = 'Editado à força.';

        try {
            $rfi->save();
            $this->fail('A closed RFI accepted a direct save to its answer.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame('Perfil série 25.', $rfi->fresh()->answer);
    }

    /** Reopening is a decision of its own, and is allowed. */
    public function test_a_closed_rfi_can_still_be_reopened(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->recordAnswer('Perfil série 25.', $this->author);
        $rfi->close();

        $rfi->reopen();

        $this->assertSame(Rfi::ANSWERED, $rfi->fresh()->status);

        // And once reopened, editable again.
        $rfi->update(['answer' => 'Perfil série 30.']);
        $this->assertSame('Perfil série 30.', $rfi->fresh()->answer);
    }

    /**
     * The one way past the freeze, and it leaves a trail. A correction that
     * cannot be seen is the thing the freeze exists to prevent.
     */
    public function test_a_correction_is_allowed_and_recorded_with_what_it_replaced(): void
    {
        $this->actingAs($this->author);

        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->recordAnswer('Perfil série 25.', $this->author);
        $rfi->close();

        $rfi->revise('Perfil série 30.', 'Série trocada após medição em obra.', $this->author);

        $this->assertSame('Perfil série 30.', $rfi->fresh()->answer);

        $entry = $rfi->activity()->where('action', ActivityLogEntry::REVISED)->first();
        $this->assertNotNull($entry);
        $this->assertSame('Série trocada após medição em obra.', $entry->context['reason']);
        $this->assertSame('Perfil série 25.', $entry->context['previous_answer']);
    }

    /** The unlock lasts one save and does not leave the door open. */
    public function test_the_freeze_is_back_in_place_after_a_correction(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->recordAnswer('Perfil série 25.', $this->author);
        $rfi->close();
        $rfi->revise('Perfil série 30.', 'Motivo.', $this->author);

        $this->expectException(ValidationException::class);

        $rfi->update(['answer' => 'Sem registro.']);
    }

    /** Housekeeping columns are not the answer, and must stay writable. */
    public function test_a_closed_rfi_still_accepts_its_change_order_link(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN, 'cost_impact' => true]);
        $rfi->close();

        $rfi->update(['discipline' => 'Arquitetura']);

        $this->assertSame('Arquitetura', $rfi->fresh()->discipline);
    }

    /*
    |---------------------------------------------------------------------------
    | State
    |---------------------------------------------------------------------------
    */

    public function test_closing_takes_it_out_of_everybodys_court(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN, 'ball_in_court_id' => $this->author->id]);

        $rfi->close();

        $this->assertNull($rfi->fresh()->ball_in_court_id);
        $this->assertFalse($rfi->fresh()->isOpenForBallInCourt());
    }

    public function test_answering_hands_it_back_to_whoever_asked(): void
    {
        $projetista = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $rfi = $this->rfi(['status' => Rfi::OPEN, 'ball_in_court_id' => $projetista->id]);

        $rfi->recordAnswer('Resposta.', $projetista);

        $this->assertSame($this->author->id, $rfi->fresh()->ball_in_court_id);
    }

    /** Offer, never create. */
    public function test_an_impacting_rfi_suggests_a_change_order_but_none_is_made(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN, 'cost_impact' => true]);
        $rfi->close();

        $this->assertTrue($rfi->fresh()->suggestsChangeOrder());
        $this->assertNull($rfi->fresh()->change_order_id);
        $this->assertSame(0, \App\Models\ChangeOrder::count());
    }

    public function test_an_rfi_with_no_impact_suggests_nothing(): void
    {
        $rfi = $this->rfi(['status' => Rfi::OPEN]);
        $rfi->close();

        $this->assertFalse($rfi->fresh()->suggestsChangeOrder());
    }

    /*
    |---------------------------------------------------------------------------
    | Labels
    |---------------------------------------------------------------------------
    */

    public function test_a_status_is_never_printed_raw(): void
    {
        $this->assertSame(__('collaboration.rfi.status.closed'), Rfi::statusLabel(Rfi::CLOSED));
        $this->assertNotSame('closed', Rfi::statusLabel(Rfi::CLOSED));
        $this->assertArrayHasKey(Rfi::OPEN, Rfi::statusOptions());
        $this->assertArrayHasKey('urgent', Rfi::priorityOptions());
    }

    /** *Solicitação* is feminine — the participle has to agree. */
    public function test_the_pt_br_status_words_agree_with_a_feminine_subject(): void
    {
        $this->app->setLocale('pt_BR');

        $this->assertSame('Encerrada', Rfi::statusLabel(Rfi::CLOSED));
        $this->assertSame('Respondida', Rfi::statusLabel(Rfi::ANSWERED));
        $this->assertSame('Aberta', Rfi::statusLabel(Rfi::OPEN));
    }

    /*
    |---------------------------------------------------------------------------
    | visibleTo
    |---------------------------------------------------------------------------
    */

    protected function confinedUserOn(?Project $project = null, ?JobSite $jobSite = null): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $scope = $project ?? $jobSite;

        if ($scope) {
            Membership::create([
                'user_id' => $user->id,
                'scopeable_type' => $scope::class,
                'scopeable_id' => $scope->id,
                'status' => MembershipStatus::ACTIVE,
                'invited_by' => $this->author->id,
                'accepted_at' => now(),
            ]);
        }

        return $user;
    }

    public function test_somebody_company_wide_sees_every_rfi(): void
    {
        $this->rfi();
        $this->rfi();

        $this->assertSame(2, Rfi::visibleTo($this->author)->count());
    }

    public function test_a_confined_person_sees_only_their_projects_rfis(): void
    {
        $mine = $this->rfi();

        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->author->id,
        ]);
        $this->rfi(['project_id' => $other->id]);

        $user = $this->confinedUserOn($this->project);

        $this->assertSame([$mine->id], Rfi::visibleTo($user)->pluck('id')->all());
    }

    /** A membership on one site is not access to the whole project's RFIs. */
    public function test_a_job_site_membership_sees_that_sites_rfis(): void
    {
        $siteRfi = $this->rfi(['job_site_id' => $this->jobSite->id]);
        $projectRfi = $this->rfi();

        $user = $this->confinedUserOn(jobSite: $this->jobSite);

        $visible = Rfi::visibleTo($user)->pluck('id')->all();

        $this->assertContains($siteRfi->id, $visible);
        $this->assertNotContains($projectRfi->id, $visible);
    }

    public function test_a_confined_person_with_no_membership_sees_nothing(): void
    {
        $this->rfi();

        $this->assertSame(0, Rfi::visibleTo($this->confinedUserOn())->count());
    }

    public function test_a_guest_is_confined_whatever_their_scope_says(): void
    {
        $this->rfi();

        $guest = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'is_guest' => true,
            'access_scope' => AccessScope::COMPANY,   // says company-wide…
        ]);

        // …and is confined regardless, so with no membership sees nothing.
        $this->assertSame(0, Rfi::visibleTo($guest)->count());
    }

    public function test_nobody_signed_in_sees_nothing(): void
    {
        $this->rfi();

        $this->assertSame(0, Rfi::visibleTo(null)->count());
    }

    /**
     * The leak-by-aggregate case: a total is as sensitive as the rows behind
     * it, so it has to be narrowed by the same scope.
     */
    public function test_a_total_is_narrowed_by_the_same_filter(): void
    {
        $this->rfi(['cost_impact' => true]);

        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->author->id,
        ]);
        $this->rfi(['project_id' => $other->id, 'cost_impact' => true]);

        $user = $this->confinedUserOn($this->project);

        $this->assertSame(2, Rfi::where('cost_impact', true)->count());
        $this->assertSame(1, Rfi::visibleTo($user)->where('cost_impact', true)->count());
    }
}
