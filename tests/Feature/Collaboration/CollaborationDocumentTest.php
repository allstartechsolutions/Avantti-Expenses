<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Livewire\Approval\ApprovalShow;
use App\Livewire\Rfi\RfiShow;
use App\Mail\CollaborationDocumentMail;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Collaboration\ResponseCode;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\Collaboration\CollaborationDocumentRenderer;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The printed sheet, the signature on it, and posting it out.
 *
 * The document is asserted as HTML rather than as rendered PDF bytes: what
 * matters is what it says, and the bytes are compressed.
 */
class CollaborationDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

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

    protected function approval(array $attributes = []): Approval
    {
        return Approval::create(array_merge([
            'project_id' => $this->project->id,
            'title' => 'Porcelanato do hall',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ], $attributes));
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

    protected function renderer(): CollaborationDocumentRenderer
    {
        return app(CollaborationDocumentRenderer::class);
    }

    /*
    |---------------------------------------------------------------------------
    | The sheet
    |---------------------------------------------------------------------------
    */

    public function test_an_rfi_sheet_carries_the_question_and_the_answer(): void
    {
        $rfi = $this->rfi(['drawing_ref' => 'ARQ-04 rev.C', 'discipline' => 'Arquitetura']);
        $rfi->recordAnswer('Perfil série 25.', $this->admin);

        $html = $this->renderer()->html($rfi->fresh());

        $this->assertStringContainsString($rfi->number, $html);
        $this->assertStringContainsString('Detalhe da esquadria', $html);
        $this->assertStringContainsString('Qual perfil usar no caixilho?', $html);
        $this->assertStringContainsString('Perfil série 25.', $html);
        $this->assertStringContainsString('Obra Central', $html);
    }

    /** A printed draft must never be mistaken for the record. */
    public function test_a_draft_is_stamped_as_one(): void
    {
        $draft = $this->rfi(['status' => Rfi::DRAFT]);
        $open = $this->rfi(['status' => Rfi::OPEN]);

        $this->assertStringContainsString(__('collaboration.pdf.draft_issued'), $this->renderer()->html($draft));
        $this->assertStringNotContainsString(__('collaboration.pdf.draft_issued'), $this->renderer()->html($open));
    }

    /**
     * The country decides which sheet is used. A rendering choice — the same
     * record produces both, and nothing about its behaviour changes.
     */
    public function test_the_country_decides_which_sheet_is_printed(): void
    {
        $rfi = $this->rfi(['drawing_ref' => 'ARQ-04 rev.C', 'spec_section' => '08 41 13']);

        config(['app.country' => 'BR']);
        $br = $this->renderer()->html($rfi);

        config(['app.country' => 'US']);
        $us = $this->renderer()->html($rfi);

        // BR cites the prancha; US cites the specification section.
        $this->assertStringContainsString('ARQ-04 rev.C', $br);
        $this->assertStringNotContainsString('08 41 13', $br);

        $this->assertStringContainsString('08 41 13', $us);
        $this->assertStringNotContainsString('ARQ-04 rev.C', $us);
    }

    public function test_an_approval_sheet_carries_every_round(): void
    {
        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval();

        $approval->submit([['user_id' => $reviewer->id]], $this->admin);
        $approval->recordResponse(
            ResponseCode::offered('approval')->firstWhere('canonical', ResponseCode::REVISE_RESUBMIT),
            $reviewer,
            'Trocar o rejunte.',
        );
        $approval->fresh()->submit([['user_id' => $reviewer->id]], $this->admin);

        $html = $this->renderer()->html($approval->fresh());

        $this->assertStringContainsString(__('collaboration.label.revision', ['revision' => '0']), $html);
        $this->assertStringContainsString(__('collaboration.label.revision', ['revision' => '1']), $html);
        $this->assertStringContainsString('Trocar o rejunte.', $html);
    }

    public function test_a_certificates_facts_are_printed(): void
    {
        $approval = $this->approval(['type' => Approval::TYPE_CERTIFICATE]);
        $approval->certificate()->create([
            'issuing_body' => 'INMETRO',
            'certificate_number' => 'ABC-123',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $html = $this->renderer()->html($approval->fresh());

        $this->assertStringContainsString('INMETRO', $html);
        $this->assertStringContainsString('ABC-123', $html);
        $this->assertStringContainsString(__('collaboration.label.certificate_expired'), $html);
    }

    public function test_the_filename_names_the_document(): void
    {
        $rfi = $this->rfi();

        $this->assertSame(
            \Illuminate\Support\Str::slug($rfi->number.'-Detalhe da esquadria').'.pdf',
            $this->renderer()->filename($rfi),
        );
    }

    /**
     * No sheet prints a translation call or an unresolved key.
     *
     * A `__('…')` written into a Blade without its `{{ }}` renders as literal
     * text, and a key with no entry renders as the key itself. Neither throws,
     * so nothing else notices: the existing PDF tests assert on *values* —
     * "ARQ-04 rev.C" — and never on the labels around them, which is how
     * `__('collaboration.label.prancha_revisao')` reached a printed document.
     *
     * Every sheet, both markets, so a template that is only rendered on one
     * kind of install is covered too.
     */
    public function test_no_sheet_prints_a_translation_call_or_an_unresolved_key(): void
    {
        $rfi = $this->rfi(['drawing_ref' => 'ARQ-04 rev.C', 'spec_section' => '08 41 13', 'discipline' => 'architecture']);
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->fresh()->sign($this->admin, 'CREA 12345-D', 'ART 987');
        $rfi->fresh()->syncDistribution([['external_name' => 'Studio', 'external_email' => 'a@b.test']]);

        $reviewer = $this->memberWith('projetista-project');
        $approval = $this->approval(['type' => Approval::TYPE_CERTIFICATE, 'description' => 'Laudo de ensaio.']);
        $approval->certificate()->create(['issuing_body' => 'INMETRO', 'valid_until' => now()->addYear()->toDateString()]);
        $approval->submit([['user_id' => $reviewer->id]], $this->admin);

        foreach (['US', 'BR'] as $country) {
            config(['app.country' => $country]);

            foreach ([$rfi->fresh(), $approval->fresh()] as $document) {
                $html = $this->renderer()->html($document);
                $where = class_basename($document)." on a {$country} install";

                $this->assertStringNotContainsString('__(', $html, "A raw __() call is printed on the {$where}.");
                $this->assertStringNotContainsString('trans_choice(', $html, "A raw trans_choice() call is printed on the {$where}.");

                // An unresolved key falls through as the key itself.
                $this->assertStringNotContainsString('collaboration.', $html, "An unresolved key is printed on the {$where}.");
            }
        }
    }

    /** The same guard over the screens, in the locale the customer reads. */
    public function test_no_screen_prints_a_translation_call_or_an_unresolved_key(): void
    {
        $this->app->setLocale('pt_BR');
        config(['app.country' => 'BR']);

        $rfi = $this->rfi(['discipline' => 'architecture', 'cost_impact' => true, 'cost_impact_amount' => 1250]);
        $approval = $this->approval(['type' => Approval::TYPE_CERTIFICATE]);
        $approval->certificate()->create(['issuing_body' => 'INMETRO', 'valid_until' => now()->addYear()->toDateString()]);

        $urls = [
            route('projects.rfis', $this->project),
            route('projects.approvals', $this->project),
            route('rfis.show', $rfi),
            route('approvals.show', $approval),
            route('projects.rfis.create', $this->project),
            route('projects.approvals.create', $this->project),
            route('rfis.edit', $rfi),
            route('approvals.edit', $approval),
            route('projects.approvals.seed', $this->project),
        ];

        foreach ($urls as $url) {
            $body = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('__(', $body, "A raw __() call is printed at {$url}.");
            $this->assertStringNotContainsString('collaboration.', $body, "An unresolved key is printed at {$url}.");
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Signatures on the sheet
    |---------------------------------------------------------------------------
    */

    public function test_a_signature_prints_with_its_registration_and_art(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->fresh()->sign($this->admin, 'CREA 12345-D', 'ART 987');

        $html = $this->renderer()->html($rfi->fresh());

        $this->assertStringContainsString('Ana Souza', $html);
        $this->assertStringContainsString('CREA 12345-D', $html);
        $this->assertStringContainsString('ART 987', $html);
    }

    /**
     * The reason `payload_hash` exists. A sheet that printed a stale signature
     * without saying so would claim more than it can.
     */
    public function test_a_sheet_says_when_a_signature_no_longer_matches(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);
        $rfi->fresh()->sign($this->admin, 'CREA 12345-D');

        $this->assertStringNotContainsString(
            __('collaboration.help.document_changed_since_signed'),
            $this->renderer()->html($rfi->fresh()),
        );

        $rfi->fresh()->update(['answer' => 'Perfil série 30.']);

        $this->assertStringContainsString(
            __('collaboration.help.document_changed_since_signed'),
            $this->renderer()->html($rfi->fresh()),
        );
    }

    /*
    |---------------------------------------------------------------------------
    | The routes
    |---------------------------------------------------------------------------
    */

    public function test_the_pdf_downloads(): void
    {
        $rfi = $this->rfi();

        $response = $this->actingAs($this->admin)->get(route('rfis.pdf.download', $rfi));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_downloading_is_recorded(): void
    {
        $rfi = $this->rfi();

        $this->actingAs($this->admin)->get(route('rfis.pdf.download', $rfi))->assertOk();

        $this->assertSame(1, $rfi->activity()->where('action', ActivityLogEntry::EXPORTED)->count());
    }

    /**
     * The hole the permission sweep found more than once: a PDF controller on
     * `auth` alone. Both of these must be refused.
     */
    public function test_the_pdf_is_refused_without_the_export_grant(): void
    {
        // The projetista may read an RFI and answer it, and does not hold export.
        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();

        $this->actingAs($projetista)->get(route('rfis.pdf.download', $rfi))->assertForbidden();
        $this->actingAs($projetista)->get(route('rfis.pdf.view', $rfi))->assertForbidden();
    }

    /**
     * And the grant is scoped: holding `rfis.export` on one project is not
     * permission to print another's.
     */
    public function test_the_export_grant_does_not_cross_projects(): void
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
            'subject' => 'De outra obra',
            'question' => 'Pergunta',
            'created_by_id' => $this->admin->id,
        ]);

        $member = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $member->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);
        $membership->syncAbilities(AbilityCatalog::filter(
            ['project.view', 'rfis.view', 'rfis.export'],
            'project',
        ));

        // Their own project's RFI prints.
        $this->actingAs($member)->get(route('rfis.pdf.download', $this->rfi()))->assertOk();

        // Another project's does not.
        $this->actingAs($member)->get(route('rfis.pdf.download', $theirs))->assertForbidden();
    }

    public function test_the_approval_pdf_is_guarded_too(): void
    {
        $projetista = $this->memberWith('projetista-project');

        $this->actingAs($projetista)
            ->get(route('approvals.pdf.download', $this->approval()))
            ->assertForbidden();
    }

    /**
     * The module's object keys are not reachable through `FileController`.
     *
     * That controller serves paths off the default disk and answers "who owns
     * this path?" per directory. These files live on the `file_uploads` side
     * and have exactly one way out — the guarded `downloadFile()` on each
     * detail component — so the directories are deliberately absent from its
     * allow-list. Pinned, because adding them later would open a second route
     * to the same bytes past a different guard.
     */
    public function test_the_modules_file_directories_are_not_served_by_file_controller(): void
    {
        $allowed = (new \ReflectionClass(\App\Http\Controllers\FileController::class))
            ->getConstant('ALLOWED_DIRECTORIES');

        $this->assertNotContains('rfis', $allowed);
        $this->assertNotContains('approvals', $allowed);

        // 404 rather than 403, which is the better refusal here: it does not
        // confirm whether the path exists.
        foreach (['rfis/1/1/x.pdf', 'approvals/1/1/x.pdf'] as $path) {
            $this->actingAs($this->admin)
                ->get(route('files.show', ['path' => $path]))
                ->assertNotFound();
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Distribution
    |---------------------------------------------------------------------------
    */

    public function test_the_document_is_sent_to_everybody_on_the_list(): void
    {
        Mail::fake();

        $rfi = $this->rfi();
        $rfi->syncDistribution([
            ['external_name' => 'Studio Arq', 'external_email' => 'arq@studio.test'],
            ['external_name' => 'Fiscalização', 'external_email' => 'fiscal@obra.test'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('distributionNote', 'Segue para análise.')
            ->call('distributeDocument')
            ->assertHasNoErrors();

        Mail::assertSent(CollaborationDocumentMail::class, 2);
    }

    public function test_sending_is_recorded_with_who_received_it(): void
    {
        Mail::fake();

        $rfi = $this->rfi();
        $rfi->syncDistribution([['external_name' => 'Studio', 'external_email' => 'arq@studio.test']]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('distributeDocument');

        $entry = $rfi->activity()->where('action', ActivityLogEntry::DISTRIBUTED)->first();

        $this->assertNotNull($entry);
        $this->assertSame(1, $entry->context['sent']);
        $this->assertSame(['arq@studio.test'], $entry->context['recipients']);
    }

    /** An empty list is said, not silently treated as a send. */
    public function test_sending_with_nobody_on_the_list_says_so(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $this->rfi()])
            ->call('distributeDocument')
            ->assertHasErrors('distributionNote');

        Mail::assertNothingSent();
    }

    public function test_distributing_is_refused_without_the_grant(): void
    {
        Mail::fake();

        $projetista = $this->memberWith('projetista-project');
        $rfi = $this->rfi();
        $rfi->syncDistribution([['external_name' => 'X', 'external_email' => 'x@test.test']]);

        Livewire::actingAs($projetista)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('distributeDocument')
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    /*
    |---------------------------------------------------------------------------
    | Whether it was actually sent
    |---------------------------------------------------------------------------
    */

    /**
     * A list nobody has sent must not look like one that went out.
     *
     * Reported from real use: two people on the list, one e-mail received. The
     * document had never been sent at all — the second name was added and the
     * screen said nothing either way, so the list read as though adding a name
     * were enough.
     */
    public function test_a_document_that_was_never_sent_says_so(): void
    {
        $rfi = $this->rfi();
        $rfi->syncDistribution([
            ['external_name' => 'Studio', 'external_email' => 'arq@studio.test'],
            ['external_name' => 'Fiscal', 'external_email' => 'fiscal@obra.test'],
        ]);

        $this->assertFalse($rfi->hasBeenDistributed());

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee(__('collaboration.label.never_sent'));
    }

    /** And once sent, it says when and to how many. */
    public function test_a_sent_document_says_when_it_went_out(): void
    {
        Mail::fake();

        $rfi = $this->rfi();
        $rfi->syncDistribution([['external_name' => 'Studio', 'external_email' => 'arq@studio.test']]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('distributeDocument');

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertDontSee(__('collaboration.label.never_sent'))
            ->assertSee(__('collaboration.label.last_sent_on', [
                // The page prints the install's own format, not a fixed one.
                'when' => $rfi->fresh()->lastDistribution()->created_at
                    ->appDateTime(),
                'count' => 1,
            ]));
    }

    /**
     * The exact case that was reported: somebody added afterwards has received
     * nothing, and the screen names them.
     */
    public function test_somebody_added_after_the_send_is_named_as_not_having_received_it(): void
    {
        Mail::fake();

        $rfi = $this->rfi();
        $rfi->syncDistribution([['external_name' => 'Studio', 'external_email' => 'arq@studio.test']]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('distributeDocument');

        Mail::assertSent(CollaborationDocumentMail::class, 1);

        // A second person joins the list. Nothing is sent by adding them.
        $rfi->fresh()->syncDistribution([
            ['external_name' => 'Studio', 'external_email' => 'arq@studio.test'],
            ['external_name' => 'Fiscal', 'external_email' => 'fiscal@obra.test'],
        ]);

        Mail::assertSent(CollaborationDocumentMail::class, 1);

        $awaiting = $rfi->fresh()->recipientsAwaitingFirstSend();
        $this->assertSame(['fiscal@obra.test'], $awaiting->keys()->all());

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->assertSee('Fiscal')
            ->assertSee(trans_choice('collaboration.count.added_since_last_send', 1, ['count' => 1]));

        // Sending again reaches both, and the warning clears.
        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->call('distributeDocument');

        Mail::assertSent(CollaborationDocumentMail::class, 3);
        $this->assertTrue($rfi->fresh()->recipientsAwaitingFirstSend()->isEmpty());
    }

    /*
    |---------------------------------------------------------------------------
    | Signing from the screen
    |---------------------------------------------------------------------------
    */

    public function test_signing_records_the_registration_and_logs_it(): void
    {
        $rfi = $this->rfi();
        $rfi->recordAnswer('Perfil série 25.', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi->fresh()])
            ->set('signerDocument', 'CREA 12345-D')
            ->set('artNumber', 'ART 987')
            ->call('signDocument')
            ->assertHasNoErrors();

        $signature = $rfi->fresh()->signatures()->first();

        $this->assertSame('Ana Souza', $signature->signer_name);
        $this->assertSame('CREA 12345-D', $signature->signer_document);
        $this->assertSame('ART 987', $signature->art_number);
        $this->assertSame(1, $rfi->activity()->where('action', ActivityLogEntry::SIGNED)->count());
    }

    public function test_signing_is_refused_without_the_grant(): void
    {
        $viewer = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $viewer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);
        $membership->syncAbilities(AbilityCatalog::filter(['project.view', 'rfis.view'], 'project'));

        $rfi = $this->rfi();

        Livewire::actingAs($viewer)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('signDocument')
            ->assertForbidden();

        $this->assertSame(0, $rfi->fresh()->signatures()->count());
    }

    public function test_an_approval_can_be_signed_from_its_page(): void
    {
        $approval = $this->approval();

        Livewire::actingAs($this->admin)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('signerDocument', 'CAU A123456')
            ->call('signDocument')
            ->assertHasNoErrors();

        $this->assertSame('CAU A123456', $approval->fresh()->signatures()->first()->signer_document);
    }
}
