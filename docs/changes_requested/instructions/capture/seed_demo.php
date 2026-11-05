<?php

// Sample records for the client-instructions screenshots (Nov 2026).
// Additive only; every record carries "Demo" in a name or note so it can be found and removed.
// Run from the project root: php /path/to/seed_demo.php

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Enums\JobSiteStatus;
use App\Enums\ProjectAmountSource;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

if (User::where('email', 'demo@avantti.test')->exists()) {
    exit("Demo data already present.\n");
}

DB::transaction(function () {
    $admin = User::forceCreate([
        'name' => 'Demo Admin',
        'email' => 'demo@avantti.test',
        'password' => Hash::make('DemoPass!2026'),
        'email_verified_at' => now(),
        'role_id' => Role::where('name', 'admin')->value('id'),
    ]);
    Auth::login($admin);

    $pm = User::forceCreate([
        'name' => 'Alexandre (Demo PM)',
        'email' => 'demo-pm@avantti.test',
        'password' => Hash::make(str()->random(32)),
        'email_verified_at' => now(),
        'role_id' => Role::where('name', 'admin')->value('id'),
    ]);

    $client = Client::create(['company_name' => 'Demo — Chesapeake Homes', 'contact_name' => 'Dana Miller', 'email' => 'demo-client@avantti.test', 'created_by' => $admin->id]);

    $project = Project::create([
        'project_name' => 'Demo — Kent Island Village',
        'client_id' => $client->id,
        'project_manager_id' => $pm->id,
        'amount_source' => ProjectAmountSource::FROM_JOBSITES,
        'contact_person' => 'Dana Miller',
        'email' => 'demo-project@avantti.test',
        'status' => ProjectStatus::IN_PROGRESS,
        'created_by' => $admin->id,
    ]);

    $lots = [];
    foreach ([['Lot 12', 185000, 92000], ['Lot 14', 172500, 88500], ['Lot 15', 179900, null]] as [$name, $amount, $estimate]) {
        $lots[$name] = JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'street' => '100 Demo Way',
            'city' => 'Stevensville',
            'state' => 'MD',
            'postal_code' => '21666',
            'contact_person' => 'Dana Miller',
            'email' => 'demo-'.str($name)->slug().'@avantti.test',
            'job_amount' => $amount,
            'estimated_cost' => $estimate,
            'status' => JobSiteStatus::IN_PROGRESS,
            'created_by' => $admin->id,
        ]);
    }

    $vendor = function (string $name, bool $sub) use ($admin) {
        $v = new Vendor;
        $v->forceFill(['name' => $name, 'is_subcontractor' => $sub, 'is_supplier' => ! $sub, 'is_active' => true, 'created_by' => $admin->id])->save();

        return $v;
    };

    $framing = $vendor('Demo Framing Pros LLC', true);
    $roofing = $vendor('Demo Roof Masters Inc', true);
    $equipRental = $vendor('Demo Bay Equipment Rental', false);
    $fuel = $vendor('Demo Shore Fuel Co', false);
    $landlord = $vendor('Demo Harbor Office Park', false);

    $edwin = SubcontractorEmployee::create(['subcontractor_id' => $framing->id, 'name' => 'Edwin Ramos']);
    $marcos = SubcontractorEmployee::create(['subcontractor_id' => $framing->id, 'name' => 'Marcos Silva']);
    $jorge = SubcontractorEmployee::create(['subcontractor_id' => $roofing->id, 'name' => 'Jorge Lima']);

    $contract = function (JobSite $lot, Vendor $sub, SubcontractorEmployee $emp, float $amount, string $number) use ($project, $admin) {
        return Contract::create([
            'project_id' => $project->id,
            'job_site_id' => $lot->id,
            'subcontractor_id' => $sub->id,
            'subcontractor_employee_id' => $emp->id,
            'contract_number' => $number,
            'status' => 'active',
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'amount' => $amount,
            'notes' => 'Demo contract',
            'created_by' => $admin->id,
        ]);
    };

    $c1 = $contract($lots['Lot 12'], $framing, $edwin, 48500, 'CTR-D101');
    $c2 = $contract($lots['Lot 12'], $roofing, $jorge, 21800, 'CTR-D102');
    $c3 = $contract($lots['Lot 14'], $framing, $marcos, 46200, 'CTR-D103');

    foreach ([[$c1, 19400, 'Framing — phase 1'], [$c3, 15000, 'Framing — phase 1']] as [$c, $amt, $phase]) {
        ContractPayment::create([
            'contract_id' => $c->id,
            'amount' => $amt,
            'payment_date' => now()->subDays(6)->toDateString(),
            'payment_method' => 'check',
            'phase' => $phase,
            'reference_number' => 'CHK-'.random_int(30000, 39999),
            'created_by' => $admin->id,
        ]);
    }

    $forklift = Equipment::create([
        'name' => 'Forklift #17',
        'asset_tag' => 'EQ-0017',
        'equipment_type' => 'machinery',
        'make' => 'JCB',
        'model' => '507-42',
        'year' => 2021,
        'ownership' => 'owned',
        'purchase_cost' => 64000,
        'purchase_date' => now()->subYears(3)->toDateString(),
        'meter_type' => 'hours',
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $service = $forklift->maintenances()->create([
        'title' => '500-hour service',
        'maintenance_type' => 'preventive',
        'status' => 'completed',
        'completed_date' => now()->subDays(9)->toDateString(),
        'created_by' => $admin->id,
    ]);

    $cat = fn (string $key) => ExpenseCategory::where('key', $key)->value('id') ?? ExpenseCategory::value('id');

    $company = function (array $a) use ($admin) {
        $e = Expense::create($a + [
            'expense_date' => now()->subDays(random_int(1, 20))->toDateString(),
            'created_by' => $admin->id,
            'total_installments' => 1,
        ]);
        $e->items()->create([
            'item_name' => $a['item_name'],
            'quantity' => 1,
            'unit_price' => $a['total_amount'],
            'total_amount' => $a['total_amount'],
        ]);

        return $e;
    };

    $e1 = $company(['expense_category_id' => $cat('overhead.equipment_maintenance'), 'supplier_id' => $equipRental->id, 'equipment_id' => $forklift->id, 'equipment_maintenance_id' => $service->id, 'item_name' => 'Hydraulic hoses and filters', 'total_amount' => 842.60, 'status' => 'paid', 'payment_method' => 'check', 'check_number' => '29852', 'paid_date' => now()->subDays(3)->toDateString(), 'notes' => 'Forklift #17 — 500-hour service, Kent Island (Edwin)']);
    $e2 = $company(['expense_category_id' => $cat('overhead.vehicle_fuel'), 'supplier_id' => $fuel->id, 'equipment_id' => $forklift->id, 'item_name' => 'Diesel', 'total_amount' => 316.45, 'status' => 'paid', 'payment_method' => 'credit_card', 'paid_date' => now()->subDays(2)->toDateString(), 'notes' => 'Fuel for Forklift #17 at Lot 12']);
    $company(['expense_category_id' => $cat('overhead.rent'), 'supplier_id' => $landlord->id, 'item_name' => 'Office rent — November', 'total_amount' => 2500, 'status' => 'unpaid', 'payment_method' => 'check', 'payment_due_date' => now()->addDays(5)->toDateString(), 'notes' => 'Demo — monthly office rent']);
    $company(['expense_category_id' => $cat('overhead.utilities'), 'item_name' => 'Electric bill', 'total_amount' => 412.18, 'status' => 'paid', 'payment_method' => 'credit_card', 'is_auto_payment' => true, 'paid_date' => now()->subDays(4)->toDateString(), 'notes' => 'Demo — autopay electric']);

    // Files on the two equipment expenses: a receipt and an attachment.
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    foreach ([[$e1, 'invoice-bay-rental-2291.pdf'], [$e1, 'service-report-500h.pdf'], [$e2, 'fuel-receipt.pdf']] as [$exp, $file]) {
        $path = 'attachments/demo/'.$file;
        Storage::put($path, $pdf);
        $exp->attachments()->create(['file_path' => $path, 'original_name' => $file, 'uploaded_by' => $admin->id]);
    }

    // A job-site expense on Lot 12.
    $site = Expense::create([
        'project_id' => $project->id,
        'job_site_id' => $lots['Lot 12']->id,
        'supplier_id' => $equipRental->id,
        'item_name' => 'Dumpster rental',
        'expense_date' => now()->subDays(8)->toDateString(),
        'total_amount' => 650,
        'status' => 'paid',
        'payment_method' => 'check',
        'paid_date' => now()->subDays(7)->toDateString(),
        'total_installments' => 1,
        'notes' => 'Demo — dumpster Lot 12',
        'created_by' => $admin->id,
    ]);
    Storage::put('attachments/demo/dumpster-invoice.pdf', $pdf);
    $site->attachments()->create(['file_path' => 'attachments/demo/dumpster-invoice.pdf', 'original_name' => 'dumpster-invoice.pdf', 'uploaded_by' => $admin->id]);
});

echo "Demo data created. Login: demo@avantti.test / DemoPass!2026\n";
