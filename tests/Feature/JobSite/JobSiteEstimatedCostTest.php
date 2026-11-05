<?php

namespace Tests\Feature\JobSite;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteOverview;
use App\Livewire\Project\ProjectJobSites;
use App\Livewire\Project\ProjectShow;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client request 22 (Nov 2026): what a lot is expected to cost the
 * subcontractor before any contract exists. Information only — it shows on
 * the overview and never moves the profit.
 */
class JobSiteEstimatedCostTest extends TestCase
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

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Riverside',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'riverside@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Lot 12',
            'contact_person' => 'C',
            'email' => 'lot12@example.test',
            'job_amount' => 50000,
            'status' => JobSiteStatus::IN_PROGRESS,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_it_is_saved_from_both_job_site_forms_and_cleared_when_blanked(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectJobSites::class, ['project' => $this->project])
            ->call('editJobSite', $this->site->id)
            ->assertSet('estimated_cost', '')
            ->set('estimated_cost', '18500.50')
            ->call('saveJobSite')
            ->assertHasNoErrors();

        $this->assertSame('18500.50', $this->site->fresh()->estimated_cost);

        Livewire::actingAs($this->admin)
            ->test(ProjectShow::class, ['project' => $this->project])
            ->call('editJobSite', $this->site->id)
            ->assertSet('estimated_cost', '18500.50')
            ->set('estimated_cost', '')
            ->call('saveJobSite')
            ->assertHasNoErrors();

        $this->assertNull($this->site->fresh()->estimated_cost);
    }

    public function test_a_negative_estimate_is_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectJobSites::class, ['project' => $this->project])
            ->call('editJobSite', $this->site->id)
            ->set('estimated_cost', '-1')
            ->call('saveJobSite')
            ->assertHasErrors(['estimated_cost' => 'min']);
    }

    public function test_the_overview_shows_it_and_the_profit_ignores_it(): void
    {
        $before = Livewire::actingAs($this->admin)
            ->test(JobSiteOverview::class, ['jobSite' => $this->site])
            ->assertSee(__('Estimated cost'))
            ->assertSee(__('Not set — add it when editing the job site.'))
            ->html();

        $this->site->update(['estimated_cost' => 18500]);

        $after = Livewire::actingAs($this->admin)
            ->test(JobSiteOverview::class, ['jobSite' => $this->site->fresh()])
            ->assertSee('$18,500.00')
            ->assertSee(__('No contract yet. Information only, not in the profit.'))
            ->html();

        // The profit card reads the same with or without an estimate.
        $profit = fn (string $html) => str($html)->after(__('Profit'))->before(__('Contract Value − Expenses − Contracts'))->toString();
        $this->assertStringContainsString('$50,000.00', $profit($after));
        $this->assertSame($profit($before), $profit($after));
    }
}
