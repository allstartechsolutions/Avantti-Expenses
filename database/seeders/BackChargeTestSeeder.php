<?php

namespace Database\Seeders;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectAmountSource;
use App\Enums\ProjectStatus;
use App\Models\BackCharge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\ContractScheduleItem;
use App\Models\JobSite;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BackChargeDeductions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Test data for back charges (docs/back-charges.md), one record per case the
 * module has to handle. Local and staging only; every record starts with
 * "Test". Additive and safe to run twice.
 *
 *   php artisan db:seed --class=BackChargeTestSeeder
 *
 * Users (password TestPass!2026):
 *   test-admin@avantti.test    administrator: everything
 *   test-pm@avantti.test       "Test PM (back charges)": batches and payments, sees, creates and deducts back charges, cannot delete them
 *   test-manager@avantti.test  manager as seeded: pays, but Back Charges is hidden and nothing is deducted
 */
class BackChargeTestSeeder extends Seeder
{
    public const PASSWORD = 'TestPass!2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Not on production.');

            return;
        }

        if (Project::where('project_name', 'Test — Bayside Commons')->exists()) {
            $this->command?->warn('Back-charge test data already present.');

            return;
        }

        DB::transaction(fn () => $this->seed());

        $this->command?->info('Back-charge test data created. Log in as test-admin@avantti.test / '.self::PASSWORD.' — see docs/back-charges.md, "Testing by hand".');
    }

    protected function seed(): void
    {
        // ---- People -------------------------------------------------------
        $user = fn (string $name, string $email, ?int $roleId) => User::where('email', $email)->first() ?? User::forceCreate([
            'name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(), 'role_id' => $roleId,
        ]);

        $admin = $user('Test Admin', 'test-admin@avantti.test', Role::where('name', 'admin')->value('id'));
        Auth::login($admin);

        $pmRole = Role::firstOrCreate(['name' => 'Test PM (back charges)']);
        $managerAbilities = Role::where('name', 'manager')->first()?->abilities() ?? [];
        $pmRole->syncAbilities(array_values(array_unique([
            ...$managerAbilities,
            'payments.view', 'payments.pay', 'payments.batch',
            'back-charges.view', 'back-charges.create', 'back-charges.edit', 'back-charges.deduct',
        ])));
        $pm = $user('Test PM', 'test-pm@avantti.test', $pmRole->id);
        $user('Test Manager', 'test-manager@avantti.test', Role::where('name', 'manager')->value('id'));

        // ---- Job site, lots, subcontractors --------------------------------
        $client = Client::create(['company_name' => 'Test — Bayside Builders', 'contact_name' => 'Sam Lee', 'email' => 'test-client@avantti.test', 'created_by' => $admin->id]);
        $project = Project::create([
            'project_name' => 'Test — Bayside Commons', 'client_id' => $client->id, 'project_manager_id' => $pm->id,
            'amount_source' => ProjectAmountSource::FROM_JOBSITES, 'contact_person' => 'Sam Lee',
            'email' => 'test-project@avantti.test', 'status' => ProjectStatus::IN_PROGRESS, 'created_by' => $admin->id,
        ]);

        $lots = [];
        foreach (['Lot 1' => 210000, 'Lot 2' => 198000, 'Lot 3' => 205000, 'Lot 4' => 189000] as $name => $amount) {
            $lots[$name] = JobSite::create([
                'project_id' => $project->id, 'job_site_name' => $name, 'street' => '200 Test Way', 'city' => 'Annapolis',
                'state' => 'MD', 'postal_code' => '21401', 'contact_person' => 'Sam Lee',
                'email' => 'test-'.str($name)->slug().'@avantti.test', 'job_amount' => $amount,
                'status' => JobSiteStatus::IN_PROGRESS, 'created_by' => $admin->id,
            ]);
        }

        $vendor = function (string $name) use ($admin) {
            $v = new Vendor;
            $v->forceFill(['name' => $name, 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $admin->id])->save();

            return $v;
        };
        $drywall = $vendor('Test Drywall Co');
        $electric = $vendor('Test Electric LLC');
        $plumbing = $vendor('Test Plumbing Inc');

        $ana = SubcontractorEmployee::create(['subcontractor_id' => $drywall->id, 'name' => 'Ana Souza (Test)']);
        $bruno = SubcontractorEmployee::create(['subcontractor_id' => $drywall->id, 'name' => 'Bruno Costa (Test)']);
        $carla = SubcontractorEmployee::create(['subcontractor_id' => $electric->id, 'name' => 'Carla Dias (Test)']);

        // ---- Contracts ------------------------------------------------------
        $contract = fn (string $number, string $lot, Vendor $sub, ?SubcontractorEmployee $emp, float $amount) => Contract::create([
            'project_id' => $project->id, 'job_site_id' => $lots[$lot]->id, 'subcontractor_id' => $sub->id,
            'subcontractor_employee_id' => $emp?->id, 'contract_number' => $number, 'status' => 'active',
            'start_date' => now()->subDays(30)->toDateString(), 'end_date' => now()->addDays(45)->toDateString(),
            'amount' => $amount, 'notes' => 'Test contract', 'created_by' => $admin->id,
        ]);

        $t01 = $contract('CTR-T01', 'Lot 1', $drywall, $ana, 20000);
        $t02 = $contract('CTR-T02', 'Lot 2', $drywall, $bruno, 18000);
        $t03 = $contract('CTR-T03', 'Lot 3', $drywall, null, 15000);
        $t04 = $contract('CTR-T04', 'Lot 1', $electric, $carla, 12000);
        $contract('CTR-T05', 'Lot 4', $electric, null, 9500);
        $t06 = $contract('CTR-T06', 'Lot 2', $plumbing, null, 8000);

        // CTR-T03 runs on installments, all three released: pay one with a back charge
        // and it still reads as fully paid.
        foreach ([1, 2, 3] as $i) {
            ContractScheduleItem::create([
                'contract_id' => $t03->id, 'sort_order' => $i, 'description' => "Test installment {$i}",
                'trigger_type' => 'date', 'due_date' => now()->addDays(($i - 1) * 15)->toDateString(), 'amount' => 5000,
                'released_at' => now(), 'released_by' => $admin->id,
            ]);
        }

        $pay = fn (Contract $c, float $amount, int $daysAgo, string $ref) => ContractPayment::create([
            'contract_id' => $c->id, 'amount' => $amount, 'payment_date' => now()->subDays($daysAgo)->toDateString(),
            'payment_method' => 'check', 'reference_number' => $ref, 'created_by' => $admin->id,
        ]);

        // An ordinary earlier payment, no back charge.
        $pay($t01, 5000, 20, 'CHK-T1001');
        $t01->updateStatusFromPayments();

        // ---- Back charges, one per state -----------------------------------
        $bc = fn (Vendor $sub, ?SubcontractorEmployee $emp, ?string $lot, float $amount, int $daysAgo, string $reason, ?string $description = null) => BackCharge::create([
            'number' => BackCharge::generateNumber(), 'subcontractor_id' => $sub->id, 'subcontractor_employee_id' => $emp?->id,
            'project_id' => $lot ? $project->id : null, 'job_site_id' => $lot ? $lots[$lot]->id : null,
            'amount' => $amount, 'date' => now()->subDays($daysAgo)->toDateString(), 'reason' => $reason,
            'description' => $description, 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $deduct = app(BackChargeDeductions::class);

        // Open, untouched — deduct it from any Test Drywall payment.
        $bc($drywall, null, 'Lot 2', 1500, 14, 'Test — Damaged window frame', 'Frame cracked while hanging board in the living room.');

        // Against an employee: only offered on CTR-T01, the contract that names Ana.
        $bc($drywall, $ana, null, 400, 9, 'Test — Lost company ladder');

        // Partly deducted: $600 of $1,000 came off a direct payment of $3,000 (check $2,400).
        $partly = $bc($drywall, null, 'Lot 1', 1000, 18, 'Test — Cleanup after drywall');
        $deduct->deductFromPayment($pay($t02, 3000, 10, 'CHK-T1002'), [$partly->id => 600]);
        $t02->updateStatusFromPayments();

        // Fully deducted through an approved batch: $2,000 line less $750 (check $1,250).
        $full = $bc($electric, $carla, 'Lot 1', 750, 16, 'Test — Cut conduit repair');
        $approved = PaymentBatch::create(['name' => 'Test — Week 44 (approved)', 'status' => 'draft', 'payment_date' => now()->subDays(7)->toDateString(), 'notes' => 'Test batch', 'created_by' => $admin->id]);
        $line = PaymentBatchItem::create(['payment_batch_id' => $approved->id, 'contract_id' => $t04->id, 'amount' => 2000, 'payment_method' => 'check', 'phase' => 'Rough-in', 'status' => 'pending']);
        $deduct->syncForBatchItem($line, [$full->id => 750]);
        $payment = ContractPayment::create([
            'contract_id' => $t04->id, 'payment_batch_item_id' => $line->id, 'amount' => 2000, 'phase' => 'Rough-in',
            'payment_date' => $approved->payment_date, 'payment_method' => 'check', 'created_by' => $admin->id,
        ]);
        $deduct->takeForApprovedItem($line, $payment);
        $line->update(['status' => 'approved', 'approved_at' => now()]);
        $approved->refreshStatusFromItems($admin->id);
        $t04->updateStatusFromPayments();

        // Waived: declined for good before anything was deducted.
        $bc($electric, null, null, 0, 12, 'Test — Missed inspection fee', 'Waived: the county refunded the fee.');

        // Set aside on a pending line of a draft batch: "Pending approval" on its page.
        $reserved = $bc($plumbing, null, 'Lot 2', 500, 6, 'Test — Water damage under sink');
        $draft = PaymentBatch::create(['name' => 'Test — Week 46 (draft)', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'notes' => 'Test batch', 'created_by' => $admin->id]);
        $pending = PaymentBatchItem::create(['payment_batch_id' => $draft->id, 'contract_id' => $t06->id, 'amount' => 2000, 'payment_method' => 'check', 'status' => 'pending']);
        $deduct->syncForBatchItem($pending, [$reserved->id => 500]);

        // A pending line with nothing deducted yet, ready for you to try.
        PaymentBatchItem::create(['payment_batch_id' => $draft->id, 'contract_id' => $t01->id, 'amount' => 3000, 'payment_method' => 'check', 'status' => 'pending']);

        Auth::logout();
    }
}
