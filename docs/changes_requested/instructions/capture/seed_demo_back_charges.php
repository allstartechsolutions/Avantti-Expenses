<?php

// Demo back charges for the how-to screenshots (batch items 4, 5, 10, 11).
// Needs seed_demo.php and seed_demo_batches.php first. Additive; every record
// is marked "Demo". Local only.
// Run from the project root: php docs/changes_requested/instructions/capture/seed_demo_back_charges.php

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\BackCharge;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\JobSite;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BackChargeDeductions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$admin = User::where('email', 'demo@avantti.test')->first() ?? exit("Run seed_demo.php first.\n");

if (BackCharge::where('reason', 'like', 'Demo%')->exists()) {
    exit("Demo back charges already present.\n");
}

DB::transaction(function () use ($admin) {
    Auth::login($admin);

    $project = Project::where('project_name', 'Demo — Kent Island Village')->firstOrFail();
    $lot = JobSite::where('project_id', $project->id)->orderBy('id')->firstOrFail();
    $framing = Vendor::where('name', 'Demo Framing Pros LLC')->firstOrFail();
    $edwin = SubcontractorEmployee::where('name', 'Edwin Ramos')->firstOrFail();

    $contracts = Contract::where('subcontractor_id', $framing->id)->committed()->whereNotIn('status', ['paid', 'cancelled'])->orderBy('id')->get()
        ->filter(fn (Contract $c) => $c->getBalanceDue() >= 3000)->values();
    $paid = $contracts[0] ?? exit("No Demo Framing contract with a balance.\n");
    $open = $contracts[1] ?? $paid;

    $drywall = BackCharge::create([
        'number' => BackCharge::generateNumber(), 'subcontractor_id' => $framing->id,
        'project_id' => $project->id, 'job_site_id' => $lot->id, 'amount' => 1000, 'date' => now()->subDays(12)->toDateString(),
        'reason' => 'Demo — Damaged drywall in the garage', 'description' => 'Found at the walk-through; repaired by the drywall crew.',
        'created_by' => $admin->id, 'updated_by' => $admin->id,
    ]);
    BackCharge::create([
        'number' => BackCharge::generateNumber(), 'subcontractor_id' => $framing->id, 'subcontractor_employee_id' => $edwin->id,
        'amount' => 250, 'date' => now()->subDays(5)->toDateString(),
        'reason' => 'Demo — Lost company tools', 'created_by' => $admin->id, 'updated_by' => $admin->id,
    ]);

    // Paid last week: $2,500 with $600 of the drywall back charge taken out.
    $last = PaymentBatch::create(['name' => 'Demo — Back charge, paid', 'status' => 'draft', 'payment_date' => now()->subDays(7)->toDateString(), 'notes' => 'Demo batch', 'created_by' => $admin->id]);
    $line = PaymentBatchItem::create(['payment_batch_id' => $last->id, 'contract_id' => $paid->id, 'amount' => 2500, 'payment_method' => 'check', 'status' => 'pending']);
    app(BackChargeDeductions::class)->syncForBatchItem($line, [$drywall->id => 600]);
    $payment = ContractPayment::create([
        'contract_id' => $paid->id, 'payment_batch_item_id' => $line->id, 'amount' => 2500,
        'payment_date' => $last->payment_date, 'payment_method' => 'check', 'created_by' => $admin->id,
    ]);
    app(BackChargeDeductions::class)->takeForApprovedItem($line, $payment);
    $line->update(['status' => 'approved', 'approved_at' => now()]);
    $last->refreshStatusFromItems($admin->id);
    $paid->updateStatusFromPayments();

    // This week's batch, still a draft: the rest is offered under the line.
    $now = PaymentBatch::create(['name' => 'Demo — Back charge, this week', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'subcontractor_id' => $framing->id, 'notes' => 'Demo batch', 'created_by' => $admin->id]);
    PaymentBatchItem::create(['payment_batch_id' => $now->id, 'contract_id' => $open->id, 'amount' => 1000, 'payment_method' => 'check', 'status' => 'pending']);

    echo "Back charges {$drywall->number}…, batches {$last->id} (paid) and {$now->id} (draft).\n";
});
