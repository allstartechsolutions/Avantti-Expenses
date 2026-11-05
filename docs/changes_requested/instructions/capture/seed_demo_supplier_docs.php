<?php

// Demo W-9 and COI on a demo supplier for the how-to screenshots (item 27).
// Needs seed_demo.php first. Additive; local only.
// Run from the project root: php docs/changes_requested/instructions/capture/seed_demo_supplier_docs.php

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\DocumentType;
use App\Models\SubcontractorDocument;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

$admin = User::where('email', 'demo@avantti.test')->first() ?? exit("Run seed_demo.php first.\n");
$supplier = Vendor::where('name', 'Demo Bay Equipment Rental')->firstOrFail();

if (SubcontractorDocument::where('subcontractor_id', $supplier->id)->exists()) {
    exit("Demo supplier documents already present.\n");
}

$pdf = "%PDF-1.4\n%%EOF";
foreach ([
    ['us.w9', 'w9-bay-equipment-2026.pdf', null],
    ['us.coi', 'coi-bay-equipment.pdf', now()->addDays(18)->toDateString()],
] as [$key, $file, $expires]) {
    $path = "subcontractor-documents/{$supplier->id}/{$file}";
    Storage::put($path, $pdf);
    SubcontractorDocument::create([
        'subcontractor_id' => $supplier->id,
        'document_type_id' => DocumentType::where('key', $key)->value('id'),
        'file_path' => $path,
        'file_name' => $file,
        'file_size' => strlen($pdf),
        'expiration_date' => $expires,
        'notes' => 'Demo document',
        'uploaded_by' => $admin->id,
    ]);
}

echo "Demo supplier documents created.\n";
