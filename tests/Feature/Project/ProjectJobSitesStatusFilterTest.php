<?php

namespace Tests\Feature\Project;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectJobSites;
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
 * The status filter on a project's job site list: chips with a count per
 * status, combined with the search, and an empty state that offers to clear
 * the filters instead of inviting the user to create a first job site.
 */
class ProjectJobSitesStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

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
    }

    protected function makeSite(string $name, JobSiteStatus $status, ?Project $project = null): JobSite
    {
        return JobSite::create([
            'project_id' => ($project ?? $this->project)->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_list_narrows_to_one_status_and_counts_every_status(): void
    {
        $this->makeSite('Lot Alpha', JobSiteStatus::IN_PROGRESS);
        $this->makeSite('Lot Bravo', JobSiteStatus::IN_PROGRESS);
        $this->makeSite('Lot Charlie', JobSiteStatus::COMPLETED);
        $this->makeSite('Lot Delta', JobSiteStatus::ON_HOLD);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectJobSites::class, ['project' => $this->project])
            ->assertViewHas('totalJobSites', 4)
            ->assertViewHas('statusCounts', fn ($counts) => (int) $counts['in_progress'] === 2 && ! isset($counts['created']))
            ->assertSee(['Lot Alpha', 'Lot Charlie', 'Lot Delta']);

        $component->set('statusFilter', 'in_progress')
            ->assertSee(['Lot Alpha', 'Lot Bravo'])
            ->assertDontSee(['Lot Charlie', 'Lot Delta'])
            ->assertViewHas('totalJobSites', 4);

        $component->set('statusFilter', 'completed')->set('jobSiteSearch', 'Charlie')
            ->assertSee('Lot Charlie')
            ->assertDontSee('Lot Alpha');

        $component->call('clearFilters')
            ->assertSet('statusFilter', '')
            ->assertSet('jobSiteSearch', '')
            ->assertSee(['Lot Alpha', 'Lot Bravo', 'Lot Charlie', 'Lot Delta']);
    }

    public function test_a_filter_that_matches_nothing_offers_to_clear_it(): void
    {
        $this->makeSite('Lot Alpha', JobSiteStatus::IN_PROGRESS);

        Livewire::actingAs($this->admin)
            ->test(ProjectJobSites::class, ['project' => $this->project])
            ->set('statusFilter', 'completed')
            ->assertDontSee('Lot Alpha')
            ->assertSee('No lots match your filters.')
            ->assertSee('Clear Filters')
            ->assertDontSee('Get started by creating');
    }

    public function test_an_unknown_status_from_the_browser_filters_nothing(): void
    {
        $this->makeSite('Lot Alpha', JobSiteStatus::IN_PROGRESS);

        Livewire::actingAs($this->admin)
            ->test(ProjectJobSites::class, ['project' => $this->project])
            ->set('statusFilter', "x' or 1=1")
            ->assertSee('Lot Alpha')
            ->assertViewHas('activeStatusFilter', null);
    }

    public function test_another_projects_job_sites_neither_show_nor_count(): void
    {
        $this->makeSite('Lot Alpha', JobSiteStatus::IN_PROGRESS);

        $other = $this->project->replicate(['email']);
        $other->forceFill(['project_name' => 'Elsewhere', 'email' => 'elsewhere@example.test'])->save();
        $this->makeSite('Lot Zulu', JobSiteStatus::IN_PROGRESS, $other);

        Livewire::actingAs($this->admin)
            ->test(ProjectJobSites::class, ['project' => $this->project])
            ->set('statusFilter', 'in_progress')
            ->assertSee('Lot Alpha')
            ->assertDontSee('Lot Zulu')
            ->assertViewHas('statusCounts', fn ($counts) => (int) $counts['in_progress'] === 1);
    }
}
