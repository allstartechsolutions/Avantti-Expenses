<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\JobSite\JobSiteRfis;
use App\Livewire\Project\ProjectRfis;
use App\Models\Client;
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
 * The RFI index, at both levels.
 *
 * Four things it has to get right: it shows what it should, it filters, it
 * refuses somebody without the grant, and it does not put cost or schedule
 * impact in front of somebody who lacks `rfis.view_impact`.
 */
class RfiIndexTest extends TestCase
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

    /** A confined user on this project, given one template's abilities. */
    protected function memberWith(string $templateKey): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $template = PermissionTemplate::where('key', $templateKey)->firstOrFail();

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

        // A template is a starting point, not a live link: the abilities are
        // copied onto the membership, exactly as InvitationService does on
        // acceptance. A membership carrying only a template_id holds nothing.
        $membership->syncAbilities(
            AbilityCatalog::filter($template->abilityRows->pluck('ability')->all(), 'project')
        );

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | Rendering
    |---------------------------------------------------------------------------
    */

    public function test_the_project_page_lists_its_rfis(): void
    {
        $first = $this->rfi(['subject' => 'Detalhe da esquadria']);
        $this->rfi(['subject' => 'Prumada hidráulica']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertOk()
            ->assertSee($first->number)
            ->assertSee('Detalhe da esquadria')
            ->assertSee('Prumada hidráulica');
    }

    public function test_the_job_site_page_shows_only_that_sites_rfis(): void
    {
        $onSite = $this->rfi(['job_site_id' => $this->jobSite->id, 'subject' => 'Só do site']);
        $this->rfi(['subject' => 'Geral do projeto']);

        Livewire::actingAs($this->admin)
            ->test(JobSiteRfis::class, ['jobSite' => $this->jobSite])
            ->assertOk()
            ->assertSee($onSite->number)
            ->assertSee('Só do site')
            ->assertDontSee('Geral do projeto');
    }

    /** The project page covers every location under it. */
    public function test_the_project_page_includes_its_job_sites_rfis(): void
    {
        $this->rfi(['job_site_id' => $this->jobSite->id, 'subject' => 'Do site']);
        $this->rfi(['subject' => 'Geral']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertSee('Do site')
            ->assertSee('Geral')
            ->assertSee('Torre A');
    }

    public function test_another_projects_rfis_are_not_shown(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);
        $this->rfi(['project_id' => $other->id, 'subject' => 'De outra obra']);
        $this->rfi(['subject' => 'Desta obra']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertSee('Desta obra')
            ->assertDontSee('De outra obra');
    }

    /**
     * Through the route, so the page layout and the tab bar are exercised too
     * — a Livewire component test renders the component and not the chrome
     * around it, and `active="rfis"` has to match a declared tab or the bar
     * highlights nothing.
     */
    public function test_both_pages_render_through_their_routes(): void
    {
        $this->rfi(['subject' => 'Detalhe da esquadria']);

        $this->actingAs($this->admin)
            ->get(route('projects.rfis', $this->project))
            ->assertOk()
            ->assertSee('Detalhe da esquadria')
            ->assertSee($this->project->project_name);

        $this->actingAs($this->admin)
            ->get(route('jobsites.rfis', $this->jobSite))
            ->assertOk()
            ->assertSee('Torre A');
    }

    public function test_the_routes_refuse_somebody_without_the_grant(): void
    {
        $stranger = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $this->actingAs($stranger)->get(route('projects.rfis', $this->project))->assertForbidden();
        $this->actingAs($stranger)->get(route('jobsites.rfis', $this->jobSite))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Filters
    |---------------------------------------------------------------------------
    */

    /** The default is live work — a closed RFI is history, not a to-do. */
    public function test_the_default_view_hides_closed_rfis(): void
    {
        $this->rfi(['subject' => 'Ainda aberta']);
        $closed = $this->rfi(['subject' => 'Já encerrada']);
        $closed->close();

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertSee('Ainda aberta')
            ->assertDontSee('Já encerrada')
            ->set('rfiStatusFilter', 'all')
            ->assertSee('Já encerrada');
    }

    public function test_search_matches_number_subject_and_drawing(): void
    {
        $first = $this->rfi(['subject' => 'Esquadria', 'drawing_ref' => 'ARQ-04 rev.C']);
        $this->rfi(['subject' => 'Hidráulica', 'drawing_ref' => 'HID-01']);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project]);

        $component->set('rfiSearch', 'ARQ-04')->assertSee('Esquadria')->assertDontSee('Hidráulica');
        $component->set('rfiSearch', 'Hidráulica')->assertSee('Hidráulica')->assertDontSee('Esquadria');
        $component->set('rfiSearch', $first->number)->assertSee('Esquadria')->assertDontSee('Hidráulica');
    }

    public function test_the_location_filter_separates_project_from_site(): void
    {
        // Distinctive subjects on purpose: the interface itself says "Projeto
        // (Geral)" in the location dropdown, so a fixture called "Geral" can
        // never be asserted absent from the page.
        $this->rfi(['subject' => 'Pergunta sem local']);
        $this->rfi(['job_site_id' => $this->jobSite->id, 'subject' => 'Pergunta da torre']);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project]);

        $component->set('rfiLocationFilter', 'project')
            ->assertSee('Pergunta sem local')->assertDontSee('Pergunta da torre');

        $component->set('rfiLocationFilter', (string) $this->jobSite->id)
            ->assertSee('Pergunta da torre')->assertDontSee('Pergunta sem local');
    }

    public function test_the_overdue_filter_shows_only_what_is_late(): void
    {
        $this->rfi(['subject' => 'Atrasada', 'due_date' => now()->subDays(2)->toDateString()]);
        $this->rfi(['subject' => 'No prazo', 'due_date' => now()->addDays(5)->toDateString()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->set('rfiOverdueOnly', true)
            ->assertSee('Atrasada')
            ->assertDontSee('No prazo');
    }

    public function test_the_ball_in_court_filter_finds_what_is_with_you(): void
    {
        $other = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->rfi(['subject' => 'Comigo', 'ball_in_court_id' => $this->admin->id]);
        $this->rfi(['subject' => 'Com outro', 'ball_in_court_id' => $other->id]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->set('rfiBallInCourtFilter', 'mine')
            ->assertSee('Comigo')
            ->assertDontSee('Com outro');
    }

    public function test_clearing_the_filters_puts_the_list_back(): void
    {
        $this->rfi(['subject' => 'Uma RFI']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->set('rfiSearch', 'nada que exista')
            ->assertDontSee('Uma RFI')
            ->call('clearRfiFilters')
            ->assertSee('Uma RFI')
            ->assertSet('rfiSearch', '');
    }

    /*
    |---------------------------------------------------------------------------
    | Counts
    |---------------------------------------------------------------------------
    */

    public function test_the_counters_describe_what_this_person_may_see(): void
    {
        $this->rfi(['ball_in_court_id' => $this->admin->id]);
        $this->rfi(['due_date' => now()->subDay()->toDateString()]);
        $this->rfi()->close();

        $summary = Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->viewData('summary');

        $this->assertSame(2, $summary['live']);
        $this->assertSame(1, $summary['waiting_on_me']);
        $this->assertSame(1, $summary['overdue']);
        $this->assertSame(1, $summary['closed']);
    }

    /*
    |---------------------------------------------------------------------------
    | Empty states
    |---------------------------------------------------------------------------
    */

    public function test_an_empty_project_says_what_an_rfi_is_for(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertSee(__('collaboration.message.rfis'))
            ->assertSee(__('collaboration.help.rfi_formal_question_put_designer'));
    }

    /** A filtered-empty list must not read as an empty project. */
    public function test_a_filtered_empty_list_says_the_filters_are_hiding_things(): void
    {
        $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->set('rfiSearch', 'nada')
            ->assertSee(__('collaboration.message.rfis_match_these_filters'))
            ->assertDontSee(__('collaboration.message.rfis'));
    }

    /*
    |---------------------------------------------------------------------------
    | Permissions
    |---------------------------------------------------------------------------
    */

    public function test_somebody_without_the_grant_is_refused(): void
    {
        $stranger = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Livewire::actingAs($stranger)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertForbidden();
    }

    public function test_the_guard_is_on_the_job_site_page_too(): void
    {
        $stranger = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Livewire::actingAs($stranger)
            ->test(JobSiteRfis::class, ['jobSite' => $this->jobSite])
            ->assertForbidden();
    }

    /**
     * The point of `rfis.view_impact`.
     *
     * The projetista template deliberately does not hold it, so an outside
     * designer sees the question and not the fact that answering it costs
     * money. Gated on the ability, never on `is_guest` — an ability cannot be
     * forgotten in a refactor.
     */
    public function test_impact_is_hidden_from_somebody_without_the_grant(): void
    {
        $this->rfi([
            'subject' => 'Detalhe caro',
            'cost_impact' => true,
            'schedule_impact' => true,
            'schedule_impact_days' => 10,
        ]);

        $projetista = $this->memberWith('projetista-project');

        $component = Livewire::actingAs($projetista)
            ->test(ProjectRfis::class, ['project' => $this->project]);

        $component->assertOk()
            ->assertSee('Detalhe caro')
            ->assertDontSee(__('collaboration.label.cost_impact'))
            ->assertDontSee(__('collaboration.label.schedule_impact'))
            ->assertDontSee(__('collaboration.label.cost_schedule_impact'));

        $this->assertFalse($component->instance()->rfiCanSeeImpact());
    }

    public function test_impact_is_shown_to_somebody_with_the_grant(): void
    {
        $this->rfi(['subject' => 'Detalhe caro', 'cost_impact' => true]);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectRfis::class, ['project' => $this->project]);

        $component->assertSee(__('collaboration.label.cost_impact'))
            ->assertSee(__('collaboration.label.cost_schedule_impact'));

        $this->assertTrue($component->instance()->rfiCanSeeImpact());
    }

    /**
     * Hiding the checkbox is not the protection — the query is. Somebody
     * setting the property directly still gets an unfiltered list rather than
     * a working filter over data they may not see the basis for.
     */
    public function test_the_impact_filter_does_nothing_without_the_grant(): void
    {
        $this->rfi(['subject' => 'Com impacto', 'cost_impact' => true]);
        $this->rfi(['subject' => 'Sem impacto']);

        $projetista = $this->memberWith('projetista-project');

        Livewire::actingAs($projetista)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->set('rfiImpactOnly', true)
            ->assertSee('Com impacto')
            ->assertSee('Sem impacto');
    }

    /** A confined member sees the list; the filter, not the guard, decides. */
    public function test_a_confined_member_sees_their_projects_rfis(): void
    {
        $this->rfi(['subject' => 'Visível']);

        Livewire::actingAs($this->memberWith('projetista-project'))
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertOk()
            ->assertSee('Visível');
    }
}
