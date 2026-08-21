<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectDocuments;
use App\Models\Client;
use App\Models\Document;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M12 — Documents.
 *
 * The pass that makes **reading** a grant, which it never was.
 *
 * N5 (docs/permissions-notes.md): `Document::isVisibleTo()` returned true for
 * every non-internal document, to anybody. The download and preview routes sat
 * behind `auth` and nothing else, so any signed-in person could fetch any
 * project's files by guessing an id. That is the hole this pass closes.
 *
 * N7: who may create a share link. The owner's answer — one grant, seeded as
 * today (admin and manager), but revocable per role, per template, per project
 * and per person, because it is the one place where access leaves the
 * application.
 *
 * N8 needed nothing: the permission check already ran **before** the presigned
 * URL was minted, which is where it belongs.
 */
class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
        $this->otherProject = $this->makeProject('Theirs');
        $this->site = $this->makeSite($this->project, 'Site A');
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Doc Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-doc@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-doc@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeDocument(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'project_id' => $this->project->id,
            'name' => 'Drawing '.str()->random(4),
            'category' => 'plans',
            'is_internal' => false,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function memberOf(Project|JobSite $scope, array $abilities): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | N5 — reading is a grant now
    |---------------------------------------------------------------------------
    */

    public function test_a_document_of_another_project_is_no_longer_visible_by_id(): void
    {
        $foreign = $this->makeDocument(['project_id' => $this->otherProject->id]);

        // The hole N5 describes: a member of one project, asking about
        // another's document.
        $member = $this->memberOf($this->project, ['documents.view']);

        $this->assertFalse($foreign->isVisibleTo($member));
        $this->assertTrue($this->makeDocument()->isVisibleTo($member));
    }

    public function test_a_signed_out_visitor_sees_nothing(): void
    {
        $document = $this->makeDocument();

        $this->assertFalse($document->isVisibleTo(null));
    }

    public function test_the_download_and_preview_routes_refuse_somebody_without_the_grant(): void
    {
        $document = $this->makeDocument();
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('documents.download', $document))->assertForbidden();
        $this->actingAs($blind)->get(route('documents.preview', $document))->assertForbidden();
    }

    public function test_seeing_the_documents_screen_is_a_grant(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);
        $reader = $this->roleWith(['project.view', 'projects.view', 'documents.view']);

        $this->actingAs($blind)->get(route('projects.documents', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.documents', $this->site))->assertForbidden();

        $this->actingAs($reader)->get(route('projects.documents', $this->project))->assertOk();
    }

    public function test_the_seeded_roles_still_reach_the_documents_screen(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('projects.documents', $this->project))
                ->assertOk();
        }
    }

    /*
    |---------------------------------------------------------------------------
    | see_internal
    |---------------------------------------------------------------------------
    */

    public function test_an_internal_document_needs_its_own_grant(): void
    {
        $internal = $this->makeDocument(['is_internal' => true, 'name' => 'Margins']);
        $ordinary = $this->makeDocument(['name' => 'Site plan']);

        $reader = $this->memberOf($this->project, ['documents.view']);
        $insider = $this->memberOf($this->project, ['documents.view', 'documents.see_internal']);

        $this->assertFalse($internal->isVisibleTo($reader));
        $this->assertTrue($ordinary->isVisibleTo($reader));

        $this->assertTrue($internal->isVisibleTo($insider));

        $this->actingAs($reader)->get(route('documents.download', $internal))->assertForbidden();
        $this->actingAs($insider)->get(route('documents.download', $internal))->assertNotFound();
    }

    public function test_the_list_hides_internal_documents_from_somebody_without_the_grant(): void
    {
        $this->makeDocument(['is_internal' => true, 'name' => 'Margins']);
        $this->makeDocument(['name' => 'Site plan']);

        $reader = $this->memberOf($this->project, ['documents.view']);
        $insider = $this->memberOf($this->project, ['documents.view', 'documents.see_internal']);

        $this->actingAs($reader);
        $visible = Document::visibleTo($reader)->pluck('name')->all();
        $this->assertContains('Site plan', $visible);
        $this->assertNotContains('Margins', $visible);

        $this->actingAs($insider);
        $this->assertContains('Margins', Document::visibleTo($insider)->pluck('name')->all());
    }

    public function test_see_internal_is_answered_per_project(): void
    {
        $here = $this->makeDocument(['is_internal' => true]);
        $there = $this->makeDocument(['is_internal' => true, 'project_id' => $this->otherProject->id]);

        // Granted on this project only.
        $insider = $this->memberOf($this->project, ['documents.view', 'documents.see_internal']);

        $this->assertTrue($here->isVisibleTo($insider));
        $this->assertFalse($there->isVisibleTo($insider));
    }

    /*
    |---------------------------------------------------------------------------
    | Writing — the old two-way split is now four grants
    |---------------------------------------------------------------------------
    */

    public function test_uploading_editing_deleting_and_sharing_are_separate_grants(): void
    {
        $reader = $this->memberOf($this->project, ['documents.view']);

        foreach ([
            ['openUploadModal', []],
            ['openFolderModal', []],
            ['openEditModal', [1]],
            ['openShareModal', []],
        ] as [$action, $args]) {
            Livewire::actingAs($reader)
                ->test(ProjectDocuments::class, ['project' => $this->project])
                ->call($action, ...$args)
                ->assertForbidden();
        }
    }

    public function test_create_does_not_carry_edit_or_share(): void
    {
        $creator = $this->memberOf($this->project, ['documents.view', 'documents.create']);

        Livewire::actingAs($creator)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openUploadModal')
            ->assertOk();

        Livewire::actingAs($creator)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openShareModal')
            ->assertForbidden();
    }

    public function test_sharing_is_its_own_grant_and_carries_nothing_else(): void
    {
        $document = $this->makeDocument();
        $sharer = $this->memberOf($this->project, ['documents.view', 'documents.share']);

        Livewire::actingAs($sharer)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openShareModal', $document->id)
            ->assertOk();

        // Sharing does not let them upload or delete.
        Livewire::actingAs($sharer)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openUploadModal')
            ->assertForbidden();

        Livewire::actingAs($sharer)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('deleteDocument', $document->id)
            ->assertForbidden();
    }

    public function test_deleting_needs_the_delete_grant(): void
    {
        $document = $this->makeDocument();
        $editor = $this->memberOf($this->project, [
            'documents.view', 'documents.create', 'documents.edit',
        ]);

        foreach (['deleteDocument', 'purgeDocument'] as $action) {
            Livewire::actingAs($editor)
                ->test(ProjectDocuments::class, ['project' => $this->project])
                ->call($action, $document->id)
                ->assertForbidden();
        }

        $this->assertNotNull($document->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | Uploads go through a controller, not a Livewire action
    |---------------------------------------------------------------------------
    */

    public function test_the_upload_endpoint_is_guarded_against_the_location_it_names(): void
    {
        // Holds documents.create on THIS project only.
        $uploader = $this->memberOf($this->project, ['documents.view', 'documents.create']);

        $payload = [
            'file_name' => 'plan.pdf',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
        ];

        // Filing into another project is refused…
        $this->actingAs($uploader)
            ->postJson(route('documents.uploads.init'), $payload + [
                'project_id' => $this->otherProject->id,
            ])
            ->assertForbidden();

        // …and somebody with no grant at all is refused everywhere.
        $reader = $this->memberOf($this->project, ['documents.view']);

        $this->actingAs($reader)
            ->postJson(route('documents.uploads.init'), $payload + [
                'project_id' => $this->project->id,
            ])
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | N7 — who may share
    |---------------------------------------------------------------------------
    */

    public function test_share_is_seeded_exactly_as_it_worked_before(): void
    {
        // Admin and manager could create a share link; an employee could not.
        $manager = Role::where('name', 'manager')->firstOrFail();
        $employee = Role::where('name', 'employee')->firstOrFail();

        $this->assertContains('documents.share', $manager->abilityRows()->pluck('ability')->all());
        $this->assertNotContains('documents.share', $employee->abilityRows()->pluck('ability')->all());
    }

    public function test_share_can_be_taken_away_from_a_manager_on_one_project(): void
    {
        // The difference M12 makes: it is a grant, so it is revocable, and it
        // is answered per project.
        $manager = $this->memberOf($this->project, ['documents.view', 'documents.create']);

        Livewire::actingAs($manager)
            ->test(ProjectDocuments::class, ['project' => $this->project])
            ->call('openShareModal')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | What the catalogue and the templates say
    |---------------------------------------------------------------------------
    */

    public function test_share_is_marked_sensitive_and_documents_are_scoped(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;
        $area = $catalog::area('documents');

        $this->assertSame(['project', 'job_site'], $area['levels']);
        $this->assertTrue($catalog::action('documents.share')['sensitive']);
    }

    public function test_the_seeded_templates_grant_the_expected_document_actions(): void
    {
        $expected = [
            'project-manager' => [
                'documents.view', 'documents.create', 'documents.edit',
                'documents.share', 'documents.see_internal',
            ],
            'procurement' => ['documents.view', 'documents.create'],
            'accounting' => ['documents.view'],
            'client-project' => ['documents.view'],
            'site-supervisor' => ['documents.view', 'documents.create'],
        ];

        foreach ($expected as $key => $abilities) {
            $held = array_values(array_filter(
                PermissionTemplate::where('key', $key)->firstOrFail()->abilities(),
                fn ($a) => str_starts_with($a, 'documents.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong document actions.");
        }
    }

    public function test_a_guest_sees_documents_but_never_the_internal_ones(): void
    {
        $template = PermissionTemplate::where('key', 'client-project')->firstOrFail();

        $this->assertContains('documents.view', $template->abilities());
        $this->assertNotContains('documents.see_internal', $template->abilities());
        $this->assertNotContains('documents.share', $template->abilities());
    }
}
