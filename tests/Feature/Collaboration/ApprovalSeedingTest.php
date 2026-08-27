<?php

namespace Tests\Feature\Collaboration;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Livewire\Approval\ApprovalSeedFromBudget;
use App\Models\Approval;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Client;
use App\Models\CostCode;
use App\Models\CostCodeTemplate;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\Collaboration\ApprovalSeeder;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Seeding approvals from the orçamento.
 *
 * The two signals, the copy-forward that makes one of them possible at all,
 * and the rule that nothing is created without somebody confirming it.
 */
class ApprovalSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
        $this->seed(CollaborationResponseCodeSeeder::class);

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

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

        $this->budget = Budget::create([
            'project_id' => $this->project->id,
            'name' => 'Orçamento',
            'created_by' => $this->admin->id,
        ]);
    }

    /** $amount is in the major unit, as the accessor expects. */
    protected function line(string $code, string $name, float $amount = 1000, array $extra = []): BudgetItem
    {
        return BudgetItem::create(array_merge([
            'budget_id' => $this->budget->id,
            'code' => $code,
            'name' => $name,
            'budgeted_amount' => $amount,
        ], $extra));
    }

    /**
     * Not named `seeder()`: `RefreshDatabase` reads `$this->seeder` to decide
     * which database seeder to run, and a method of that name is picked up as
     * the property, handing the artisan command an object where it wants a
     * class name.
     */
    protected function approvalSeeder(): ApprovalSeeder
    {
        return app(ApprovalSeeder::class);
    }

    /*
    |---------------------------------------------------------------------------
    | The copy-forward — the thing discovery said would silently not work
    |---------------------------------------------------------------------------
    */

    /**
     * A budget item carries no `cost_code_id`, so a flag left on `cost_codes`
     * could never be read from the budget line. It has to travel with the copy.
     */
    public function test_the_flag_travels_from_the_template_into_the_budget(): void
    {
        $template = CostCodeTemplate::create(['name' => 'Padrão', 'created_by' => $this->admin->id]);

        $parent = CostCode::create([
            'template_id' => $template->id,
            'code' => '07',
            'name' => 'Revestimentos',
            'sort_order' => 1,
            'requires_approval' => true,
            'default_approval_type' => Approval::TYPE_MATERIAL,
        ]);

        CostCode::create([
            'template_id' => $template->id,
            'parent_id' => $parent->id,
            'code' => '07.100',
            'name' => 'Porcelanato',
            'sort_order' => 1,
            'requires_approval' => true,
            'default_approval_type' => Approval::TYPE_SAMPLE,
        ]);

        CostCode::create([
            'template_id' => $template->id,
            'parent_id' => $parent->id,
            'code' => '07.200',
            'name' => 'Rejunte',
            'sort_order' => 2,
        ]);

        $budget = Budget::create([
            'project_id' => $this->project->id,
            'name' => 'Do template',
            'created_by' => $this->admin->id,
        ]);

        $budget->applyTemplate($template);

        $porcelanato = $budget->items()->where('code', '07.100')->first();
        $rejunte = $budget->items()->where('code', '07.200')->first();

        $this->assertTrue($porcelanato->requires_approval);
        $this->assertSame(Approval::TYPE_SAMPLE, $porcelanato->default_approval_type);

        // And a code that was never flagged stays unflagged.
        $this->assertFalse($rejunte->requires_approval);
        $this->assertNull($rejunte->default_approval_type);
    }

    /*
    |---------------------------------------------------------------------------
    | The two signals
    |---------------------------------------------------------------------------
    */

    public function test_a_flagged_line_is_suggested_whatever_it_costs(): void
    {
        $cheap = $this->line('07.100', 'Porcelanato', 50, ['requires_approval' => true]);
        $this->line('03.100', 'Concreto', 900000);

        $candidates = $this->approvalSeeder()->candidates($this->project)->keyBy(fn ($r) => $r['item']->id);

        $this->assertTrue($candidates[$cheap->id]['suggested']);
        $this->assertSame(['flagged'], $candidates[$cheap->id]['reasons']);
    }

    /** No threshold set means value suggests nothing — the sane default. */
    public function test_with_no_threshold_only_flagged_lines_are_suggested(): void
    {
        $flagged = $this->line('07.100', 'Porcelanato', 100, ['requires_approval' => true]);
        $expensive = $this->line('03.100', 'Concreto', 900000);

        $candidates = $this->approvalSeeder()->candidates($this->project)->keyBy(fn ($r) => $r['item']->id);

        $this->assertTrue($candidates[$flagged->id]['suggested']);
        $this->assertFalse($candidates[$expensive->id]['suggested']);
    }

    /**
     * The threshold is in cents and so is the column behind the accessor.
     * Comparing the accessor's reais against centavos would suggest almost
     * nothing, which is the sort of bug that looks like "the feature is quiet".
     */
    public function test_the_threshold_compares_like_with_like(): void
    {
        $this->project->update(['approval_seed_threshold' => 500000]);  // R$ 5.000,00

        $over = $this->line('03.100', 'Concreto', 6000);   // R$ 6.000,00
        $under = $this->line('07.100', 'Rejunte', 400);    // R$ 400,00

        $candidates = $this->approvalSeeder()->candidates($this->project->fresh())->keyBy(fn ($r) => $r['item']->id);

        $this->assertTrue($candidates[$over->id]['suggested'], 'A line above the threshold was not suggested.');
        $this->assertSame(['threshold'], $candidates[$over->id]['reasons']);
        $this->assertFalse($candidates[$under->id]['suggested']);
    }

    public function test_a_line_can_be_suggested_by_both_signals(): void
    {
        $this->project->update(['approval_seed_threshold' => 100000]);

        $both = $this->line('07.100', 'Esquadria', 9000, ['requires_approval' => true]);

        $candidates = $this->approvalSeeder()->candidates($this->project->fresh())->keyBy(fn ($r) => $r['item']->id);

        $this->assertEqualsCanonicalizing(['flagged', 'threshold'], $candidates[$both->id]['reasons']);
    }

    /** A parent line is a heading; the work is on its children. */
    public function test_parent_lines_are_not_offered(): void
    {
        $parent = $this->line('07', 'Revestimentos', 0);
        $child = $this->line('07.100', 'Porcelanato', 500, ['parent_id' => $parent->id]);

        $ids = $this->approvalSeeder()->candidates($this->project)->pluck('item.id')->all();

        $this->assertContains($child->id, $ids);
        $this->assertNotContains($parent->id, $ids);
    }

    /*
    |---------------------------------------------------------------------------
    | The type guess
    |---------------------------------------------------------------------------
    */

    public function test_the_flags_own_type_wins_where_it_is_set(): void
    {
        $item = $this->line('03.100', 'Concreto usinado', 100, [
            'requires_approval' => true,
            'default_approval_type' => Approval::TYPE_MATERIAL,
        ]);

        // Even though the wording says "concreto", the decision already made
        // on the code is the one that counts.
        $this->assertSame(Approval::TYPE_MATERIAL, $this->approvalSeeder()->typeFor($item));
    }

    public function test_the_wording_is_a_fair_guess_when_nothing_is_set(): void
    {
        $this->assertSame(
            Approval::TYPE_CERTIFICATE,
            $this->approvalSeeder()->typeFor($this->line('03.100', 'Concreto usinado FCK 30')),
        );
        $this->assertSame(
            Approval::TYPE_SHOP_DRAWING,
            $this->approvalSeeder()->typeFor($this->line('12.100', 'Esquadria de alumínio')),
        );
        $this->assertSame(
            Approval::TYPE_MATERIAL,
            $this->approvalSeeder()->typeFor($this->line('07.100', 'Porcelanato 90x90')),
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Creating
    |---------------------------------------------------------------------------
    */

    public function test_confirmed_lines_become_drafts(): void
    {
        $a = $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);
        $b = $this->line('12.100', 'Esquadria', 800, ['requires_approval' => true]);

        $created = $this->approvalSeeder()->seed($this->project, [$a->id, $b->id], [], $this->admin);

        $this->assertSame(2, $created->count());

        $approval = Approval::where('budget_item_id', $a->id)->first();
        $this->assertSame(Approval::DRAFT, $approval->status);
        $this->assertSame('07.100 Porcelanato', $approval->title);
        $this->assertSame(0, $approval->revisions()->count());
    }

    /** Nothing is created that nobody ticked. */
    public function test_an_unticked_line_is_not_created(): void
    {
        $ticked = $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);
        $untouched = $this->line('12.100', 'Esquadria', 800, ['requires_approval' => true]);

        $this->approvalSeeder()->seed($this->project, [$ticked->id], [], $this->admin);

        $this->assertSame(1, Approval::count());
        $this->assertNull(Approval::where('budget_item_id', $untouched->id)->first());
    }

    /** Ids came from a screen: a line from another project must not be used. */
    public function test_a_budget_line_from_another_project_is_ignored(): void
    {
        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);
        $otherBudget = Budget::create([
            'project_id' => $other->id,
            'name' => 'Outro',
            'created_by' => $this->admin->id,
        ]);
        $foreign = BudgetItem::create([
            'budget_id' => $otherBudget->id,
            'code' => '99',
            'name' => 'De outra obra',
            'budgeted_amount' => 1000,
        ]);

        $created = $this->approvalSeeder()->seed($this->project, [$foreign->id], [], $this->admin);

        $this->assertSame(0, $created->count());
        $this->assertSame(0, Approval::count());
    }

    /** Seeding twice must not raise a second approval for the same line. */
    public function test_a_line_already_covered_is_not_seeded_again(): void
    {
        $item = $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);

        $this->approvalSeeder()->seed($this->project, [$item->id], [], $this->admin);
        $second = $this->approvalSeeder()->seed($this->project, [$item->id], [], $this->admin);

        $this->assertSame(0, $second->count());
        $this->assertSame(1, Approval::count());
    }

    public function test_an_unknown_type_falls_back_to_the_guess(): void
    {
        $item = $this->line('03.100', 'Concreto usinado', 500, ['requires_approval' => true]);

        $this->approvalSeeder()->seed($this->project, [$item->id], [$item->id => 'not-a-type'], $this->admin);

        $this->assertSame(Approval::TYPE_CERTIFICATE, Approval::first()->type);
    }

    /** A job site's own budget line puts its approval on that site. */
    public function test_an_approval_follows_its_budget_line_to_a_job_site(): void
    {
        $site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $siteBudget = Budget::create([
            'project_id' => $this->project->id,
            'job_site_id' => $site->id,
            'name' => 'Da torre',
            'created_by' => $this->admin->id,
        ]);

        $item = BudgetItem::create([
            'budget_id' => $siteBudget->id,
            'code' => '07.100',
            'name' => 'Porcelanato',
            'budgeted_amount' => 500,
            'requires_approval' => true,
        ]);

        $this->approvalSeeder()->seed($this->project, [$item->id], [], $this->admin);

        $this->assertSame($site->id, Approval::first()->job_site_id);
    }

    /*
    |---------------------------------------------------------------------------
    | The screen
    |---------------------------------------------------------------------------
    */

    public function test_the_screen_pre_ticks_what_the_signals_suggest(): void
    {
        $flagged = $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);
        $plain = $this->line('07.200', 'Rejunte', 100);

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project]);

        $selected = $component->get('selected');

        $this->assertTrue($selected[$flagged->id]);
        $this->assertFalse($selected[$plain->id]);
    }

    public function test_the_screen_creates_only_what_is_ticked(): void
    {
        $a = $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);
        $b = $this->line('07.200', 'Rejunte', 100);

        Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project])
            ->set('selected.'.$b->id, true)
            ->call('generate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, Approval::count());
    }

    public function test_creating_nothing_says_so_rather_than_doing_nothing(): void
    {
        $this->line('07.200', 'Rejunte', 100);

        Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project])
            ->call('selectNone')
            ->call('generate')
            ->assertHasErrors('selected');

        $this->assertSame(0, Approval::count());
    }

    public function test_select_all_and_back_to_suggested(): void
    {
        $flagged = $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);
        $plain = $this->line('07.200', 'Rejunte', 100);

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project])
            ->call('selectAll');

        $this->assertTrue($component->get('selected')[$plain->id]);

        $component->call('resetToSuggested');

        $this->assertFalse($component->get('selected')[$plain->id]);
        $this->assertTrue($component->get('selected')[$flagged->id]);
    }

    /** The threshold is a project setting, so it survives the visit. */
    public function test_the_threshold_is_saved_on_the_project(): void
    {
        $this->line('03.100', 'Concreto', 6000);

        Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project])
            ->set('threshold', '5000')
            ->call('applyThreshold')
            ->assertHasNoErrors();

        $this->assertSame(500000, (int) $this->project->fresh()->approval_seed_threshold);
    }

    public function test_an_empty_budget_says_to_build_one_first(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project])
            ->assertSee(__('collaboration.message.project_budget_lines'))
            ->assertSee(__('collaboration.help.build_budget_first_screen_works'));
    }

    /*
    |---------------------------------------------------------------------------
    | Cost of the scan
    |---------------------------------------------------------------------------
    */

    /**
     * Seeding must not lazy-load `budget` per line.
     *
     * It did, inside the transaction that holds the number sequence's row
     * lock — so seeding a large budget held that lock across one round trip
     * per approval, blocking every other document being numbered on the
     * project.
     */
    public function test_seeding_does_not_read_the_budget_once_per_line(): void
    {
        $ids = [];
        foreach (range(1, 10) as $i) {
            $ids[] = $this->line(sprintf('07.%03d', $i), "Linha {$i}", 500, ['requires_approval' => true])->id;
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->approvalSeeder()->seed($this->project, $ids, [], $this->admin);
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $budgetReads = collect($queries)
            ->filter(fn ($q) => str_contains($q['query'], 'from "budgets"') || str_contains($q['query'], 'from `budgets`'))
            ->count();

        // One eager load for the whole set — not one read per line.
        $this->assertLessThan(
            5,
            $budgetReads,
            "The budget table was read {$budgetReads} times for 10 lines; it should be eager-loaded.",
        );
        $this->assertSame(10, \App\Models\Approval::count());
    }

    /** The screen scans the budget once per request, not once per call. */
    public function test_the_screen_scans_the_budget_once(): void
    {
        foreach (range(1, 5) as $i) {
            $this->line(sprintf('07.%03d', $i), "Linha {$i}", 500, ['requires_approval' => true]);
        }

        $component = Livewire::actingAs($this->admin)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $component->call('selectAll')->call('resetToSuggested');
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $scans = collect($queries)
            ->filter(fn ($q) => str_contains($q['query'], 'budget_items'))
            ->count();

        // Two calls plus the re-render used to mean three or more full scans.
        $this->assertLessThanOrEqual(
            2,
            $scans,
            "The budget was scanned {$scans} times across two clicks.",
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Permissions
    |---------------------------------------------------------------------------
    */

    public function test_seeding_is_refused_without_the_grant(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);
        // Everything but the seed grant.
        $membership->syncAbilities(AbilityCatalog::filter(
            ['project.view', 'approvals.view', 'approvals.create', 'approvals.edit'],
            'project',
        ));

        Livewire::actingAs($user)
            ->test(ApprovalSeedFromBudget::class, ['project' => $this->project])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('projects.approvals.seed', $this->project))
            ->assertForbidden();
    }

    public function test_the_page_renders_through_its_route(): void
    {
        $this->line('07.100', 'Porcelanato', 500, ['requires_approval' => true]);

        $this->actingAs($this->admin)
            ->get(route('projects.approvals.seed', $this->project))
            ->assertOk()
            ->assertSee('Porcelanato')
            ->assertSee(__('collaboration.label.marked_needing_approval'));
    }
}
