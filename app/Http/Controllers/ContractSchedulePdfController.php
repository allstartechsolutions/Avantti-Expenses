<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Cronograma físico-financeiro — the contract's payment schedule as a
 * document: parcelas with previsto / realizado / saldo, delays, approvals
 * and the retention position.
 */
class ContractSchedulePdfController extends Controller
{
    public function download(Contract $contract)
    {
        $pdf = Pdf::loadView('pdf.contract-schedule', $this->data($contract));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('cronograma-'.$contract->contract_number.'.pdf');
    }

    public function stream(Contract $contract)
    {
        $pdf = Pdf::loadView('pdf.contract-schedule', $this->data($contract));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('cronograma-'.$contract->contract_number.'.pdf');
    }

    private function data(Contract $contract): array
    {
        $contract->load([
            'project', 'jobSite', 'subcontractor', 'changeOrders', 'payments',
            'scheduleItems.budgetItem', 'scheduleItems.releasedBy',
            'scheduleItems.payments', 'scheduleItems.measurements.payments',
        ]);

        // Share the loaded contract so percent parcelas don't re-query it.
        $contract->scheduleItems->each(fn ($item) => $item->setRelation('contract', $contract));

        return [
            'contract' => $contract,
            'items' => $contract->scheduleItems,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }
}
