<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Project\ProjectRfis;
use App\Livewire\Rfi\RfiForm;
use App\Livewire\Rfi\RfiShow;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The RFI module's permission pass, in the four groups
 * docs/permissions-for-new-modules.md §6 asks for.
 *
 * The module is new, so "reproduced" means something slightly different here
 * than it does for a converted one: there is no previous behaviour to preserve,
 * and the thing to prove instead is that the seeded roles come out where the
 * hold-back lists say they should. Everything else is the same four questions.
 */
class RfiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Project $otherProject;

    protected JobSite $jobSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $this->project = $this->makeProject('Obra Central');
        $this->otherProject = $this->makeProject('Obra Norte');

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeProject(string $name): Project
    {
        $client = Client::create([
            'company_name' => $name.' Client',
            'contact_name' => 'Contact',
            'email' => str($name)->slug().'@example.test',
            'created_by' => $this->admin->id,
        ]);

        return Project::create([
            'project_name' => $name,
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => str($name)->slug().'-p@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function rfi(?Project $project = null, array $attributes = []): Rfi
    {
        return Rfi::create(array_merge([
            'project_id' => ($project ?? $this->project)->id,
            'subject' => 'Detalhe da esquadria',
            'question' => 'Qual perfil usar?',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ], $attributes));
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /** A confined member of a project, holding exactly these abilities. */
    protected function member(Project $project, array $abilities, bool $isGuest = false): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
            'is_guest' => $isGuest,
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'can_see_money' => false,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);

        $membership->syncAbilities(AbilityCatalog::filter($abilities, 'project'));

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | 1. REPRODUCED
    |---------------------------------------------------------------------------
    | Nothing existed before this module, so what is pinned here is that the
    | seeded roles land exactly where PermissionSeeder's hold-back lists say —
    | the thing that would otherwise drift silently.
    */

    public function test_the_seeded_roles_hold_what_the_hold_back_lists_say(): void
    {
        $resolver = app(PermissionResolver::class);
        $manager = $this->user('manager');
        $employee = $this->user('employee');

        // Held from both: the deletes and the correction of a closed record.
        foreach (['rfis.delete', 'rfis.revise'] as $ability) {
            $this->assertFalse($resolver->allows($manager, $ability), $ability);
            $this->assertFalse($resolver->allows($employee, $ability), $ability);
        }

        // Held from the employee only.
        foreach (['rfis.close', 'rfis.distribute'] as $ability) {
            $this->assertTrue($resolver->allows($manager, $ability), $ability);
            $this->assertFalse($resolver->allows($employee, $ability), $ability);
        }

        // Everyday work, held by both.
        foreach (['rfis.view', 'rfis.create', 'rfis.edit', 'rfis.answer', 'rfis.view_impact'] as $ability) {
            $this->assertTrue($resolver->allows($manager, $ability), $ability);
            $this->assertTrue($resolver->allows($employee, $ability), $ability);
        }
    }

    /** An administrator bypasses the rows, as everywhere else. */
    public function test_an_administrator_holds_the_lot(): void
    {
        $resolver = app(PermissionResolver::class);

        foreach (AbilityCatalog::areas()['rfis']['actions'] as $key => $action) {
            $name = is_int($key) ? $action : $key;
            $this->assertTrue($resolver->allows($this->admin, "rfis.{$name}"), $name);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | 2. REVOCABLE
    |---------------------------------------------------------------------------
    */

    public function test_the_area_can_be_taken_away(): void
    {
        $role = Role::create(['name' => 'no-rfis-'.uniqid()]);
        $role->syncAbilities(['projects.view', 'project.view']);

        $stranger = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($stranger)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('projects.rfis', $this->project))
            ->assertForbidden();
    }

    /** Revocation bites on the next request, not at next sign-in. */
    public function test_revoking_a_membership_closes_the_door_at_once(): void
    {
        $member = $this->member($this->project, ['project.view', 'rfis.view']);
        $rfi = $this->rfi();

        Livewire::actingAs($member)->test(RfiShow::class, ['rfi' => $rfi])->assertOk();

        // There is no REVOKED status: `active()` wants an ACTIVE row with no
        // revoked_at, so taking access away is the timestamp and the status
        // moving off ACTIVE.
        $member->memberships()->first()->update([
            'status' => MembershipStatus::SUSPENDED,
            'revoked_at' => now(),
        ]);
        app(PermissionResolver::class)->flush();

        Livewire::actingAs($member)->test(RfiShow::class, ['rfi' => $rfi])->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | 3. SCOPED
    |---------------------------------------------------------------------------
    */

    public function test_an_rfi_on_another_project_is_not_reachable_by_its_id(): void
    {
        $member = $this->member($this->project, ['project.view', 'rfis.view', 'rfis.edit']);
        $theirs = $this->rfi($this->otherProject);

        Livewire::actingAs($member)->test(RfiShow::class, ['rfi' => $theirs])->assertForbidden();
        Livewire::actingAs($member)->test(RfiForm::class, ['rfi' => $theirs])->assertForbidden();
        $this->actingAs($member)->get(route('rfis.show', $theirs))->assertForbidden();
    }

    public function test_another_projects_rfis_are_not_in_the_list_or_its_totals(): void
    {
        $this->rfi($this->project, ['subject' => 'Ours']);
        $this->rfi($this->otherProject, ['subject' => 'Theirs']);

        $member = $this->member($this->project, ['project.view', 'rfis.view']);

        Livewire::actingAs($member)
            ->test(ProjectRfis::class, ['project' => $this->project])
            ->assertSee('Ours')
            ->assertDontSee('Theirs');

        // And the aggregate is narrowed by the same filter — a count over
        // records somebody cannot open is a leak by arithmetic.
        $this->assertSame(1, Rfi::visibleTo($member)->count());
    }

    /** A job site chosen on the form must belong to the project in hand. */
    public function test_a_job_site_from_another_project_cannot_be_attached(): void
    {
        $foreign = JobSite::create([
            'project_id' => $this->otherProject->id,
            'job_site_name' => 'Bloco Z',
            'contact_person' => 'C',
            'email' => 'z@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'A')
            ->set('question', 'B')
            ->set('job_site_id', (string) $foreign->id)
            ->call('save')
            ->assertNotFound();
    }

    /*
    |---------------------------------------------------------------------------
    | 4. SEPARATE
    |---------------------------------------------------------------------------
    | Each action is its own grant. Holding one gives none of the others.
    */

    public function test_viewing_gives_neither_answering_nor_closing(): void
    {
        $viewer = $this->member($this->project, ['project.view', 'rfis.view']);
        $rfi = $this->rfi();

        $component = Livewire::actingAs($viewer)->test(RfiShow::class, ['rfi' => $rfi]);
        $component->assertOk();

        $component->set('answerText', 'Tentativa.')->call('recordAnswer')->assertForbidden();
        $this->assertNull($rfi->fresh()->answer);
    }

    public function test_answering_gives_neither_closing_nor_editing(): void
    {
        $answerer = $this->member($this->project, ['project.view', 'rfis.view', 'rfis.answer']);
        $rfi = $this->rfi();

        Livewire::actingAs($answerer)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('answerText', 'Resposta.')
            ->call('recordAnswer')
            ->assertHasNoErrors();

        Livewire::actingAs($answerer)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('close')
            ->assertForbidden();

        Livewire::actingAs($answerer)
            ->test(RfiForm::class, ['rfi' => $rfi->fresh()])
            ->assertForbidden();
    }

    public function test_closing_does_not_carry_the_right_to_correct_afterwards(): void
    {
        $closer = $this->member($this->project, ['project.view', 'rfis.view', 'rfis.answer', 'rfis.close']);
        $rfi = $this->rfi();

        Livewire::actingAs($closer)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('answerText', 'Resposta.')
            ->call('recordAnswer')
            ->assertHasNoErrors();

        $rfi->refresh();

        Livewire::actingAs($closer)->test(RfiShow::class, ['rfi' => $rfi])->call('close')->assertHasNoErrors();

        // Closed by them, and still not theirs to rewrite: correcting a closed
        // SI is `rfis.revise`, which closing does not carry.
        Livewire::actingAs($closer)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('startEditingReply', $rfi->fresh()->valid_reply_id)
            ->assertForbidden();

        $this->assertSame('Resposta.', $rfi->fresh()->answer);
    }

    public function test_editing_does_not_carry_the_right_to_see_impact(): void
    {
        $editor = $this->member($this->project, ['project.view', 'rfis.view', 'rfis.edit']);
        $rfi = $this->rfi($this->project, ['cost_impact' => true, 'schedule_impact_days' => 10]);

        Livewire::actingAs($editor)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->assertOk()
            ->assertDontSee(__('collaboration.label.cost_impact'));
    }

    /*
    |---------------------------------------------------------------------------
    | The leak test
    |---------------------------------------------------------------------------
    */

    /**
     * A guest's page carries no cost or schedule figure, anywhere in the body.
     *
     * The plan asked for this against a separate portal. There is no portal —
     * a guest is answered by the same screens, and what keeps the figures away
     * is `rfis.view_impact`, which the projetista template does not hold. This
     * asserts the outcome the portal was meant to guarantee, against the real
     * pages rather than a second set that would drift from them.
     */
    public function test_a_guests_pages_carry_no_cost_or_schedule_figure(): void
    {
        $template = PermissionTemplate::where('key', 'projetista-project')->firstOrFail();

        $projetista = $this->member(
            $this->project,
            $template->abilityRows->pluck('ability')->all(),
            isGuest: true,
        );

        $rfi = $this->rfi($this->project, [
            'cost_impact' => true,
            'schedule_impact' => true,
            'schedule_impact_days' => 17,
            'ball_in_court_id' => $projetista->id,
        ]);

        foreach ([
            route('projects.rfis', $this->project),
            route('rfis.show', $rfi),
        ] as $url) {
            $body = $this->actingAs($projetista)->get($url)->assertOk()->getContent();

            foreach ([
                __('collaboration.label.cost_impact'),
                __('collaboration.label.schedule_impact'),
                __('collaboration.label.cost_schedule_impact'),
                __('collaboration.label.change_order'),
                // The day count as it would actually be rendered. A bare "17"
                // is not a probe: it matches class names, ids and dates in any
                // page, and would fail whether or not anything leaked.
                trans_choice(':count day|:count days', 17, ['count' => 17]),
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $body,
                    "A guest's page at {$url} shows '{$forbidden}'.",
                );
            }
        }
    }

    /** And a guest never sees money anywhere, by their membership. */
    public function test_a_guest_cannot_see_money(): void
    {
        $projetista = $this->member($this->project, ['project.view', 'rfis.view'], isGuest: true);

        $this->assertFalse(app(PermissionResolver::class)->canSeeMoney($projetista, $this->project));
        $this->assertTrue($projetista->isConfined());
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue
    |---------------------------------------------------------------------------
    */

    public function test_the_area_is_declared_and_swept(): void
    {
        $area = AbilityCatalog::areas()['rfis'];

        $this->assertSame('collaboration', $area['module']);
        $this->assertTrue($area['swept'], 'rfis is enforced now; the flag should say so.');
        $this->assertEqualsCanonicalizing(['global', 'project', 'job_site'], $area['levels']);
        $this->assertTrue(AbilityCatalog::isSensitive('rfis.revise'));
        $this->assertTrue(AbilityCatalog::isSensitive('rfis.distribute'));
    }

    public function test_an_undeclared_rfi_ability_is_refused(): void
    {
        $this->assertFalse(app(PermissionResolver::class)->allows($this->admin, 'rfis.invented'));
    }
}
