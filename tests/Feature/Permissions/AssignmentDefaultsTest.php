<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\UserStatus;
use App\Livewire\Assignment\DefaultAssignmentsPanel;
use App\Models\Client;
use App\Models\DefaultAssignment;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 of docs/procurement-assignment-plan.md: the defaults table, the
 * resolver that walks job site → project → install, and the three panels.
 *
 * The four things this has to prove, in the order the checklist puts them:
 * the chain resolves, a deactivated person is skipped rather than returned,
 * the panel is guarded on both view and edit, and an id the picker never
 * offered is refused by the endpoint behind it.
 */
class AssignmentDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected JobSite $jobSite;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $client = Client::create([
            'company_name' => 'Assignment Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Assignment Project',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'North Site',
            'contact_person' => 'Contact',
            'email' => 'site@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /** Somebody who can raise a round on the given scope, via a membership. */
    protected function buyer(Project|JobSite $scope, string $name = 'Buyer'): User
    {
        $user = $this->user('employee', [
            'name' => $name,
            'access_scope' => AccessScope::ASSIGNED->value,
        ]);

        $template = PermissionTemplate::where('key', 'procurement')->first();

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->id,
            'permission_template_id' => $template->id,
            'status' => \App\Enums\MembershipStatus::ACTIVE->value,
            'invited_by' => $this->admin->id,
        ]);

        foreach ($template->abilities() as $ability) {
            $membership->abilityRows()->create(['ability' => $ability]);
        }

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | The chain
    |---------------------------------------------------------------------------
    */

    public function test_nothing_set_anywhere_resolves_to_nobody(): void
    {
        $this->assertNull(
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)
        );
    }

    public function test_the_chain_walks_job_site_then_project_then_install(): void
    {
        $installBuyer = $this->buyer($this->project, 'Install Buyer');
        $projectBuyer = $this->buyer($this->project, 'Project Buyer');
        $siteBuyer = $this->buyer($this->jobSite, 'Site Buyer');

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'global', 0, $installBuyer->id, $this->admin->id);

        $this->assertSame(
            $installBuyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id,
            'With only the install tier set, a job site falls all the way through to it.'
        );

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $projectBuyer->id, $this->admin->id);

        $this->assertSame(
            $projectBuyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id,
            'The project tier beats the install.'
        );

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'job_site', $this->jobSite->id, $siteBuyer->id, $this->admin->id);

        $this->assertSame(
            $siteBuyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id,
            'The job site beats them both.'
        );

        // Asked about the project rather than the site, the site's row is not
        // consulted at all — it belongs to one site, not to the project.
        $this->assertSame(
            $projectBuyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, null, $this->project)?->id
        );
    }

    public function test_a_level_set_to_nobody_defers_upward_rather_than_ending_the_walk(): void
    {
        $projectBuyer = $this->buyer($this->project, 'Project Buyer');

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $projectBuyer->id, $this->admin->id);
        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'job_site', $this->jobSite->id, null, $this->admin->id);

        $this->assertSame(
            $projectBuyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id
        );
    }

    public function test_a_deactivated_person_is_skipped_not_returned(): void
    {
        $projectBuyer = $this->buyer($this->project, 'Project Buyer');
        $siteBuyer = $this->buyer($this->jobSite, 'Site Buyer');

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $projectBuyer->id, $this->admin->id);
        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'job_site', $this->jobSite->id, $siteBuyer->id, $this->admin->id);

        $siteBuyer->update(['status' => UserStatus::INACTIVE->value]);

        $this->assertSame(
            $projectBuyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id,
            'Disabling somebody must not route new work into a dead inbox.'
        );
    }

    public function test_every_level_naming_a_deactivated_person_resolves_to_nobody(): void
    {
        $buyer = $this->buyer($this->project, 'Only Buyer');

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'global', 0, $buyer->id, $this->admin->id);
        $buyer->update(['status' => UserStatus::INACTIVE->value]);

        $this->assertNull(
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite),
            'Unassigned is a legal answer — a stale name is not.'
        );
    }

    public function test_one_row_per_level_per_role(): void
    {
        $a = $this->buyer($this->project, 'First');
        $b = $this->buyer($this->project, 'Second');

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $a->id, $this->admin->id);
        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $b->id, $this->admin->id);

        $this->assertSame(1, DefaultAssignment::query()
            ->where('context_type', 'project')
            ->where('context_id', $this->project->id)
            ->where('role_key', DefaultAssignment::QUOTATION_BUYER)
            ->count());

        $this->assertSame($b->id, DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, null, $this->project)?->id);
    }

    /*
    |---------------------------------------------------------------------------
    | The panel — guarded, scoped, revocable
    |---------------------------------------------------------------------------
    */

    public function test_the_panel_refuses_somebody_without_the_view_grant(): void
    {
        // A plain employee holds neither assignment-defaults ability.
        Livewire::actingAs($this->user('employee'))
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->assertForbidden();
    }

    public function test_saving_is_guarded_separately_from_viewing(): void
    {
        $viewer = $this->user('employee');
        $viewer->abilityOverrides()->create(['ability' => 'assignment-defaults.view', 'granted' => true]);

        $buyer = $this->buyer($this->project, 'Project Buyer');

        Livewire::actingAs($viewer)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->assertOk()
            ->set('choices.'.DefaultAssignment::QUOTATION_BUYER, (string) $buyer->id)
            ->call('save', DefaultAssignment::QUOTATION_BUYER)
            ->assertForbidden();

        $this->assertNull(DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, null, $this->project));
    }

    public function test_an_admin_can_set_and_then_clear_a_project_default(): void
    {
        $buyer = $this->buyer($this->project, 'Project Buyer');

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->set('choices.'.DefaultAssignment::QUOTATION_BUYER, (string) $buyer->id)
            ->call('save', DefaultAssignment::QUOTATION_BUYER)
            ->assertHasNoErrors();

        $this->assertSame($buyer->id, DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, null, $this->project)?->id);

        $row = DefaultAssignment::at(DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id);
        $this->assertSame($this->admin->id, $row->set_by);

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->set('choices.'.DefaultAssignment::QUOTATION_BUYER, '')
            ->call('save', DefaultAssignment::QUOTATION_BUYER)
            ->assertHasNoErrors();

        $this->assertNull(DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, null, $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | Never trust an id from the browser
    |---------------------------------------------------------------------------
    */

    public function test_an_id_the_picker_never_offered_is_refused(): void
    {
        // On the project, but with no quotations.create: an outsider to this
        // particular piece of work.
        $stranger = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED->value]);

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->set('choices.'.DefaultAssignment::QUOTATION_BUYER, (string) $stranger->id)
            ->call('save', DefaultAssignment::QUOTATION_BUYER)
            ->assertHasErrors('choices.'.DefaultAssignment::QUOTATION_BUYER);

        $this->assertNull(DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, null, $this->project));
    }

    public function test_the_picker_offers_only_people_who_can_raise_a_round_here(): void
    {
        $buyer = $this->buyer($this->project, 'Project Buyer');
        $elsewhere = $this->user('employee', ['name' => 'Elsewhere', 'access_scope' => AccessScope::ASSIGNED->value]);

        $component = Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ]);

        $ids = $component->instance()->candidatesFor(DefaultAssignment::QUOTATION_BUYER)->pluck('id')->all();

        $this->assertContains($buyer->id, $ids);
        $this->assertNotContains($elsewhere->id, $ids, 'Somebody with no membership here is not offered.');
    }

    /*
    |---------------------------------------------------------------------------
    | All three tiers, and the three places the panel is rendered
    |---------------------------------------------------------------------------
    */

    public function test_the_install_tier_can_be_set_from_system_settings(): void
    {
        $buyer = $this->buyer($this->project, 'Install Buyer');

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, ['contextType' => 'global'])
            ->assertOk()
            ->set('choices.'.DefaultAssignment::QUOTATION_BUYER, (string) $buyer->id)
            ->call('save', DefaultAssignment::QUOTATION_BUYER)
            ->assertHasNoErrors();

        $this->assertSame(
            $buyer->id,
            DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id,
            'An install-wide default reaches a job site that names nobody.'
        );
    }

    public function test_the_job_site_tier_can_be_set(): void
    {
        $buyer = $this->buyer($this->jobSite, 'Site Buyer');

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'job_site',
                'contextId' => $this->jobSite->id,
            ])
            ->assertOk()
            ->set('choices.'.DefaultAssignment::QUOTATION_BUYER, (string) $buyer->id)
            ->call('save', DefaultAssignment::QUOTATION_BUYER)
            ->assertHasNoErrors();

        $this->assertSame($buyer->id, DefaultAssignment::resolve(DefaultAssignment::QUOTATION_BUYER, $this->jobSite)?->id);
    }

    public function test_the_panel_appears_on_both_team_pages_and_in_system_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.team', $this->project))
            ->assertOk()
            ->assertSee(__('Default assignments'));

        $this->actingAs($this->admin)
            ->get(route('jobsites.team', $this->jobSite))
            ->assertOk()
            ->assertSee(__('Default assignments'));

        $this->actingAs($this->admin)
            ->get(route('system-settings.index'))
            ->assertOk()
            ->assertSee(__('Assignments'));
    }

    public function test_a_project_panel_says_what_it_inherits_rather_than_looking_unset(): void
    {
        $installBuyer = $this->buyer($this->project, 'Install Buyer');

        DefaultAssignment::set(DefaultAssignment::QUOTATION_BUYER, 'global', 0, $installBuyer->id, $this->admin->id);

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->assertOk()
            ->assertSee(__('Follows :name, set higher up.', ['name' => 'Install Buyer']));
    }

    public function test_the_empty_state_says_what_to_do_about_it(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->assertOk()
            ->assertSee(__('Nobody here holds what this needs yet.'));
    }

    public function test_an_unknown_role_key_is_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->call('save', 'not_a_role')
            ->assertNotFound();
    }
}
