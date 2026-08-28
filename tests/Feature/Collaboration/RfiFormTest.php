<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Rfi\RfiForm;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Raising and editing an RFI.
 *
 * The things that matter beyond the obvious: a job site from the browser has
 * to belong to this project, impact cannot be written by somebody who may not
 * see it, and files chosen on the form are attached in the same step rather
 * than after a save-and-reopen.
 */
class RfiFormTest extends TestCase
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

        $this->jobSite = $this->makeJobSite('Torre A');
    }

    protected function makeJobSite(string $name, ?Project $project = null): JobSite
    {
        return JobSite::create([
            'project_id' => ($project ?? $this->project)->id,
            'job_site_name' => $name,
            'contact_person' => 'Contact',
            'email' => str($name)->slug().'@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
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
    | Raising
    |---------------------------------------------------------------------------
    */

    public function test_an_rfi_can_be_raised_from_the_project_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Detalhe da esquadria')
            ->set('question', 'Qual perfil usar no caixilho do hall?')
            ->set('discipline', 'Arquitetura')
            ->set('priority', 'high')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $rfi = Rfi::first();

        $this->assertStringEndsWith('-001', $rfi->number);
        $this->assertSame(Rfi::OPEN, $rfi->status);
        $this->assertSame($this->project->id, $rfi->project_id);
        $this->assertNull($rfi->job_site_id);
        $this->assertSame($this->admin->id, $rfi->created_by_id);
    }

    public function test_raising_from_a_job_site_page_fixes_the_location(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['jobSite' => $this->jobSite])
            ->set('subject', 'Prumada')
            ->set('question', 'Onde passa a prumada?')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->jobSite->id, Rfi::first()->job_site_id);
        $this->assertSame($this->project->id, Rfi::first()->project_id);
    }

    public function test_the_subject_and_question_are_required(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', '')
            ->set('question', '')
            ->call('save')
            ->assertHasErrors(['subject', 'question']);

        $this->assertSame(0, Rfi::count());
    }

    public function test_raising_is_recorded_in_the_history(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->call('save');

        $this->assertSame(
            1,
            Rfi::first()->activity()->where('action', ActivityLogEntry::CREATED)->count(),
        );
    }

    /**
     * The id came from the browser. Existing is not the same as belonging here.
     */
    public function test_a_job_site_from_another_project_is_refused(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);
        $foreign = $this->makeJobSite('Bloco Z', $other);

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('job_site_id', (string) $foreign->id)
            ->call('save')
            ->assertNotFound();

        $this->assertSame(0, Rfi::count());
    }

    /*
    |---------------------------------------------------------------------------
    | Editing
    |---------------------------------------------------------------------------
    */

    public function test_the_form_opens_filled_in_for_an_existing_rfi(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Detalhe da esquadria',
            'question' => 'Qual perfil?',
            'discipline' => 'Arquitetura',
            'priority' => 'urgent',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['rfi' => $rfi])
            ->assertSet('subject', 'Detalhe da esquadria')
            ->assertSet('question', 'Qual perfil?')
            ->assertSet('discipline', 'Arquitetura')
            ->assertSet('priority', 'urgent');
    }

    public function test_editing_saves_without_taking_a_new_number(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Antes',
            'question' => 'Pergunta',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['rfi' => $rfi])
            ->set('subject', 'Depois')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Depois', $rfi->fresh()->subject);
        $this->assertStringEndsWith('-001', $rfi->fresh()->number);
        $this->assertSame(1, Rfi::count());
    }

    /*
    |---------------------------------------------------------------------------
    | Distribution
    |---------------------------------------------------------------------------
    */

    public function test_a_distribution_list_is_saved_with_the_rfi(): void
    {
        $member = $this->memberWith('projetista-project');

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('distributionRows', [
                ['user_id' => (string) $member->id, 'external_name' => '', 'external_email' => '', 'role' => 'projetista'],
                ['user_id' => '', 'external_name' => 'Studio Arq', 'external_email' => 'arq@studio.test', 'role' => 'fiscalizacao'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Rfi::first()->distribution()->count());
    }

    /** A row naming nobody reachable would fail silently at send time. */
    public function test_blank_distribution_rows_are_dropped(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('distributionRows', [
                ['user_id' => '', 'external_name' => '', 'external_email' => '', 'role' => ''],
                ['user_id' => '', 'external_name' => 'Real', 'external_email' => 'real@test.test', 'role' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Rfi::first()->distribution()->count());
    }

    public function test_a_malformed_address_is_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('distributionRows', [
                ['user_id' => '', 'external_name' => 'X', 'external_email' => 'not-an-address', 'role' => ''],
            ])
            ->call('save')
            ->assertHasErrors('distributionRows.0.external_email');
    }

    /** The shortcut that stops somebody adding eight rows by hand. */
    public function test_everyone_on_the_project_can_be_added_at_once(): void
    {
        $a = $this->memberWith('projetista-project');
        $b = $this->memberWith('projetista-project');

        $component = Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->call('addEveryoneOnProject');

        $ids = collect($component->get('distributionRows'))->pluck('user_id')->filter()->all();

        $this->assertContains((string) $a->id, $ids);
        $this->assertContains((string) $b->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_adding_everyone_twice_does_not_double_them(): void
    {
        $this->memberWith('projetista-project');

        $rows = Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->call('addEveryoneOnProject')
            ->call('addEveryoneOnProject')
            ->get('distributionRows');

        $this->assertCount(1, $rows);
    }

    public function test_a_row_can_be_removed_and_the_list_never_empties(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->call('addDistributionRow')
            ->call('removeDistributionRow', 0)
            ->call('removeDistributionRow', 0);

        // Always one blank row to type into, never a bare panel.
        $this->assertCount(1, $component->get('distributionRows'));
    }

    /**
     * A user id on the distribution list is an id from the browser.
     *
     * `exists:users,id` proves the person exists; it does not prove they
     * belong on this project. Without the scope check, a crafted payload put
     * anybody on the list and the distributor posted them the document.
     */
    public function test_somebody_not_on_the_project_cannot_be_put_on_the_distribution_list(): void
    {
        $outsider = User::factory()->create(['role_id' => Role::where('name', 'employee')->value('id')]);

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('distributionRows', [
                ['user_id' => (string) $outsider->id, 'external_name' => '', 'external_email' => '', 'role' => ''],
                ['user_id' => '', 'external_name' => 'Legítimo', 'external_email' => 'ok@test.test', 'role' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $list = Rfi::first()->distribution;

        $this->assertSame(1, $list->count());
        $this->assertSame('ok@test.test', $list->first()->external_email);
    }

    /** Somebody who IS on the project still goes on the list normally. */
    public function test_a_project_member_can_be_put_on_the_distribution_list(): void
    {
        $member = $this->memberWith('projetista-project');

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('distributionRows', [
                ['user_id' => (string) $member->id, 'external_name' => '', 'external_email' => '', 'role' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($member->id, Rfi::first()->distribution->first()->user_id);
    }

    /**
     * A closed RFI refuses its subject and question, and says which field and
     * why — the model threw under a key this form never rendered, so the save
     * appeared to do nothing at all.
     */
    public function test_editing_a_closed_rfis_question_says_why_it_is_refused(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Antes',
            'question' => 'Pergunta original',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);
        $rfi->recordAnswer('Resposta.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['rfi' => $rfi->fresh()])
            ->set('question', 'Outra pergunta')
            ->call('save')
            ->assertHasErrors('question');

        $this->assertSame('Pergunta original', $rfi->fresh()->question);
    }

    /** The rest of a closed RFI is still editable, and saving works. */
    public function test_a_closed_rfi_still_accepts_the_fields_that_are_not_frozen(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Assunto',
            'question' => 'Pergunta',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);
        $rfi->recordAnswer('Resposta.', $this->admin);
        $rfi->close();

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['rfi' => $rfi->fresh()])
            ->set('discipline', 'Arquitetura')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Arquitetura', $rfi->fresh()->discipline);
    }

    /*
    |---------------------------------------------------------------------------
    | Attachments, in the same step
    |---------------------------------------------------------------------------
    */

    public function test_files_chosen_on_the_form_are_attached_when_it_is_saved(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('uploads', [
                UploadedFile::fake()->create('prancha.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('detalhe.pdf', 80, 'application/pdf'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $rfi = Rfi::first();

        // Attached in the same action — no save-then-reopen-to-attach.
        $this->assertSame(2, $rfi->availableFiles()->count());
        $this->assertEqualsCanonicalizing(
            ['prancha.pdf', 'detalhe.pdf'],
            $rfi->availableFiles()->pluck('original_name')->all(),
        );
    }

    public function test_a_chosen_file_can_be_taken_off_before_saving(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('uploads', [
                UploadedFile::fake()->create('mantida.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('removida.pdf', 10, 'application/pdf'),
            ])
            ->call('removeUpload', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['mantida.pdf'], Rfi::first()->availableFiles()->pluck('original_name')->all());
    }

    /*
    |---------------------------------------------------------------------------
    | Impact and permissions
    |---------------------------------------------------------------------------
    */

    public function test_impact_is_saved_by_somebody_who_may_see_it(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RfiForm::class, ['project' => $this->project])
            ->set('subject', 'Assunto')
            ->set('question', 'Pergunta')
            ->set('cost_impact', true)
            ->set('schedule_impact', true)
            ->set('schedule_impact_days', '10')
            ->call('save')
            ->assertHasNoErrors();

        $rfi = Rfi::first();

        $this->assertTrue($rfi->cost_impact);
        $this->assertTrue($rfi->schedule_impact);
        $this->assertSame(10, $rfi->schedule_impact_days);
    }

    /**
     * Somebody without `rfis.view_impact` never sees the flags. If the form
     * wrote them anyway, saving an edit would silently clear what they were
     * not shown — a data loss dressed as a save.
     */
    public function test_impact_is_not_cleared_by_an_edit_from_somebody_who_cannot_see_it(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Antes',
            'question' => 'Pergunta',
            'status' => Rfi::OPEN,
            'cost_impact' => true,
            'schedule_impact' => true,
            'schedule_impact_days' => 10,
            'created_by_id' => $this->admin->id,
        ]);

        // Site Supervisor holds rfis.view_impact; strip it to model somebody
        // who may edit but may not see the flags.
        $editor = $this->memberWith('site-supervisor');
        $editor->memberships()->first()->syncAbilities([
            'project.view', 'rfis.view', 'rfis.create', 'rfis.edit',
        ]);

        Livewire::actingAs($editor)
            ->test(RfiForm::class, ['rfi' => $rfi])
            ->set('subject', 'Depois')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Depois', $rfi->fresh()->subject);
        $this->assertTrue($rfi->fresh()->cost_impact);
        $this->assertSame(10, $rfi->fresh()->schedule_impact_days);
    }

    public function test_raising_is_refused_without_the_grant(): void
    {
        // The projetista answers RFIs; it does not raise them.
        Livewire::actingAs($this->memberWith('projetista-project'))
            ->test(RfiForm::class, ['project' => $this->project])
            ->assertForbidden();
    }

    public function test_editing_is_refused_without_the_grant(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Assunto',
            'question' => 'Pergunta',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->memberWith('projetista-project'))
            ->test(RfiForm::class, ['rfi' => $rfi])
            ->assertForbidden();
    }

    /**
     * Every action a `wire:click` can reach guards itself.
     *
     * Checked mechanically rather than case by case, because the failure this
     * catches is one of omission: somebody adds a method next year and the
     * button in front of it is the only thing standing between the browser and
     * the record. Hiding a button is not protection — the endpoint stays open.
     *
     * The list is the public methods of the two components, minus Livewire's
     * own lifecycle hooks and the read-only helpers the view calls.
     */
    public function test_every_action_method_carries_its_own_guard(): void
    {
        $exempt = [
            // Lifecycle and rendering.
            'mount', 'render', 'boot', 'booted', 'hydrate', 'dehydrate', 'updated', 'updating',
            // Read-only, and each one is a `getXProperty` the view reads.
            'getIsEditingProperty', 'getCanSeeImpactProperty', 'getCanAnswerProperty',
            'getCanCloseProperty', 'getCanReopenProperty', 'getCanReviseProperty',
            'getCanEditProperty',
            // Form-local state: they move rows and files about in the
            // component and touch no record. The save they feed is guarded.
            'addDistributionRow', 'removeDistributionRow', 'addEveryoneOnProject', 'removeUpload',
            'cancelEditingReply', 'removeReplyUpload',
            // Read-only helpers the view asks before it renders a button.
            'getCanChooseReplyProperty', 'canEditReply',
            // Same again for withdrawing and destroying. The two actions
            // behind these buttons — voidRfi() and deleteRfi() — are NOT
            // exempt and are checked below.
            'getCanVoidProperty', 'getCanDeleteProperty',
        ];

        $checked = [];

        foreach ([\App\Livewire\Rfi\RfiForm::class, \App\Livewire\Rfi\RfiShow::class] as $class) {
            $reflection = new \ReflectionClass($class);
            $source = file_get_contents($reflection->getFileName());

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Methods a trait supplies (Livewire's own WithFileUploads
                // machinery) report this class as their declaring class but
                // live in the trait's file. They are framework internals, not
                // this module's actions.
                if ($method->getFileName() !== $reflection->getFileName()
                    || $method->getDeclaringClass()->getName() !== $class
                    || in_array($method->getName(), $exempt, true)
                    || str_starts_with($method->getName(), '__')) {
                    continue;
                }

                // The body of this method, from its signature to its close.
                $lines = preg_split('/\r\n|\r|\n/', $source);
                $body = implode("\n", array_slice(
                    $lines,
                    $method->getStartLine() - 1,
                    $method->getEndLine() - $method->getStartLine() + 1,
                ));

                $checked[] = $method->getName();

                // `authorizeAbility()` or an explicit `abort_unless()` over a
                // permission helper — the reply actions use the second because
                // the rule is conditional (your own words while it is open, or
                // `rfis.revise` for somebody else's), and it is the same guard
                // by another name.
                $this->assertTrue(
                    str_contains($body, 'authorizeAbility') || str_contains($body, 'abort_unless('),
                    "{$class}::{$method->getName()}() is reachable from the browser and does not guard itself.",
                );
            }
        }

        // A filter that quietly skipped everything would pass this test while
        // proving nothing, so name what it actually inspected.
        $this->assertEqualsCanonicalizing(
            ['save', 'recordAnswer', 'close', 'reopen', 'passBall', 'downloadFile',
                'chooseReply', 'startEditingReply', 'saveReplyEdit',
                // Withdrawing and destroying, added when the RFI gained them.
                'voidRfi', 'deleteRfi'],
            $checked,
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Through the routes
    |---------------------------------------------------------------------------
    */

    public function test_the_pages_render_through_their_routes(): void
    {
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'subject' => 'Assunto',
            'question' => 'Pergunta',
            'status' => Rfi::OPEN,
            'created_by_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('projects.rfis.create', $this->project))->assertOk();
        $this->actingAs($this->admin)->get(route('jobsites.rfis.create', $this->jobSite))->assertOk();
        $this->actingAs($this->admin)->get(route('rfis.edit', $rfi))->assertOk()->assertSee('Assunto');
    }

    public function test_the_index_offers_the_new_rfi_button_only_with_the_grant(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.rfis', $this->project))
            ->assertSee(__('collaboration.label.new_rfi'));

        $this->actingAs($this->memberWith('projetista-project'))
            ->get(route('projects.rfis', $this->project))
            ->assertOk()
            ->assertDontSee(__('collaboration.label.new_rfi'));
    }
}
