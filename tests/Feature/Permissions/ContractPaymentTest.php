<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M11 — Contracts & Payments.
 *
 * The largest pass, and the one held back longest: fifteen components across
 * contracts, measurements, the schedule of values, the *aditivos*, and the
 * three company-wide money screens. **Not one of them had a guard of any
 * kind** — recorded in E1, and the owner declined a stopgap so they would be
 * fixed properly here rather than patched ahead of the engine.
 *
 * The contracts area follows the rule M10 set: doing and undoing are the same
 * grant while nothing has moved, and undoing is narrower once it has.
 */
class ContractPaymentTest extends TestCase
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
                ['company_name' => 'Contract Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-ct@example.test',
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
            'email' => str($name)->slug().'-ct@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeContract(array $attributes = [], float $amount = 50000.0): Contract
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => 'Sub '.str()->random(5),
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return Contract::create(array_merge([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ], $attributes));
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

    /*
    |---------------------------------------------------------------------------
    | The screens answer as they did
    |---------------------------------------------------------------------------
    */

    public function test_the_contract_screens_answer_as_they_did_for_every_role(): void
    {
        $contract = $this->makeContract();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.contracts', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.contracts', $this->site))->assertOk();
            $this->actingAs($user)->get(route('contracts.show', $contract))->assertOk();
            $this->actingAs($user)->get(route('contracts.project.create', $this->project))->assertOk();
        }
    }

    public function test_seeing_contracts_is_a_grant_that_can_be_taken_away(): void
    {
        $contract = $this->makeContract();
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.contracts', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.contracts', $this->site))->assertForbidden();
        $this->actingAs($blind)->get(route('contracts.show', $contract))->assertForbidden();
        $this->actingAs($blind)->get(route('contracts.project.create', $this->project))->assertForbidden();
    }

    public function test_creating_editing_and_deleting_are_separate_grants(): void
    {
        $contract = $this->makeContract();
        $reader = $this->memberOf($this->project, ['contracts.view']);

        $this->actingAs($reader)->get(route('contracts.project.create', $this->project))->assertForbidden();
        $this->actingAs($reader)->get(route('contracts.edit', $contract))->assertForbidden();

        Livewire::actingAs($reader)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('delete')
            ->assertForbidden();

        $this->assertNotNull($contract->fresh());
    }

    public function test_changing_a_contracts_status_needs_the_edit_grant(): void
    {
        $contract = $this->makeContract();
        $reader = $this->memberOf($this->project, ['contracts.view']);

        Livewire::actingAs($reader)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('openStatusModal')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Paying — and the ceiling
    |---------------------------------------------------------------------------
    */

    public function test_recording_a_payment_needs_the_pay_grant(): void
    {
        $contract = $this->makeContract();

        $editor = $this->memberOf($this->project, ['contracts.view', 'contracts.edit']);

        Livewire::actingAs($editor)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('openPaymentModal')
            ->assertForbidden();

        Livewire::actingAs($editor)
            ->test(ContractShow::class, ['contract' => $contract])
            ->set('paymentAmount', 100)
            ->call('recordPayment')
            ->assertForbidden();

        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_a_payment_above_the_ceiling_is_refused(): void
    {
        $contract = $this->makeContract();

        $payer = $this->memberOf(
            $this->project,
            ['contracts.view', 'contracts.pay'],
            limitCents: 100000,       // R$ 1.000
        );

        Livewire::actingAs($payer)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('openPaymentModal')
            ->set('paymentAmount', 5000)
            ->set('paymentDate', now()->toDateString())
            ->set('paymentMethod', 'bank_transfer')
            ->call('recordPayment')
            ->assertForbidden();

        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_a_payment_within_the_ceiling_goes_through(): void
    {
        $contract = $this->makeContract();

        $payer = $this->memberOf(
            $this->project,
            ['contracts.view', 'contracts.pay'],
            limitCents: 1000000,      // R$ 10.000
        );

        Livewire::actingAs($payer)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('openPaymentModal')
            ->set('paymentAmount', 5000)
            ->set('paymentDate', now()->toDateString())
            ->set('paymentMethod', 'bank_transfer')
            ->call('recordPayment')
            ->assertOk()
            ->assertHasNoErrors();

        $this->assertSame(1, $contract->payments()->count());
    }

    public function test_releasing_retention_obeys_the_same_ceiling(): void
    {
        $contract = $this->makeContract(['retention_percent' => 5]);

        $payer = $this->memberOf(
            $this->project,
            ['contracts.view', 'contracts.pay'],
            limitCents: 100,          // R$ 1
        );

        Livewire::actingAs($payer)
            ->test(ContractShow::class, ['contract' => $contract])
            ->set('retentionAmount', 500)
            ->call('releaseRetention')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Undoing a payment is the narrow act
    |---------------------------------------------------------------------------
    */

    public function test_taking_a_payment_back_out_needs_its_own_grant(): void
    {
        $contract = $this->makeContract();

        $payment = ContractPayment::create([
            'contract_id' => $contract->id,
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'created_by' => $this->admin->id,
        ]);

        // Somebody who may pay any amount still cannot undo one.
        $payer = $this->memberOf($this->project, ['contracts.view', 'contracts.pay']);

        Livewire::actingAs($payer)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('deletePayment', $payment->id)
            ->assertForbidden();

        $this->assertNotNull($payment->fresh());

        // The narrower grant does it.
        $auditor = $this->memberOf($this->project, ['contracts.view', 'contracts.unpay']);

        Livewire::actingAs($auditor)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('deletePayment', $payment->id)
            ->assertOk();

        $this->assertNull($payment->fresh());
    }

    public function test_no_seeded_role_or_template_may_undo_a_payment(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $this->assertNotContains(
                'contracts.unpay',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
                $name,
            );
        }

        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('contracts.unpay', $template->abilities(), $template->key);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | The three company-wide money screens
    |---------------------------------------------------------------------------
    */

    public function test_the_money_screens_are_grants_now_rather_than_open_doors(): void
    {
        // Reproduced: the seeded roles still reach all three.
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('payments.index'))->assertOk();
            $this->actingAs($user)->get(route('contract-payments.index'))->assertOk();
            $this->actingAs($user)->get(route('payment-batches.index'))->assertOk();
        }

        // The difference is that it is a grant.
        $blind = $this->roleWith(['projects.view', 'project.view']);

        foreach (['payments.index', 'contract-payments.index', 'payment-batches.index'] as $name) {
            $this->actingAs($blind)->get(route($name))->assertForbidden();
        }
    }

    public function test_seeing_the_payments_and_building_batches_are_separate_grants(): void
    {
        $reader = $this->roleWith(['projects.view', 'project.view', 'payments.view']);

        $this->actingAs($reader)->get(route('payments.index'))->assertOk();
        $this->actingAs($reader)->get(route('contract-payments.index'))->assertOk();

        // Batches are their own grant, and so is the screen that builds them.
        $this->actingAs($reader)->get(route('payment-batches.index'))->assertForbidden();
        $this->actingAs($reader)->get(route('payment-batches.create'))->assertForbidden();

        $batcher = $this->roleWith(['projects.view', 'project.view', 'payments.batch']);

        $this->actingAs($batcher)->get(route('payment-batches.index'))->assertOk();
        $this->actingAs($batcher)->get(route('payment-batches.create'))->assertOk();
    }

    public function test_the_contract_payments_export_needs_the_view_grant(): void
    {
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($blind)->get(route('contract-payments.pdf.view'))->assertForbidden();
    }

    public function test_the_payments_menu_entries_follow_the_grants(): void
    {
        $reader = $this->roleWith(['projects.view', 'project.view', 'payments.view']);
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($reader)->get(route('projects.index'))
            ->assertOk()
            ->assertSee(route('payments.index'))
            ->assertDontSee(route('payment-batches.index'));

        $this->actingAs($blind)->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee(route('payments.index'))
            ->assertDontSee(route('contract-payments.index'));
    }

    /*
    |---------------------------------------------------------------------------
    | Files
    |---------------------------------------------------------------------------
    */

    public function test_a_contract_file_is_only_served_to_somebody_who_may_see_it(): void
    {
        Storage::fake('local');
        Storage::put('contracts/signed.pdf', 'x');

        $this->makeContract([
            'job_site_id' => $this->site->id,
            'contract_file_path' => 'contracts/signed.pdf',
        ]);

        $reader = $this->memberOf($this->site, ['contracts.view']);
        $outsider = $this->memberOf($this->makeSite($this->project, 'Site B'), ['contracts.view']);

        $this->actingAs($reader)
            ->get(route('files.show', ['path' => 'contracts/signed.pdf']))->assertOk();
        $this->actingAs($outsider)
            ->get(route('files.show', ['path' => 'contracts/signed.pdf']))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | What the catalogue and the seeds say
    |---------------------------------------------------------------------------
    */

    public function test_paying_is_limited_and_the_rest_are_not(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;

        $this->assertTrue($catalog::isLimited('contracts.pay'));
        $this->assertFalse($catalog::isLimited('contracts.measure'));
        $this->assertFalse($catalog::isLimited('contracts.unpay'));

        // This case recorded a limitation when M11 was built: `payments` is a
        // company-wide area, `approval_limit` lived only on a membership and a
        // template, and so the payments dashboard was the one way round a
        // ceiling that bound everywhere else (P13, P19).
        //
        // F0 gave the ceiling a company-wide home and F1 made this act obey it,
        // so the case now records the opposite. It is the same act as
        // `contracts.pay` and it answers to the same number.
        $this->assertSame(['global'], $catalog::area('payments')['levels']);
        $this->assertTrue($catalog::isLimited('payments.pay'));
    }

    public function test_the_seeded_templates_grant_the_expected_contract_actions(): void
    {
        $expected = [
            'project-manager' => [
                'contracts.view', 'contracts.create', 'contracts.edit',
                'contracts.measure', 'contracts.pay',
            ],
            'procurement' => ['contracts.view'],
            'accounting' => ['contracts.view', 'contracts.pay'],
            'site-supervisor' => [],
        ];

        foreach ($expected as $key => $abilities) {
            $held = array_values(array_filter(
                PermissionTemplate::where('key', $key)->firstOrFail()->abilities(),
                fn ($a) => str_starts_with($a, 'contracts.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong contract actions.");
        }
    }
}
