<?php

// Demo payment batches for the how-to screenshots (batch items 1, 2, 3, 6, 7, 8, 9).
// Needs seed_demo.php first. Additive; every record is marked "Demo". Local only.
// Run from the project root: php docs/changes_requested/instructions/capture/seed_demo_batches.php

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Contract;
use App\Models\JobSite;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'demo@avantti.test')->first() ?? exit("Run seed_demo.php first.\n");

if (PaymentBatch::where('name', 'like', 'Demo%')->exists()) {
    exit("Demo batches already present.\n");
}

DB::transaction(function () use ($admin) {
    Auth::login($admin);

    $alexandre = User::where('email', 'demo-pm@avantti.test')->firstOrFail();
    $rildo = User::forceCreate([
        'name' => 'Rildo (Demo PM)',
        'email' => 'demo-pm2@avantti.test',
        'password' => Hash::make(str()->random(32)),
        'email_verified_at' => now(),
        'role_id' => $alexandre->role_id,
    ]);

    $project = Project::where('project_name', 'Demo — Kent Island Village')->firstOrFail();
    $lots = JobSite::where('project_id', $project->id)->orderBy('id')->get()->values();
    $framing = Vendor::where('name', 'Demo Framing Pros LLC')->firstOrFail();
    $roofing = Vendor::where('name', 'Demo Roof Masters Inc')->firstOrFail();
    $edwin = SubcontractorEmployee::where('name', 'Edwin Ramos')->firstOrFail();
    $marcos = SubcontractorEmployee::where('name', 'Marcos Silva')->firstOrFail();
    $jorge = SubcontractorEmployee::where('name', 'Jorge Lima')->firstOrFail();

    $n = 200;
    $contract = function (Vendor $sub, SubcontractorEmployee $emp, int $lot, float $amount, string $scope) use ($project, $lots, $admin, &$n) {
        return Contract::create([
            'project_id' => $project->id,
            'job_site_id' => $lots[$lot % $lots->count()]->id,
            'subcontractor_id' => $sub->id,
            'subcontractor_employee_id' => $emp->id,
            'contract_number' => 'CTR-D'.(++$n),
            'status' => 'active',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'amount' => $amount,
            'notes' => 'Demo — '.$scope,
            'created_by' => $admin->id,
        ]);
    };

    $batch = function (string $name, User $pm, string $date, array $lines) use ($admin) {
        $b = PaymentBatch::create([
            'name' => $name,
            'status' => 'draft',
            'payment_date' => $date,
            'project_manager_id' => $pm->id,
            'notes' => 'Demo batch',
            'created_by' => $admin->id,
        ]);
        foreach ($lines as [$c, $amount, $status, $phase]) {
            PaymentBatchItem::create([
                'payment_batch_id' => $b->id,
                'contract_id' => $c->id,
                'amount' => $amount,
                'payment_method' => 'check',
                'phase' => $phase,
                'status' => $status,
            ]);
        }

        return $b;
    };

    $batch('Demo — Alexandre - Framing', $alexandre, '2026-11-06', [
        [$contract($framing, $edwin, 0, 4500, 'framing'), 1800, 'pending', 'Framing — walls'],
        [$contract($framing, $marcos, 1, 3900, 'framing'), 1500, 'pending', 'Framing — walls'],
        [$contract($framing, $edwin, 2, 4200, 'framing'), 1650, 'pending', 'Framing — roof'],
        [$contract($roofing, $jorge, 0, 2600, 'roofing'), 950, 'pending', 'Shingles'],
        [$contract($framing, $marcos, 2, 3100, 'framing'), 1200, 'rejected', 'Framing — walls'],
        [$contract($roofing, $jorge, 1, 2400, 'roofing'), 875, 'pending', 'Shingles'],
        [$contract($framing, $edwin, 1, 3800, 'framing'), 1425.50, 'pending', 'Framing — trim'],
    ]);
    $batch('Demo — Alexandre - Roofing', $alexandre, '2026-11-07', [
        [$contract($roofing, $jorge, 2, 5200, 'roofing'), 2100, 'pending', 'Roof deck'],
        [$contract($roofing, $jorge, 0, 4800, 'roofing'), 1950, 'pending', 'Roof deck'],
    ]);
    $batch('Demo — Rildo - Framing', $rildo, '2026-11-07', [
        [$contract($framing, $marcos, 0, 6100, 'framing'), 2400, 'pending', 'Framing — walls'],
    ]);
});

echo "Demo batches created.\n";
