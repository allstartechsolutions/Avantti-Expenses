<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Company;
use App\Services\CostCodeLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class BudgetCostGridPdfController extends Controller
{
    public function download(Budget $budget)
    {
        $pdf = $this->render($budget);

        $filename = 'cost-grid-' . Str::slug($budget->project->project_name . '-' . $budget->location_name)
            . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Budget $budget)
    {
        return $this->render($budget)->stream('cost-grid.pdf');
    }

    private function render(Budget $budget)
    {
        $budget->load(['project', 'jobSite']);

        $pdf = Pdf::loadView('pdf.budget-cost-grid', [
            'budget' => $budget,
            'company' => Company::first(),
            'grid' => CostCodeLedger::for($budget)->grid(),
            'generatedAt' => now(),
        ]);

        // Nine money columns need the width.
        $pdf->setPaper('letter', 'landscape');

        return $pdf;
    }
}
