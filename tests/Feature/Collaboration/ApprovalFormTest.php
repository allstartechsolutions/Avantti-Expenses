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

    protected function makeSupplier(string $name, ?string $contact = null): \App\Models\Supplier
    {
        $supplier = \App\Models\Supplier::create([
            'name' => $name,
            'created_by' => $this->admin->id,
        ]);

        if ($contact) {
            $supplier->forceFill(['contact_name' => $contact])->save();
        }

        return $supplier;
    }

    /** A vendor flagged only as a subcontractor — not a supplier. */
    protected function makeSubcontractorOnly(string $name): \App\Models\Vendor
    {
        $vendor = new \App\Models\Vendor;

        $vendor->forceFill([
            'name' => $name,
            'is_supplier' => false,
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return $vendor;
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
    | The type-to-search pickers
    |---------------------------------------------------------------------------
    |
    | A project can carry hundreds of budget lines and the company thousands of
    | vendors, so neither is a list to scroll: what these prove is that the
    | search finds the row, taking it links the id, and the link never outlives
    | the text that named it.
    */

    public function test_the_budget_line_picker_finds_a_line_by_code_or_by_name(): void
    {
        $line = $this->makeBudgetLine();

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project]);

        $component->set('budgetItemSearch', '07.1')
            ->assertViewHas('budgetItemResults', fn (array $rows) => collect($rows)->contains('id', $line->id));

        $component->set('budgetItemSearch', 'revest')
            ->assertViewHas('budgetItemResults', fn (array $rows) => collect($rows)->contains('id', $line->id));

        // One character is not a search — the whole budget would come back.
        $component->set('budgetItemSearch', '0')
            ->assertViewHas('budgetItemResults', []);
    }

    public function test_taking_a_budget_line_links_it_and_names_it_in_the_box(): void
    {
        $line = $this->makeBudgetLine();

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('budgetItemSearch', 'revest')
            ->call('selectBudgetItem', $line->id)
            ->assertSet('budget_item_id', (string) $line->id)
            ->assertSet('budgetItemSearch', '07.100 Revestimentos')
            // The label is a read-back, not a search: no panel over the field.
            ->assertViewHas('budgetItemResults', [])
            ->set('title', 'Porcelanato')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($line->id, Approval::first()->budget_item_id);
    }

    /** Typing over a chosen line unlinks it; saving what is on screen is the point. */
    public function test_typing_over_a_chosen_budget_line_unlinks_it(): void
    {
        $line = $this->makeBudgetLine();

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->call('selectBudgetItem', $line->id)
            ->assertSet('budget_item_id', (string) $line->id)
            ->set('budgetItemSearch', 'outra coisa')
            ->assertSet('budget_item_id', null)
            ->call('clearBudgetItem')
            ->assertSet('budgetItemSearch', '');
    }

    /** The picker is the same door as the form: another project's line is neither offered nor taken. */
    public function test_a_budget_line_from_another_project_is_never_offered_or_taken(): void
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
            ->set('budgetItemSearch', 'revest')
            ->assertViewHas('budgetItemResults', [])
            ->call('selectBudgetItem', $foreign->id)
            ->assertSet('budget_item_id', null);
    }

    public function test_the_supplier_picker_searches_names_and_contacts_and_caps_what_it_returns(): void
    {
        $supplier = $this->makeSupplier('Cerâmica Portobello', 'Marcos Vieira');

        for ($i = 0; $i < 24; $i++) {
            $this->makeSupplier('Cerâmica Genérica '.$i);
        }

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project]);

        $component->set('supplierSearch', 'portobello')
            ->assertViewHas('supplierResults', fn (array $rows) => collect($rows)->contains('id', $supplier->id));

        $component->set('supplierSearch', 'marcos')
            ->assertViewHas('supplierResults', fn (array $rows) => collect($rows)->contains('id', $supplier->id));

        // 25 vendors match; a dropdown does not take 25 rows.
        $component->set('supplierSearch', 'cerâmica')
            ->assertViewHas('supplierResults', fn (array $rows) => count($rows) === 20);
    }

    public function test_taking_a_supplier_links_it_and_typing_over_it_unlinks_it(): void
    {
        $supplier = $this->makeSupplier('Cerâmica Portobello');

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('supplierSearch', 'portobello')
            ->call('selectSupplier', $supplier->id)
            ->assertSet('supplier_id', (string) $supplier->id)
            ->assertSet('supplierSearch', 'Cerâmica Portobello')
            ->set('title', 'Porcelanato')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($supplier->id, Approval::first()->supplier_id);

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['approval' => Approval::first()])
            // Editing opens with what is linked already named.
            ->assertSet('supplierSearch', 'Cerâmica Portobello')
            ->set('supplierSearch', 'Cerâm')
            ->assertSet('supplier_id', null);
    }

    /**
     * The field is *Fornecedor*, so it offers suppliers.
     *
     * Suppliers and subcontractors share one table; a vendor flagged only as a
     * subcontractor used to be offered here and could be saved as the supplier
     * of an approval.
     */
    public function test_a_subcontractor_only_vendor_is_neither_offered_nor_taken(): void
    {
        $sub = $this->makeSubcontractorOnly('Empreiteira Alvenaria');

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('supplierSearch', 'alvenaria')
            ->assertViewHas('supplierResults', [])
            ->call('selectSupplier', $sub->id)
            ->assertSet('supplier_id', null);
    }

    /** And it cannot be saved by setting the property directly either. */
    public function test_a_subcontractor_only_vendor_is_refused_on_save(): void
    {
        $sub = $this->makeSubcontractorOnly('Empreiteira Alvenaria');

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('supplier_id', (string) $sub->id)
            ->call('save')
            ->assertNotFound();

        $this->assertSame(0, Approval::count());
    }

    /**
     * A record raised before the narrowing keeps what it has.
     *
     * Refusing it would mean nobody could correct the title of an approval
     * filed last month, which is a worse outcome than the link itself.
     */
    public function test_an_approval_that_already_names_a_subcontractor_can_still_be_edited(): void
    {
        $sub = $this->makeSubcontractorOnly('Empreiteira Alvenaria');

        $approval = Approval::create([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato',
            'type' => Approval::TYPE_MATERIAL,
            'supplier_id' => $sub->id,
            'created_by_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['approval' => $approval])
            // The name it already carries is read back, whatever the flags say.
            ->assertSet('supplierSearch', 'Empreiteira Alvenaria')
            ->set('title', 'Porcelanato retificado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($sub->id, $approval->fresh()->supplier_id);
        $this->assertSame('Porcelanato retificado', $approval->fresh()->title);
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
            ->set('newUploads', [
                UploadedFile::fake()->create('ficha-tecnica.pdf', 100, 'application/pdf'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['ficha-tecnica.pdf'], Approval::first()->availableFiles()->pluck('original_name')->all());
    }

    /**
     * A second drop adds to the queue.
     *
     * Files arrive one drag at a time; if each drop replaced the last, the
     * user would lose the first batch with nothing on screen to say so.
     */
    public function test_files_dropped_in_two_goes_are_all_kept(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            ->set('title', 'Porcelanato')
            ->set('newUploads', [UploadedFile::fake()->create('ficha-tecnica.pdf', 20, 'application/pdf')])
            // The box is emptied for the next drop, and the queue holds the first.
            ->assertSet('newUploads', [])
            ->set('newUploads', [UploadedFile::fake()->create('laudo.pdf', 20, 'application/pdf')])
            ->assertCount('uploads', 2)
            ->call('discardUpload', 0)
            ->assertCount('uploads', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['laudo.pdf'], Approval::first()->availableFiles()->pluck('original_name')->all());
    }

    /**
     * A file over this install's size cap is refused without wedging the form.
     *
     * Left in the box it would be invisible — the list on screen is the queue,
     * not the box — and would fail every later save with no button to remove
     * it. And the refusal must not take the rest of the form's messages with
     * it: `validate()` ends with a bare `resetErrorBag()`, which is why this
     * hook uses `addError()` instead.
     *
     * The cap is lowered here on purpose. Livewire's own temporary-upload rule
     * (12 MB by default) catches anything bigger than itself first; this is the
     * install whose own limit is *below* Livewire's — a stock `upload_max_filesize`
     * of 2 M, say — where the file arrives and this form has to refuse it.
     */
    public function test_an_oversize_file_is_refused_without_blocking_the_save(): void
    {
        Storage::fake(config('documents.disk', 'local'));

        config(['tasks.max_upload_bytes' => 1024 * 1024]);

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalForm::class, ['project' => $this->project])
            // A form already showing a message of its own.
            ->set('title', '')
            ->call('save')
            ->assertHasErrors('title')
            ->set('newUploads', [UploadedFile::fake()->create('planta-enorme.pdf', 4096, 'application/pdf')]);

        // Refused, said, and out of the box.
        $component->assertHasErrors('newUploads')
            ->assertSet('newUploads', [])
            ->assertCount('uploads', 0)
            // The title message is still there, because the title is still empty.
            ->assertHasErrors('title');

        // And the form still saves, rather than failing for ever on a file
        // nothing on screen can remove.
        $component->set('title', 'Porcelanato')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Approval::count());
        $this->assertSame(0, Approval::first()->availableFiles()->count());
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
            ->set('newUploads', [
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
            'addDistributionRow', 'removeDistributionRow', 'discardUpload', 'updatedNewUploads',
            'addReviewerRow', 'removeReviewerRow', 'reuseLastReviewers',
            // The type-to-search pickers. Clearing and re-typing only unset a
            // property; the three select* methods, which act on an id that
            // came from the browser, are NOT exempt and are checked below.
            'clearBudgetItem', 'clearSupplier', 'clearCatalogItem',
            'updatedBudgetItemSearch', 'updatedSupplierSearch', 'updatedCatalogItemSearch',
            // Read-only helpers the view asks before it renders a button. The
            // actions behind them — deleteApproval(), setPackage(),
            // createPackage(), togglePackageStatus() — are NOT exempt and are
            // checked below.
            'getCanDeleteProperty', 'getCanManagePackagesProperty', 'availablePackages',
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
            ['save', 'submitRevision', 'recordResponse', 'downloadFile',
                // The pickers that take an id from the browser.
                'selectBudgetItem', 'selectSupplier', 'selectCatalogItem',
                // Destroying an approval, and the submittal packages.
                'deleteApproval', 'setPackage', 'createPackage', 'togglePackageStatus'],
            $checked,
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Through the routes
    |---------------------------------------------------------------------------
    */

    /**
     * No action may be named after one of Livewire's own `$wire` methods.
     *
     * `removeUpload()` was, on both of this module's forms, and the delete
     * button on the attachment queue never reached PHP: `$wire.removeUpload`
     * is Livewire's uploader, so the click went there with the row index where
     * a property name belongs and the request died with
     * "Property [$0] not found". The name is the whole bug, which is why it is
     * checked mechanically rather than remembered.
     */
    public function test_no_action_is_named_after_a_livewire_api_method(): void
    {
        // From Livewire's own alias map (dist/livewire.js): anything here is
        // intercepted in the browser and never reaches the component.
        $reserved = [
            'get', 'set', 'call', 'commit', 'watch', 'entangle', 'dispatch', 'dispatchTo',
            'dispatchSelf', 'upload', 'uploadMultiple', 'removeUpload', 'cancelUpload',
            'refresh', 'toggle', 'on', 'js',
        ];

        foreach ([
            ApprovalForm::class,
            \App\Livewire\Approval\ApprovalShow::class,
            \App\Livewire\Rfi\RfiForm::class,
            \App\Livewire\Rfi\RfiShow::class,
        ] as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $this->assertNotContains(
                    $method->getName(),
                    $reserved,
                    "{$class}::{$method->getName()}() shadows a Livewire \$wire method; a wire:click on it never reaches PHP.",
                );
            }
        }
    }

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
