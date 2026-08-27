<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\JobSite\JobSiteApprovals;
use App\Livewire\Project\ProjectApprovals;
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
 * The approvals index, at both levels.
 */
class ApprovalIndexTest extends TestCase
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
            'status' => Approval::IN_REVIEW,
            'created_by_id' => $this->admin->id,
        ], $attributes));
    }

    protected function memberWith(string $templateKey): User
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

    public function test_the_project_page_lists_its_approvals(): void
    {
        $first = $this->approval(['title' => 'Porcelanato do hall']);
        $this->approval(['title' => 'Esquadria de alumínio', 'type' => Approval::TYPE_SHOP_DRAWING]);

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertOk()
            ->assertSee($first->number)
            ->assertSee('Porcelanato do hall')
            ->assertSee('Esquadria de alumínio');
    }

    public function test_the_job_site_page_shows_only_that_sites_approvals(): void
    {
        $this->approval(['job_site_id' => $this->jobSite->id, 'title' => 'Só do site']);
        $this->approval(['title' => 'Geral do projeto']);

        Livewire::actingAs($this->admin)
            ->test(JobSiteApprovals::class, ['jobSite' => $this->jobSite])
            ->assertOk()
            ->assertSee('Só do site')
            ->assertDontSee('Geral do projeto');
    }

    public function test_both_pages_render_through_their_routes(): void
    {
        $this->approval(['title' => 'Porcelanato do hall']);

        $this->actingAs($this->admin)
            ->get(route('projects.approvals', $this->project))
            ->assertOk()
            ->assertSee('Porcelanato do hall');

        $this->actingAs($this->admin)
            ->get(route('jobsites.approvals', $this->jobSite))
            ->assertOk()
            ->assertSee('Torre A');
    }

    /*
    |---------------------------------------------------------------------------
    | Filters
    |---------------------------------------------------------------------------
    */

    public function test_the_default_view_hides_settled_approvals(): void
    {
        $this->approval(['title' => 'Ainda em análise']);
        $this->approval(['title' => 'Já aprovada', 'status' => Approval::APPROVED]);

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertSee('Ainda em análise')
            ->assertDontSee('Já aprovada')
            ->set('approvalStatusFilter', 'all')
            ->assertSee('Já aprovada');
    }

    public function test_the_type_filter_offers_only_what_is_in_use(): void
    {
        $this->approval(['type' => Approval::TYPE_MATERIAL]);
        $this->approval(['type' => Approval::TYPE_CERTIFICATE]);

        $offered = Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->viewData('typeOptions');

        $this->assertArrayHasKey(Approval::TYPE_MATERIAL, $offered);
        $this->assertArrayHasKey(Approval::TYPE_CERTIFICATE, $offered);
        $this->assertArrayNotHasKey(Approval::TYPE_AS_BUILT, $offered);
    }

    public function test_the_type_filter_narrows_the_list(): void
    {
        $this->approval(['title' => 'Um material', 'type' => Approval::TYPE_MATERIAL]);
        $this->approval(['title' => 'Um laudo', 'type' => Approval::TYPE_CERTIFICATE]);

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->set('approvalTypeFilter', Approval::TYPE_CERTIFICATE)
            ->assertSee('Um laudo')
            ->assertDontSee('Um material');
    }

    /** The question a reviewer opens this screen to ask. */
    public function test_waiting_on_me_finds_what_is_actually_on_my_desk(): void
    {
        $reviewer = $this->memberWith('projetista-project');

        $mine = $this->approval(['title' => 'Para revisar']);
        $mine->submit([['user_id' => $reviewer->id]], $this->admin);

        $done = $this->approval(['title' => 'Já respondida']);
        $done->submit([['user_id' => $reviewer->id]], $this->admin);
        $done->recordResponse(
            ResponseCode::offered('approval')->firstWhere('canonical', ResponseCode::APPROVED),
            $reviewer,
        );

        Livewire::actingAs($reviewer)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->set('approvalStatusFilter', 'all')
            ->set('approvalReviewerFilter', 'mine')
            ->assertSee('Para revisar')
            ->assertDontSee('Já respondida');
    }

    public function test_the_certificate_filter_finds_what_is_lapsing(): void
    {
        $lapsing = $this->approval(['title' => 'Laudo vencendo', 'type' => Approval::TYPE_CERTIFICATE]);
        $lapsing->certificate()->create([
            'issuing_body' => 'INMETRO',
            'valid_until' => now()->addDays(10)->toDateString(),
        ]);

        $fine = $this->approval(['title' => 'Laudo em dia', 'type' => Approval::TYPE_CERTIFICATE]);
        $fine->certificate()->create([
            'issuing_body' => 'INMETRO',
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->set('approvalCertificateAlertsOnly', true)
            ->assertSee('Laudo vencendo')
            ->assertDontSee('Laudo em dia');
    }

    /** The warning belongs on the row, where somebody scanning will see it. */
    public function test_a_lapsing_certificate_is_marked_on_its_row(): void
    {
        $approval = $this->approval(['title' => 'Laudo', 'type' => Approval::TYPE_CERTIFICATE]);
        $approval->certificate()->create([
            'issuing_body' => 'INMETRO',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertSee(__('collaboration.label.certificate_expired'));
    }

    public function test_clearing_the_filters_puts_the_list_back(): void
    {
        $this->approval(['title' => 'Uma aprovação']);

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->set('approvalSearch', 'nada que exista')
            ->assertDontSee('Uma aprovação')
            ->call('clearApprovalFilters')
            ->assertSee('Uma aprovação')
            ->assertSet('approvalSearch', '');
    }

    /*
    |---------------------------------------------------------------------------
    | Counts and states
    |---------------------------------------------------------------------------
    */

    public function test_the_counters_describe_what_this_person_may_see(): void
    {
        $reviewer = $this->memberWith('projetista-project');

        $a = $this->approval();
        $a->submit([['user_id' => $reviewer->id]], $this->admin);

        $this->approval(['due_date' => now()->subDay()->toDateString()]);
        $this->approval(['status' => Approval::APPROVED]);

        $summary = Livewire::actingAs($reviewer)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->viewData('summary');

        $this->assertSame(2, $summary['live']);
        $this->assertSame(1, $summary['awaiting_me']);
        $this->assertSame(1, $summary['overdue']);
        $this->assertSame(1, $summary['approved']);
    }

    public function test_an_empty_project_says_what_an_approval_is_for(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertSee(__('collaboration.message.approvals'))
            ->assertSee(__('collaboration.help.approval_material_sample_shop_drawing'));
    }

    public function test_a_filtered_empty_list_says_the_filters_are_hiding_things(): void
    {
        $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->set('approvalSearch', 'nada')
            ->assertSee(__('collaboration.message.approvals_match_these_filters'))
            ->assertDontSee(__('collaboration.message.approvals'));
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
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertForbidden();

        Livewire::actingAs($stranger)
            ->test(JobSiteApprovals::class, ['jobSite' => $this->jobSite])
            ->assertForbidden();
    }

    public function test_another_projects_approvals_are_not_listed_or_counted(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->approval(['title' => 'Desta obra']);
        $this->approval(['project_id' => $other->id, 'title' => 'De outra obra']);

        $member = $this->memberWith('projetista-project');

        Livewire::actingAs($member)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertSee('Desta obra')
            ->assertDontSee('De outra obra');

        $this->assertSame(1, Approval::visibleTo($member)->count());
    }
}
