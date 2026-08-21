<?php

namespace Tests\Feature\Permissions;

use App\Livewire\Estimate\EstimateShow;
use App\Livewire\Invoice\InvoiceShow;
use App\Livewire\Invoice\PublicInvoicePay;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M15 — Estimates & Invoices.
 *
 * The last two of the six money screens E1 recorded as reachable by anybody
 * signed in. Both areas are **company-wide**: an estimate belongs to a client,
 * not to a project, so their grants are held by role.
 *
 * The module also has something no other has: **a public pay link**. An
 * unauthenticated visitor settles an invoice through CardPointe using a token
 * in the URL. That is a token boundary like the document share link, not a
 * permissions question, so it is deliberately not guarded — but its boundary is
 * tested here, because "not a permissions question" is a claim worth proving.
 */
class EstimateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->client = Client::create([
            'company_name' => 'Acme',
            'contact_name' => 'A',
            'email' => 'a@example.test',
            'created_by' => $this->admin->id,
        ]);
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

    protected function makeEstimate(array $attributes = []): Estimate
    {
        return Estimate::create(array_merge([
            'client_id' => $this->client->id,
            'estimate_number' => 'EST-'.str()->random(5),
            'estimate_date' => now()->toDateString(),
            'terms' => 'net_30',
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function makeInvoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-'.str()->random(5),
            'invoice_date' => now()->toDateString(),
            'terms' => 'net_30',
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'payment_token' => (string) str()->uuid(),
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Reproduced, then revocable
    |---------------------------------------------------------------------------
    */

    public function test_the_screens_answer_as_they_did_for_every_role(): void
    {
        $estimate = $this->makeEstimate();
        $invoice = $this->makeInvoice();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('estimates.index'))->assertOk();
            $this->actingAs($user)->get(route('estimates.show', $estimate))->assertOk();
            $this->actingAs($user)->get(route('invoices.index'))->assertOk();
            $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk();
        }
    }

    public function test_both_areas_can_now_be_taken_away(): void
    {
        $estimate = $this->makeEstimate();
        $invoice = $this->makeInvoice();
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($blind)->get(route('estimates.index'))->assertForbidden();
        $this->actingAs($blind)->get(route('estimates.show', $estimate))->assertForbidden();
        $this->actingAs($blind)->get(route('estimates.create'))->assertForbidden();

        $this->actingAs($blind)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($blind)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($blind)->get(route('invoices.create'))->assertForbidden();
    }

    public function test_estimates_and_invoices_are_separate_grants(): void
    {
        $estimate = $this->makeEstimate();
        $invoice = $this->makeInvoice();

        $estimator = $this->roleWith(['projects.view', 'project.view', 'estimates.view']);

        $this->actingAs($estimator)->get(route('estimates.show', $estimate))->assertOk();
        $this->actingAs($estimator)->get(route('invoices.show', $invoice))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Sending, paying and refunding are each their own grant
    |---------------------------------------------------------------------------
    */

    public function test_sending_to_the_client_is_its_own_grant(): void
    {
        $estimate = $this->makeEstimate();

        $reader = $this->roleWith(['projects.view', 'project.view', 'estimates.view', 'estimates.edit']);

        Livewire::actingAs($reader)
            ->test(EstimateShow::class, ['estimate' => $estimate])
            ->call('markAsSent')
            ->assertForbidden();

        $sender = $this->roleWith(['projects.view', 'project.view', 'estimates.view', 'estimates.send']);

        Livewire::actingAs($sender)
            ->test(EstimateShow::class, ['estimate' => $estimate])
            ->call('markAsSent')
            ->assertOk();
    }

    public function test_recording_a_payment_is_held_apart_from_editing_the_invoice(): void
    {
        $invoice = $this->makeInvoice(['status' => 'sent']);

        $editor = $this->roleWith(['projects.view', 'project.view', 'invoices.view', 'invoices.edit']);

        Livewire::actingAs($editor)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertForbidden();

        $cashier = $this->roleWith([
            'projects.view', 'project.view', 'invoices.view', 'invoices.record_payment',
        ]);

        Livewire::actingAs($cashier)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertOk();
    }

    public function test_refunding_needs_payments_refund_which_no_seeded_role_holds(): void
    {
        $invoice = $this->makeInvoice(['status' => 'sent']);

        // Somebody who may take money in still may not give it back.
        $cashier = $this->roleWith([
            'projects.view', 'project.view', 'invoices.view', 'invoices.record_payment',
        ]);

        Livewire::actingAs($cashier)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openRefundModal', 1)
            ->assertForbidden();

        // It was reserved for this module back in E1 and is held by nobody.
        foreach (['manager', 'employee'] as $name) {
            $this->assertNotContains(
                'payments.refund',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
                $name,
            );
        }
    }

    public function test_turning_an_estimate_into_an_invoice_needs_the_invoice_grant(): void
    {
        $estimate = $this->makeEstimate(['status' => 'accepted']);

        // Holding every estimate grant is not enough — it raises an invoice.
        $estimator = $this->roleWith([
            'projects.view', 'project.view',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.send',
        ]);

        Livewire::actingAs($estimator)
            ->test(EstimateShow::class, ['estimate' => $estimate])
            ->call('convertToInvoice')
            ->assertForbidden();

        $this->assertSame(0, Invoice::count());
    }

    public function test_deleting_is_its_own_grant_on_both_sides(): void
    {
        $estimate = $this->makeEstimate();
        $invoice = $this->makeInvoice();

        $editor = $this->roleWith([
            'projects.view', 'project.view',
            'estimates.view', 'estimates.edit', 'invoices.view', 'invoices.edit',
        ]);

        Livewire::actingAs($editor)
            ->test(EstimateShow::class, ['estimate' => $estimate])
            ->call('deleteEstimate')
            ->assertForbidden();

        Livewire::actingAs($editor)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('deleteInvoice')
            ->assertForbidden();

        $this->assertNotNull($estimate->fresh());
        $this->assertNotNull($invoice->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | P22 — the PDFs
    |---------------------------------------------------------------------------
    */

    public function test_the_pdfs_are_no_longer_reachable_by_id(): void
    {
        $estimate = $this->makeEstimate();
        $invoice = $this->makeInvoice();
        $blind = $this->roleWith(['projects.view', 'project.view']);

        $this->actingAs($blind)->get(route('estimates.pdf.view', $estimate))->assertForbidden();
        $this->actingAs($blind)->get(route('invoices.pdf.view', $invoice))->assertForbidden();

        $reader = $this->roleWith([
            'projects.view', 'project.view', 'estimates.view', 'invoices.view',
        ]);

        $this->actingAs($reader)->get(route('estimates.pdf.view', $estimate))->assertSuccessful();
        $this->actingAs($reader)->get(route('invoices.pdf.view', $invoice))->assertSuccessful();
    }

    /*
    |---------------------------------------------------------------------------
    | The public pay link — a token boundary, not a permissions question
    |---------------------------------------------------------------------------
    */

    public function test_the_pay_link_works_without_a_login(): void
    {
        $invoice = $this->makeInvoice(['status' => 'sent']);

        // Deliberately not guarded: the client has no account. The token is
        // the whole credential, which is why the route is throttled.
        $this->get(route('invoice.pay', $invoice->payment_token))->assertOk();
    }

    public function test_the_pay_link_refuses_a_wrong_token_and_a_draft(): void
    {
        $this->get(route('invoice.pay', 'not-a-real-token'))->assertNotFound();

        // A draft has not been sent to anybody, so its token opens nothing.
        $draft = $this->makeInvoice(['status' => 'draft']);

        $this->get(route('invoice.pay', $draft->payment_token))->assertNotFound();
    }

    public function test_the_pay_link_does_not_expose_the_rest_of_the_application(): void
    {
        $invoice = $this->makeInvoice(['status' => 'sent']);
        $other = $this->makeInvoice(['status' => 'sent', 'invoice_number' => 'INV-SECRET']);

        // One token opens one invoice, and names no other.
        $this->get(route('invoice.pay', $invoice->payment_token))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertDontSee('INV-SECRET');

        // And a visitor with a valid token is still not signed in.
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
    }

    public function test_a_visitor_cannot_pay_more_than_the_balance(): void
    {
        $invoice = $this->makeInvoice(['status' => 'sent']);

        Livewire::test(PublicInvoicePay::class, ['token' => $invoice->payment_token])
            ->set('paymentAmount', 999999)
            ->set('cardName', 'A Client')
            ->set('cardExpiryMonth', '12')
            ->set('cardExpiryYear', '30')
            ->call('processPayment')
            ->assertHasErrors('paymentAmount');
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue
    |---------------------------------------------------------------------------
    */

    public function test_both_areas_are_company_wide(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;

        // An estimate belongs to a client, not to a project, so neither area
        // is grantable on a project membership.
        $this->assertSame(['global'], $catalog::area('estimates')['levels']);
        $this->assertSame(['global'], $catalog::area('invoices')['levels']);
        $this->assertTrue($catalog::area('invoices')['money']);
    }
}
