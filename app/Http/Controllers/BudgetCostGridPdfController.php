<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Company;
use App\Services\CostCodeLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BudgetCostGridPdfController extends Controller
{
    public function download(Request $request, Budget $budget)
    {
        $pdf = $this->render($budget, $request->boolean('all'));

        $filename = 'cost-grid-' . Str::slug($budget->project->project_name . '-' . $budget->location_name)
            . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Request $request, Budget $budget)
    {
        return $this->render($budget, $request->boolean('all'))->stream('cost-grid.pdf');
    }

    /**
     * The screen's "show empty cost codes" choice rides along in ?all=1, so the
     * printed grid lists exactly the rows the user was looking at.
     */
    private function render(Budget $budget, bool $showEmpty = false)
    {
        $budget->load(['project', 'jobSite']);

        $grid = CostCodeLedger::for($budget)->grid();

        $pdf = Pdf::loadView('pdf.budget-cost-grid', [
            'budget' => $budget,
            'company' => Company::first(),
            'grid' => $showEmpty ? $grid : CostCodeLedger::withActivityOnly($grid),
            'generatedAt' => now(),
        ]);

        // Nine money columns need the width.
        $pdf->setPaper('letter', 'landscape');

        return $pdf;
    }
}
