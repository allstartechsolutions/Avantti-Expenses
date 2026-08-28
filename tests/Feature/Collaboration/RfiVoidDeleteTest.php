<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Rfi\RfiShow;
use App\Models\Client;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiReply;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Getting rid of an RFI.
 *
 * Two acts, deliberately not one. **Voiding** retires the record and keeps it:
 * an SI that went out to an outside projetista is somebody else's evidence,
 * and it keeps its number, its question, its replies and everyone it was sent
 * to. **Deleting** destroys it, and is offered only where there is nothing
 * outside to preserve — a draft, or one already voided.
 *
 * Both were declared and never built: `rfis.delete` was a grant nothing
 * implemented, and `void` was a status nothing could reach.
 */
class RfiVoidDeleteTest extends TestCase
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
            'email' => 'torre-a@example.test',
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

    protected function memberOf(array $abilities): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
        ])->syncAbilities(array_merge(['project.view', 'rfis.view'], $abilities));

        app(\App\Services\PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | Voiding keeps the record
    |---------------------------------------------------------------------------
    */

    public function test_an_open_rfi_can_be_voided_with_a_reason(): void
    {
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', 'Resolvido em obra, não é mais necessária.')
            ->call('voidRfi')
            ->assertHasNoErrors();

        $rfi->refresh();

        $this->assertSame(Rfi::VOID, $rfi->status);
        $this->assertTrue($rfi->isVoid());
        $this->assertNotNull($rfi->number, 'It keeps its number.');
        $this->assertSame('Qual perfil usar no caixilho?', $rfi->question, 'And its question.');
    }

    public function test_voiding_requires_a_reason(): void
    {
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', '')
            ->call('voidRfi')
            ->assertHasErrors('voidReason');

        $this->assertSame(Rfi::OPEN, $rfi->fresh()->status);
    }

    public function test_the_reason_is_kept_in_the_activity_log(): void
    {
        $rfi = $this->rfi();

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', 'Aberta por engano.')
            ->call('voidRfi');

        $entry = ActivityLogEntry::where('subject_type', Rfi::class)
            ->where('subject_id', $rfi->id)
            ->where('action', ActivityLogEntry::VOIDED)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('Aberta por engano.', $entry->context['reason'] ?? null);
        $this->assertSame($this->admin->id, $entry->user_id);
    }

    public function test_voiding_drops_the_ball(): void
    {
        $somebody = $this->memberOf(['rfis.edit']);
        $rfi = $this->rfi(['ball_in_court_id' => $somebody->id]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', 'Substituída pela SI-0012.')
            ->call('voidRfi');

        $this->assertNull(
            $rfi->fresh()->ball_in_court_id,
            'A voided RFI is nobody\'s turn.'
        );
    }

    public function test_a_closed_rfi_can_still_be_voided(): void
    {
        // Closing says "answered"; voiding says "this should never have been
        // asked", and the second is sometimes the truth after the fact.
        $rfi = $this->rfi(['status' => Rfi::CLOSED]);

        $this->assertTrue($rfi->canBeVoided());

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', 'Respondida contra o projeto errado.')
            ->call('voidRfi')
            ->assertHasNoErrors();

        $this->assertSame(Rfi::VOID, $rfi->fresh()->status);
    }

    public function test_voiding_twice_is_refused(): void
    {
        $rfi = $this->rfi(['status' => Rfi::VOID]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', 'De novo.')
            ->call('voidRfi')
            ->assertHasErrors('voidReason');
    }

    public function test_voiding_needs_the_edit_grant(): void
    {
        $reader = $this->memberOf([]);
        $rfi = $this->rfi();

        Livewire::actingAs($reader)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->set('voidReason', 'Não deveria conseguir.')
            ->call('voidRfi')
            ->assertForbidden();

        $this->assertSame(Rfi::OPEN, $rfi->fresh()->status);
    }

    public function test_a_voided_rfi_drops_out_of_the_live_list(): void
    {
        $live = $this->rfi(['subject' => 'Ainda viva']);
        $void = $this->rfi(['subject' => 'Anulada', 'status' => Rfi::VOID]);

        $subjects = Rfi::live()->pluck('subject')->all();

        $this->assertContains('Ainda viva', $subjects);
        $this->assertNotContains('Anulada', $subjects);
    }

    /*
    |---------------------------------------------------------------------------
    | Deleting destroys it — and only where that is safe
    |---------------------------------------------------------------------------
    */

    public function test_a_draft_can_be_deleted(): void
    {
        $rfi = $this->rfi(['status' => Rfi::DRAFT]);
        $id = $rfi->id;

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('deleteRfi')
            ->assertRedirect(route('projects.rfis', $this->project->id));

        $this->assertNull(Rfi::find($id));
    }

    public function test_a_voided_rfi_can_be_deleted(): void
    {
        $rfi = $this->rfi(['status' => Rfi::VOID]);
        $id = $rfi->id;

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('deleteRfi');

        $this->assertNull(Rfi::find($id));
    }

    public function test_a_live_rfi_cannot_be_deleted(): void
    {
        foreach ([Rfi::OPEN, Rfi::ANSWERED, Rfi::CLOSED] as $status) {
            $rfi = $this->rfi(['status' => $status]);

            $this->assertFalse($rfi->canBeDeleted(), "A {$status} RFI must be voided, not destroyed.");

            Livewire::actingAs($this->admin)
                ->test(RfiShow::class, ['rfi' => $rfi])
                ->call('deleteRfi')
                ->assertForbidden();

            $this->assertNotNull($rfi->fresh());
        }
    }

    public function test_deleting_needs_its_own_grant(): void
    {
        // Can edit — and therefore void — but not destroy.
        $editor = $this->memberOf(['rfis.edit']);
        $rfi = $this->rfi(['status' => Rfi::DRAFT]);

        Livewire::actingAs($editor)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('deleteRfi')
            ->assertForbidden();

        $this->assertNotNull($rfi->fresh());
    }

    public function test_the_grant_alone_is_enough_when_the_status_allows_it(): void
    {
        $deleter = $this->memberOf(['rfis.edit', 'rfis.delete']);
        $rfi = $this->rfi(['status' => Rfi::DRAFT]);
        $id = $rfi->id;

        Livewire::actingAs($deleter)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('deleteRfi');

        $this->assertNull(Rfi::find($id));
    }

    public function test_deleting_takes_its_replies_and_history_with_it(): void
    {
        // The realistic shape: an RFI that was answered and distributed, then
        // voided, and only then let go of.
        $rfi = $this->rfi();

        $rfi->addReply('Uma resposta.', $this->admin);
        $rfi->logActivity(ActivityLogEntry::UPDATED);
        $rfi->void('Substituída pela SI-0012.');
        $rfi->distribution()->create([
            'user_id' => $this->admin->id,
            'email' => $this->admin->email,
            'name' => $this->admin->name,
            'role' => 'to',
        ]);

        $id = $rfi->id;

        $this->assertGreaterThan(0, RfiReply::where('rfi_id', $id)->count());

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $rfi])
            ->call('deleteRfi');

        $this->assertNull(Rfi::find($id));

        // Replies cascade at the database level; the other two hang off
        // polymorphic columns with no foreign key and would otherwise be left
        // behind for ever.
        $this->assertSame(0, RfiReply::where('rfi_id', $id)->count());
        $this->assertSame(0, ActivityLogEntry::where('subject_type', Rfi::class)->where('subject_id', $id)->count());
        $this->assertSame(0, \App\Models\Collaboration\DistributionEntry::where('distributable_type', Rfi::class)
            ->where('distributable_id', $id)->count());
    }

    /*
    |---------------------------------------------------------------------------
    | What the screen offers
    |---------------------------------------------------------------------------
    */

    public function test_the_screen_offers_void_on_a_live_rfi_and_delete_on_a_draft(): void
    {
        $draft = $this->rfi(['status' => Rfi::DRAFT]);

        Livewire::actingAs($this->admin)
            ->test(RfiShow::class, ['rfi' => $draft])
            ->assertSee(__('collaboration.label.void_rfi'))
            ->assertSee(__('Delete'));

        $open = $this->rfi(['status' => Rfi::OPEN]);

        $page = Livewire::actingAs($this->admin)->test(RfiShow::class, ['rfi' => $open]);

        $this->assertTrue($page->instance()->canVoid);
        $this->assertFalse(
            $page->instance()->canDelete,
            'An RFI that has been seen outside is voided, never destroyed.'
        );
    }

    public function test_neither_is_offered_to_somebody_who_may_only_read(): void
    {
        $reader = $this->memberOf([]);
        $rfi = $this->rfi(['status' => Rfi::DRAFT]);

        $page = Livewire::actingAs($reader)->test(RfiShow::class, ['rfi' => $rfi]);

        $this->assertFalse($page->instance()->canVoid);
        $this->assertFalse($page->instance()->canDelete);
    }
}
