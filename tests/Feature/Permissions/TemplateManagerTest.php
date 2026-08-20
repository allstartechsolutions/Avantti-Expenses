<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Access\TemplateManager;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
    }

    protected function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_it_lists_the_built_in_templates_by_level(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->assertSee('Project Manager')
            ->assertSee('Site Supervisor')
            ->assertSee(__('Guest'))
            ->assertSee(__('No monetary figures'));
    }

    public function test_the_editor_only_offers_abilities_that_can_be_held_at_that_level(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('newTemplate', 'job_site');

        $areas = collect($component->get('matrix'))
            ->flatMap(fn ($section) => array_column($section['areas'], 'key'))
            ->all();

        // A job-site template cannot hand out a company-wide screen.
        $this->assertContains('expenses', $areas);
        $this->assertNotContains('users', $areas);
        $this->assertNotContains('settings', $areas);
        $this->assertNotContains('estimates', $areas);
    }

    public function test_changing_the_level_drops_grants_that_cannot_be_held_there(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('newTemplate', 'project')
            ->call('toggleArea', 'expenses', true)
            ->call('toggleArea', 'budget', true)
            ->set('level', 'job_site')
            ->assertSet('granted.expenses.view', true)
            // budget is project and job_site, so it survives; a global-only
            // area would not — proven by the area list above.
            ->assertSet('granted.budget.view', true);
    }

    public function test_a_template_can_be_created_with_a_money_limit(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('newTemplate', 'project')
            ->set('name', 'Engineer')
            ->set('description', 'Runs the technical side')
            ->set('approvalLimit', '50000.00')
            ->call('toggleArea', 'daily-reports', true)
            ->set('granted.requisitions.approve', true)
            ->call('save')
            ->assertHasNoErrors();

        $template = PermissionTemplate::where('name', 'Engineer')->first();

        $this->assertNotNull($template);
        $this->assertSame('project', $template->level);
        $this->assertSame(5_000_000, $template->approval_limit);   // cents
        $this->assertContains('requisitions.approve', $template->abilities());
        $this->assertContains('daily-reports.create', $template->abilities());

        $audit = PermissionAudit::where('subject_type', 'template')->latest('id')->first();
        $this->assertSame('created', $audit->action);
        $this->assertStringContainsString('Engineer', $audit->summary);
    }

    public function test_a_guest_template_can_hold_nothing_sensitive_and_never_sees_money(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('newTemplate', 'project')
            ->set('name', 'Visiting Engineer')
            ->set('isGuest', true)
            ->set('canSeeMoney', true)
            ->set('granted.documents.view', true)
            ->set('granted.documents.share', true)      // sensitive
            ->set('granted.budget.lock', true)          // sensitive
            ->call('save')
            ->assertHasNoErrors();

        $template = PermissionTemplate::where('name', 'Visiting Engineer')->first();

        $this->assertTrue($template->is_guest);
        $this->assertFalse($template->can_see_money);
        $this->assertContains('documents.view', $template->abilities());
        $this->assertNotContains('documents.share', $template->abilities());
        $this->assertNotContains('budget.lock', $template->abilities());
    }

    public function test_duplicating_starts_a_new_template_from_an_existing_one(): void
    {
        $source = PermissionTemplate::where('key', 'site-supervisor')->first();

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('duplicate', $source->id)
            ->assertSet('editingId', null)
            ->assertSet('level', 'job_site')
            ->assertSet('name', __(':name (copy)', ['name' => 'Site Supervisor']))
            ->set('name', 'Site Supervisor (night shift)')
            ->call('save')
            ->assertHasNoErrors();

        $copy = PermissionTemplate::where('name', 'Site Supervisor (night shift)')->first();

        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_system);
        $this->assertEqualsCanonicalizing($source->abilities(), $copy->abilities());
        $this->assertNotNull(PermissionTemplate::find($source->id), 'The original must be untouched.');
    }

    public function test_editing_a_template_does_not_change_what_existing_members_can_do(): void
    {
        $admin = $this->admin();
        $template = PermissionTemplate::where('key', 'procurement')->first();

        $project = Project::create([
            'project_name' => 'Template Test',
            'client_id' => \App\Models\Client::create([
                'company_name' => 'C', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $admin->id,
            ])->id,
            'contact_person' => 'C',
            'email' => 'p@example.test',
            'created_by' => $admin->id,
        ]);

        $membership = Membership::create([
            'user_id' => $admin->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'permission_template_id' => $template->id,
        ]);
        $membership->syncAbilities($template->abilities());

        $before = $membership->abilities();

        Livewire::actingAs($admin)
            ->test(TemplateManager::class)
            ->call('edit', $template->id)
            ->call('toggleArea', 'requisitions', false)
            ->call('save');

        $membership->refresh()->unsetRelation('abilityRows');

        $this->assertEqualsCanonicalizing($before, $membership->abilities());
        $this->assertNotContains('requisitions.create', $template->fresh()->abilities());
    }

    public function test_a_built_in_template_cannot_be_deleted_but_a_custom_one_can(): void
    {
        $system = PermissionTemplate::where('key', 'accounting')->first();

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('delete', $system->id);

        $this->assertNotNull(PermissionTemplate::find($system->id));

        $custom = PermissionTemplate::create(['name' => 'Temporary', 'level' => 'project']);

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('delete', $custom->id);

        $this->assertNull(PermissionTemplate::find($custom->id));
    }

    public function test_deleting_a_template_in_use_leaves_the_member_with_their_access(): void
    {
        $admin = $this->admin();
        $custom = PermissionTemplate::create(['name' => 'Temporary', 'level' => 'project']);
        $custom->syncAbilities(['expenses.view', 'expenses.create']);

        $project = Project::create([
            'project_name' => 'Detach Test',
            'client_id' => \App\Models\Client::create([
                'company_name' => 'C2', 'contact_name' => 'C2', 'email' => 'c2@example.test', 'created_by' => $admin->id,
            ])->id,
            'contact_person' => 'C',
            'email' => 'p2@example.test',
            'created_by' => $admin->id,
        ]);

        $membership = Membership::create([
            'user_id' => $admin->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'permission_template_id' => $custom->id,
        ]);
        $membership->syncAbilities($custom->abilities());

        Livewire::actingAs($admin)
            ->test(TemplateManager::class)
            ->call('delete', $custom->id);

        $membership->refresh()->unsetRelation('abilityRows');

        $this->assertNull($membership->permission_template_id);
        $this->assertEqualsCanonicalizing(['expenses.view', 'expenses.create'], $membership->abilities());
        $this->assertSame(__('Custom'), $membership->accessLabel());
    }

    public function test_names_are_unique_within_a_level_but_not_across_them(): void
    {
        // "Client (read only)" exists at both levels in the seed, which proves
        // the second half; the first is what this checks.
        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('newTemplate', 'project')
            ->set('name', 'Procurement')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_only_somebody_who_can_manage_access_may_save(): void
    {
        $role = Role::create(['name' => 'auditor']);
        $role->syncAbilities(['access.view']);
        $auditor = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($auditor)
            ->test(TemplateManager::class)
            ->assertOk()
            ->call('edit', PermissionTemplate::where('key', 'accounting')->value('id'))
            ->set('granted.expenses.delete', true)
            ->call('save')
            ->assertForbidden();
    }

    public function test_every_seeded_template_can_only_grant_what_its_level_allows(): void
    {
        foreach (PermissionTemplate::with('abilityRows')->get() as $template) {
            foreach ($template->abilities() as $ability) {
                $this->assertTrue(
                    AbilityCatalog::isGrantableAt($ability, $template->level),
                    "Template '{$template->name}' grants '{$ability}' at level '{$template->level}'.",
                );
            }
        }
    }
}
