<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Approval\ApprovalForm;
use App\Models\Approval;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Raising and editing an approval.
 *
 * Beyond the obvious: the certificate block has to follow the type rather than
 * outlive it, and every id that arrives from the browser has to belong to this
 * project.
 */
class ApprovalFormTest extends TestCase
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
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeBudgetLine(?Project $project = null): BudgetItem
    {
        $budget = Budget::create([
            'project_id' => ($project ?? $this->project)->id,
            'name' => 'Orçamento',
            'created_by' => $this->admin->id,
        ]);

        return BudgetItem::create([
            'budget_id' => $budget->id,
            'code' => '07.100',
            'name' => 'Revestimentos',
            'budgeted_amount' => 100000,
        ]);
    }

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

    public function test_an_approval_can_be_raised_as_a_draft(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato do hall')
            ->set('description', 'Porcelanato 90x90 acetinado.')
            ->set('type', Approval::TYPE_MATERIAL)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $approval = Approval::first();

        $this->assertStringEndsWith('-001', $approval->number);
        // A draft: it is not in review until somebody is asked to look at it.
        $this->assertSame(Approval::DRAFT, $approval->status);
        $this->assertSame(0, $approval->revisions()->count());
        $this->assertSame($this->admin->id, $approval->created_by_id);
    }

    public function test_raising_from_a_job_site_page_fixes_the_location(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['jobSite' => $this->jobSite])
            ->set('title', 'Esquadria')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->jobSite->id, Approval::first()->job_site_id);
    }

    public function test_the_title_is_required(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', '')
            ->call('save')
            ->assertHasErrors('title');

        $this->assertSame(0, Approval::count());
    }

    public function test_a_budget_line_can_be_attached(): void
    {
        $line = $this->makeBudgetLine();

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('budget_item_id', (string) $line->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($line->id, Approval::first()->budget_item_id);
    }

    /*
    |---------------------------------------------------------------------------
    | Ids from the browser
    |---------------------------------------------------------------------------
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
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Assunto')
            ->set('job_site_id', (string) $foreign->id)
            ->call('save')
            ->assertNotFound();

        $this->assertSame(0, Approval::count());
    }

    /** A budget line belongs to a project; costing work against another's is not on. */
    public function test_a_budget_line_from_another_project_is_refused(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $foreign = $this->makeBudgetLine($other);

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Assunto')
            ->set('budget_item_id', (string) $foreign->id)
            ->call('save')
            ->assertNotFound();

        $this->assertSame(0, Approval::count());
    }

    /*
    |---------------------------------------------------------------------------
    | The certificate block
    |---------------------------------------------------------------------------
    */

    public function test_a_certificates_details_are_saved_with_it(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Laudo de ensaio')
            ->set('type', Approval::TYPE_CERTIFICATE)
            ->set('issuing_body', 'INMETRO')
            ->set('certificate_number', 'ABC-123')
            ->set('issued_at', now()->subMonth()->toDateString())
            ->set('valid_until', now()->addYear()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $certificate = Approval::first()->certificate;

        $this->assertNotNull($certificate);
        $this->assertSame('INMETRO', $certificate->issuing_body);
        $this->assertSame('ABC-123', $certificate->certificate_number);
    }

    /** The one fact a laudo is useless without. */
    public function test_a_certificate_needs_its_issuing_body(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Laudo')
            ->set('type', Approval::TYPE_CERTIFICATE)
            ->set('issuing_body', '')
            ->call('save')
            ->assertHasErrors('issuing_body');
    }

    /** A material is not asked for one. */
    public function test_a_material_does_not_need_an_issuing_body(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('type', Approval::TYPE_MATERIAL)
            ->set('issuing_body', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Approval::first()->certificate);
    }

    public function test_a_validity_before_the_issue_date_is_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Laudo')
            ->set('type', Approval::TYPE_CERTIFICATE)
            ->set('issuing_body', 'INMETRO')
            ->set('issued_at', now()->toDateString())
            ->set('valid_until', now()->subYear()->toDateString())
            ->call('save')
            ->assertHasErrors('valid_until');
    }

    /**
     * Changing the type away from a certificate drops the block.
     *
     * An orphan row would put a validity date on a screen that never asks for
     * one, and would keep counting towards the lapsing total on the index.
     */
    public function test_changing_the_type_away_from_a_certificate_drops_its_details(): void
    {
        $approval = Approval::create([
            'project_id' => $this->project->id,
            'title' => 'Era um laudo',
            'type' => Approval::TYPE_CERTIFICATE,
            'created_by_id' => $this->admin->id,
        ]);
        $approval->certificate()->create([
            'issuing_body' => 'INMETRO',
            'valid_until' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertTrue($approval->fresh()->certificateNeedsAttention());

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['approval' => $approval])
            ->set('type', Approval::TYPE_MATERIAL)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($approval->fresh()->certificate);
        $this->assertFalse($approval->fresh()->certificateNeedsAttention());
    }

    /*
    |---------------------------------------------------------------------------
    | Editing
    |---------------------------------------------------------------------------
    */

    public function test_the_form_opens_filled_in(): void
    {
        $approval = Approval::create([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato do hall',
            'description' => 'Uma descrição',
            'type' => Approval::TYPE_SHOP_DRAWING,
            'created_by_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['approval' => $approval])
            ->assertSet('title', 'Porcelanato do hall')
            ->assertSet('description', 'Uma descrição')
            ->assertSet('type', Approval::TYPE_SHOP_DRAWING);
    }

    public function test_editing_does_not_take_a_new_number_or_disturb_the_rounds(): void
    {
        $reviewer = $this->memberWith('projetista-project');

        $approval = Approval::create([
            'project_id' => $this->project->id,
            'title' => 'Antes',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ]);
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['approval' => $approval->fresh()])
            ->set('title', 'Depois')
            ->call('save')
            ->assertHasNoErrors();

        $approval->refresh();
        $this->assertSame('Depois', $approval->title);
        $this->assertStringEndsWith('-001', $approval->number);
        $this->assertSame(1, $approval->revisions()->count());
        $this->assertSame(Approval::IN_REVIEW, $approval->status);
    }

    /*
    |---------------------------------------------------------------------------
    | Distribution and attachments
    |---------------------------------------------------------------------------
    */

    public function test_a_distribution_list_is_saved_and_blank_rows_dropped(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('distributionRows', [
                ['user_id' => '', 'external_name' => '', 'external_email' => '', 'role' => ''],
                ['user_id' => '', 'external_name' => 'Studio', 'external_email' => 'arq@studio.test', 'role' => 'projetista'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Approval::first()->distribution()->count());
    }

    public function test_files_chosen_on_the_form_are_attached_when_it_is_saved(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('uploads', [
                UploadedFile::fake()->create('ficha-tecnica.pdf', 100, 'application/pdf'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['ficha-tecnica.pdf'], Approval::first()->availableFiles()->pluck('original_name')->all());
    }

    /**
     * A file the allow-list refuses is said, not dropped.
     *
     * Skipping quietly is how somebody attaches a drawing, is told the record
     * was saved, and finds out weeks later that it never arrived.
     */
    public function test_a_refused_file_type_is_reported_rather_than_dropped(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('uploads', [
                UploadedFile::fake()->create('ficha.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('malicioso.exe', 20, 'application/x-msdownload'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        // The good one is attached, the refused one is named.
        $this->assertSame(['ficha.pdf'], Approval::first()->availableFiles()->pluck('original_name')->all());
        $this->assertStringContainsString('malicioso.exe', session('approval_upload_refused'));
    }

    /**
     * Moving a record between job sites is authorized against where it is
     * going, not only where it has been.
     */
    public function test_a_record_cannot_be_moved_into_a_job_site_the_editor_does_not_hold(): void
    {
        $siteA = $this->makeJobSite('Torre A');
        $siteB = $this->makeJobSite('Torre B');

        $approval = Approval::create([
            'project_id' => $this->project->id,
            'job_site_id' => $siteA->id,
            'title' => 'Porcelanato',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ]);

        // A member of site A only.
        $editor = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);
        $membership = Membership::create([
            'user_id' => $editor->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $siteA->id,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);
        $membership->syncAbilities(AbilityCatalog::filter(
            ['project.view', 'approvals.view', 'approvals.edit'],
            'job_site',
        ));

        Livewire::actingAs($editor)
            ->test(ApprovalForm::class, ['approval' => $approval])
            ->set('job_site_id', (string) $siteB->id)
            ->call('save')
            ->assertForbidden();

        $this->assertSame($siteA->id, $approval->fresh()->job_site_id);
    }

    /*
    |---------------------------------------------------------------------------
    | Permissions
    |---------------------------------------------------------------------------
    */

    public function test_raising_is_refused_without_the_grant(): void
    {
        // The projetista reviews approvals; it does not raise them.
        Livewire::actingAs($this->memberWith('projetista-project'))
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->assertForbidden();
    }

    public function test_editing_is_refused_without_the_grant(): void
    {
        $approval = Approval::create([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->memberWith('projetista-project'))
            ->test(ApprovalForm::class, ['approval' => $approval])
            ->assertForbidden();
    }

    /**
     * Every action a `wire:click` reaches guards itself. Checked mechanically,
     * because the failure this catches is one of omission.
     */
    public function test_every_action_method_carries_its_own_guard(): void
    {
        $exempt = [
            'mount', 'render', 'boot', 'booted', 'hydrate', 'dehydrate', 'updated', 'updating',
            'getIsEditingProperty', 'getIsCertificateProperty', 'getCanSubmitProperty',
            'getCanRespondProperty', 'getCanEditProperty',
            // Form-local state: they move rows and files about in the
            // component and touch no record. The save they feed is guarded.
            'addDistributionRow', 'removeDistributionRow', 'removeUpload',
            'addReviewerRow', 'removeReviewerRow', 'reuseLastReviewers',
        ];

        $checked = [];

        foreach ([ApprovalForm::class, \App\Livewire\Approval\ApprovalShow::class] as $class) {
            $reflection = new \ReflectionClass($class);
            $source = file_get_contents($reflection->getFileName());
            $lines = preg_split('/\r\n|\r|\n/', $source);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getFileName() !== $reflection->getFileName()
                    || $method->getDeclaringClass()->getName() !== $class
                    || in_array($method->getName(), $exempt, true)
                    || str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $body = implode("\n", array_slice(
                    $lines,
                    $method->getStartLine() - 1,
                    $method->getEndLine() - $method->getStartLine() + 1,
                ));

                $checked[] = $method->getName();

                $this->assertStringContainsString(
                    'authorizeAbility',
                    $body,
                    "{$class}::{$method->getName()}() is reachable from the browser and does not guard itself.",
                );
            }
        }

        // A filter that quietly skipped everything would pass while proving
        // nothing, so name what was actually inspected.
        $this->assertEqualsCanonicalizing(
            ['save', 'submitRevision', 'recordResponse', 'downloadFile'],
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
        $approval = Approval::create([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('projects.approvals.create', $this->project))->assertOk();
        $this->actingAs($this->admin)->get(route('jobsites.approvals.create', $this->jobSite))->assertOk();
        $this->actingAs($this->admin)->get(route('approvals.edit', $approval))->assertOk()->assertSee('Porcelanato');
    }

    public function test_the_index_offers_the_new_button_only_with_the_grant(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.approvals', $this->project))
            ->assertSee(__('collaboration.label.new_approval'));

        $this->actingAs($this->memberWith('projetista-project'))
            ->get(route('projects.approvals', $this->project))
            ->assertOk()
            ->assertDontSee(__('collaboration.label.new_approval'));
    }
}
