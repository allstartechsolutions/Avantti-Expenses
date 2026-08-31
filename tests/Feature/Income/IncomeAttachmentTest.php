<?php

namespace Tests\Feature\Income;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteIncome;
use App\Livewire\Project\ProjectIncome;
use App\Models\Client;
use App\Models\Income;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Files dropped on an income record, at both levels.
 *
 * The queue is what the form saves; the box beside it is only what the drop
 * zone writes to. What matters is that two drags both arrive, that a file the
 * rule refuses is said rather than left in a box nothing on screen can empty,
 * and that a file taken off the queue does not go up anyway.
 */
class IncomeAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Income Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-inc@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site-inc@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Files arrive one drag at a time, so a second drop must add to the queue.
     *
     * Bound straight to the queue — as this form was — Livewire's
     * `uploadMultiple` runs with `append = false` and the first batch is
     * replaced with nothing on screen to say so.
     */
    public function test_files_dropped_in_two_goes_are_all_attached(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('income_date', now()->toDateString())
            ->set('income_title', 'Medição 1')
            ->set('income_amount', 5000)
            ->set('income_new_uploads', [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')])
            // The box is emptied for the next drop, and the queue holds the first.
            ->assertSet('income_new_uploads', [])
            ->set('income_new_uploads', [UploadedFile::fake()->image('foto.jpg')])
            ->assertCount('income_uploads', 2)
            ->call('saveIncome')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['nota.pdf', 'foto.jpg'],
            Income::first()->attachments()->pluck('original_name')->all(),
        );
    }

    /** A file taken off the queue does not go up with the rest. */
    public function test_a_file_can_be_taken_off_before_saving(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('income_date', now()->toDateString())
            ->set('income_title', 'Medição 1')
            ->set('income_amount', 5000)
            ->set('income_new_uploads', [
                UploadedFile::fake()->create('mantida.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('removida.pdf', 10, 'application/pdf'),
            ])
            ->call('removeIncomeUpload', 1)
            ->assertCount('income_uploads', 1)
            ->call('saveIncome')
            ->assertHasNoErrors();

        $this->assertSame(['mantida.pdf'], Income::first()->attachments()->pluck('original_name')->all());
    }

    /**
     * A file of the wrong type is refused without wedging the form.
     *
     * Left in the box it would be invisible — the list on screen is the queue,
     * not the box — and the save would fail for ever on a file with no button
     * to remove it. And the refusal must not clear the messages the rest of
     * the form is already showing.
     */
    public function test_a_refused_file_does_not_block_the_save_or_clear_other_errors(): void
    {
        Storage::fake('local');

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectIncome::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('income_date', now()->toDateString())
            ->set('income_title', '')
            ->set('income_amount', 5000)
            ->call('saveIncome')
            ->assertHasErrors('income_title')
            ->set('income_new_uploads', [UploadedFile::fake()->create('planilha.xlsx', 10)]);

        $component->assertHasErrors('income_new_uploads')
            ->assertSet('income_new_uploads', [])
            ->assertCount('income_uploads', 0)
            // The title message is still there, because the title is still empty.
            ->assertHasErrors('income_title');

        $component->set('income_title', 'Medição 1')
            ->call('saveIncome')
            ->assertHasNoErrors();

        $this->assertSame(1, Income::count());
        $this->assertSame(0, Income::first()->attachments()->count());
    }

    /** The job site level carries the same behaviour as the project level. */
    public function test_the_job_site_level_keeps_both_drops_too(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)
            ->test(JobSiteIncome::class, ['jobSite' => $this->site])
            ->call('openAddModal')
            ->set('income_date', now()->toDateString())
            ->set('income_title', 'Medição 1')
            ->set('income_amount', 2500)
            ->set('income_new_uploads', [UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')])
            ->set('income_new_uploads', [UploadedFile::fake()->create('recibo.pdf', 10, 'application/pdf')])
            ->assertCount('income_uploads', 2)
            ->call('saveIncome')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['nota.pdf', 'recibo.pdf'],
            Income::first()->attachments()->pluck('original_name')->all(),
        );
    }
}
