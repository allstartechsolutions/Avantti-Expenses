<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectQuotations;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationVendor;
use App\Models\QuotationVendorItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M8 — Quotations.
 *
 * The first area where money is genuinely committed, and so the first whose
 * actions obey `approval_limit`. Four of the seven grants are held apart on
 * purpose, each answering a question the owner asked:
 *
 *   create_standalone  the half of N1 still open after M7 — a round raised
 *                      with no requisition walks around the approval chain
 *   award_own          N3 — whoever typed a vendor's prices picking that
 *                      vendor, the same shape as M7's self-approval rule
 *   convert            committing an award into one purchase order
 *   convert_contract   committing it into a schedule of future payments
 */
class QuotationTest extends TestCase
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

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
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
                ['company_name' => 'Quo Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-quo@example.test',
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
            'email' => str($name)->slug().'-quo@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * A round with two priced proposals, so it is awardable.
     *
     * @param  array<int, float>  $prices  One total per vendor.
     */
    protected function makeRound(array $prices = [1000.0, 1200.0], array $attributes = []): Quotation
    {
        $quotation = Quotation::create(array_merge([
            'project_id' => $this->project->id,
            'quotation_number' => 'QT-'.str()->random(6),
            'type' => 'material',
            'title' => 'Cement',
            'status' => 'comparing',
            'created_by' => $this->admin->id,
        ], $attributes));

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'item_name' => 'Cement',
            'item_type' => 'custom',
            'quantity' => 1,
            'unit' => 'bag',
            'sort_order' => 0,
        ]);

        foreach ($prices as $i => $price) {
            $vendor = new Vendor;
            $vendor->forceFill([
                'name' => 'Vendor '.$i.' '.str()->random(4),
                'is_supplier' => true,
                'created_by' => $this->admin->id,
            ])->save();


            $row = QuotationVendor::create([
                'quotation_id' => $quotation->id,
                'vendor_id' => $vendor->id,
                'status' => 'responded',
                'responded_at' => now(),
                'created_by' => $this->admin->id,
                'priced_by' => $this->admin->id,
            ]);

            QuotationVendorItem::create([
                'quotation_vendor_id' => $row->id,
                'quotation_item_id' => $item->id,
                'unit_price' => $price,
                'total_amount' => $price,
                'is_unavailable' => false,
            ]);
        }

        return $quotation->fresh(['items', 'quotationVendors.items', 'quotationVendors.vendor']);
    }

    protected function memberOf(Project|JobSite $scope, array $abilities, ?int $limitCents = null): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
            'approval_limit' => $limitCents,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** Pick the cheaper proposal, as the award form would. */
    protected function awardArgs(Quotation $quotation): array
    {
        $row = $quotation->quotationVendors->sortBy(fn ($r) => $r->equalizedTotal())->first();

        return [$row, $row->id];
    }

    /*
    |---------------------------------------------------------------------------
    | The screens answer as they did
    |---------------------------------------------------------------------------
    */

    public function test_the_quotation_screens_answer_as_they_did_for_every_company_wide_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.quotations', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.quotations', $this->site))->assertOk();
        }
    }

    public function test_seeing_quotations_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.quotations', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.quotations', $this->site))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | N1's remaining half — a round with no requisition behind it
    |---------------------------------------------------------------------------
    */

    public function test_raising_a_standalone_round_needs_its_own_grant(): void
    {
        $buyer = $this->memberOf($this->project, ['quotations.view', 'quotations.create']);

        // The button is not offered…
        $this->actingAs($buyer)->get(route('projects.quotations', $this->project))
            ->assertOk()
            ->assertDontSee('wire:click="openAddModal"', escape: false);

        // …the modal will not open…
        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertForbidden();

        // …and a save with no requisition behind it is refused even if the
        // form is driven directly.
        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('quo_title', 'Sand')
            ->call('saveQuotation')
            ->assertForbidden();
    }

    public function test_the_standalone_grant_lets_a_round_be_raised_from_nothing(): void
    {
        $buyer = $this->memberOf($this->project, [
            'quotations.view', 'quotations.create', 'quotations.create_standalone',
        ]);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertOk();
    }

    public function test_no_seeded_role_below_manager_may_raise_a_standalone_round(): void
    {
        $employee = Role::where('name', 'employee')->firstOrFail();
        $manager = Role::where('name', 'manager')->firstOrFail();

        $this->assertNotContains(
            'quotations.create_standalone',
            $employee->abilityRows()->pluck('ability')->all(),
        );

        // A manager keeps it: they can approve the requisition they would
        // otherwise have needed, so nothing is being walked around.
        $this->assertContains(
            'quotations.create_standalone',
            $manager->abilityRows()->pluck('ability')->all(),
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Approval limits — the first area that uses them
    |---------------------------------------------------------------------------
    */

    public function test_an_award_above_the_ceiling_is_refused(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        // Ceiling R$ 1.000, award R$ 5.000.
        $buyer = $this->memberOf(
            $this->project,
            ['quotations.view', 'quotations.award'],
            limitCents: 100000,
        );

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest and available')
            ->call('awardQuotation')
            ->assertForbidden();

        $this->assertSame('comparing', $quotation->fresh()->status);
    }

    public function test_an_award_within_the_ceiling_goes_through(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        $buyer = $this->memberOf(
            $this->project,
            ['quotations.view', 'quotations.award'],
            limitCents: 1000000,      // R$ 10.000
        );

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest and available')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    public function test_no_ceiling_means_no_ceiling(): void
    {
        $quotation = $this->makeRound([50000.0, 60000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        $buyer = $this->memberOf($this->project, ['quotations.view', 'quotations.award']);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Only one who can supply')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    public function test_the_ceiling_binds_conversion_as_well_as_the_award(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        // Awarded by somebody with no ceiling…
        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertSame('awarded', $quotation->fresh()->status);

        // …cannot then be committed by somebody whose ceiling is below it.
        $buyer = $this->memberOf(
            $this->project,
            ['quotations.view', 'quotations.convert'],
            limitCents: 100000,
        );

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('convertAward', $quotation->id)
            ->assertForbidden();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    public function test_the_ceiling_is_read_from_the_membership_of_the_project_in_hand(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);

        $buyer = $this->memberOf(
            $this->project,
            ['quotations.view', 'quotations.award'],
            limitCents: 100000,
        );

        $resolver = app(PermissionResolver::class);

        $this->actingAs($buyer);

        $this->assertSame(100000, $resolver->approvalLimit($buyer, $quotation));
        $this->assertFalse($resolver->withinApprovalLimit($buyer, 500000, $quotation));
        $this->assertTrue($resolver->withinApprovalLimit($buyer, 50000, $quotation));
    }

    /*
    |---------------------------------------------------------------------------
    | N3 — awarding proposals you keyed in yourself
    |---------------------------------------------------------------------------
    */

    public function test_whoever_keyed_the_winning_prices_in_cannot_pick_that_winner(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        $buyer = $this->memberOf($this->project, ['quotations.view', 'quotations.award']);

        // They typed the winning proposal's numbers.
        $row->update(['priced_by' => $buyer->id]);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest')
            ->call('awardQuotation')
            ->assertForbidden();

        $this->assertSame('comparing', $quotation->fresh()->status);
    }

    public function test_keying_in_a_losing_proposal_does_not_block_the_award(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$winner, $winnerId] = $this->awardArgs($quotation);

        $loser = $quotation->quotationVendors->firstWhere('id', '!=', $winner->id);

        $buyer = $this->memberOf($this->project, ['quotations.view', 'quotations.award']);

        // They typed the LOSING vendor's numbers — no conflict.
        $loser->update(['priced_by' => $buyer->id]);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $winnerId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    public function test_the_self_award_block_is_lifted_by_a_grant(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        $buyer = $this->memberOf($this->project, [
            'quotations.view', 'quotations.award', 'quotations.award_own',
        ]);

        $row->update(['priced_by' => $buyer->id]);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    public function test_saving_a_proposal_records_who_typed_it(): void
    {
        $quotation = $this->makeRound();
        $row = $quotation->quotationVendors->first();

        $this->assertSame($this->admin->id, $row->priced_by);
    }

    public function test_no_seeded_role_or_template_may_award_its_own(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $this->assertNotContains(
                'quotations.award_own',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
            );
        }

        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('quotations.award_own', $template->abilities(), $template->key);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Contract conversion is held tighter than a purchase order
    |---------------------------------------------------------------------------
    */

    public function test_converting_a_service_round_needs_the_contract_grant(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0], ['type' => 'service']);
        [$row, $rowId] = $this->awardArgs($quotation);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Best terms')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertTrue($quotation->fresh()->convertsToContract());

        // `convert` alone is not enough for a contract.
        $buyer = $this->memberOf($this->project, ['quotations.view', 'quotations.convert']);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('convertAward', $quotation->id)
            ->assertForbidden();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    public function test_a_purchase_order_round_needs_only_the_ordinary_convert_grant(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);   // material → purchase order
        [$row, $rowId] = $this->awardArgs($quotation);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest')
            ->call('awardQuotation')
            ->assertOk();

        $this->assertFalse($quotation->fresh()->convertsToContract());

        $buyer = $this->memberOf($this->project, ['quotations.view', 'quotations.convert']);

        Livewire::actingAs($buyer)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('convertAward', $quotation->id)
            ->assertOk();

        $this->assertSame('converted', $quotation->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | The ordinary grants
    |---------------------------------------------------------------------------
    */

    public function test_awarding_editing_and_deleting_are_separate_grants(): void
    {
        $quotation = $this->makeRound();

        $reader = $this->memberOf($this->project, ['quotations.view']);

        foreach ([
            ['openAwardModal', [$quotation->id]],
            ['openEditModal', [$quotation->id]],
            ['deleteQuotation', [$quotation->id]],
            ['cancelQuotation', [$quotation->id]],
        ] as [$action, $args]) {
            Livewire::actingAs($reader)
                ->test(ProjectQuotations::class, ['project' => $this->project])
                ->call($action, ...$args)
                ->assertForbidden();
        }

        $this->assertNotNull($quotation->fresh());
    }

    public function test_revoking_an_award_is_the_same_authority_as_making_one(): void
    {
        $quotation = $this->makeRound([5000.0, 6000.0]);
        [$row, $rowId] = $this->awardArgs($quotation);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAwardModal', $quotation->id)
            ->set('awardVendorRowId', $rowId)
            ->set('awardAcknowledgedNorm', true)
            ->set('awardReason', 'Cheapest')
            ->call('awardQuotation')
            ->assertOk();

        $editor = $this->memberOf($this->project, ['quotations.view', 'quotations.edit']);

        Livewire::actingAs($editor)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('revokeAward', $quotation->id)
            ->assertForbidden();

        $this->assertSame('awarded', $quotation->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue's own claims
    |---------------------------------------------------------------------------
    */

    public function test_the_money_actions_are_declared_as_limited(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;

        $this->assertTrue($catalog::isLimited('quotations.award'));
        $this->assertTrue($catalog::isLimited('quotations.convert'));
        $this->assertTrue($catalog::isLimited('quotations.convert_contract'));

        // …and the ones that commit nothing are not.
        $this->assertFalse($catalog::isLimited('quotations.create'));
        $this->assertFalse($catalog::isLimited('quotations.view'));
    }
}
