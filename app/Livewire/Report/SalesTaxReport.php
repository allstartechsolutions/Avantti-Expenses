<?php

namespace App\Livewire\Report;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesTaxReport extends Component
{
    use AuthorizesAbility;

    public string $fromDate = '';
    public string $toDate = '';
    public string $statusFilter = 'non_draft';
    public string $basis = 'accrual';

    protected $queryString = [
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'statusFilter' => ['except' => 'non_draft'],
        'basis' => ['except' => 'accrual'],
    ];

    public function mount(): void
    {
        $this->authorizeAbility('reports.sales_tax');

        if ($this->fromDate === '') {
            $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        }
        if ($this->toDate === '') {
            $this->toDate = Carbon::now()->endOfMonth()->toDateString();
        }
    }

    public function setCurrentMonth(): void
    {
        $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        $this->toDate = Carbon::now()->endOfMonth()->toDateString();
    }

    public function setLastMonth(): void
    {
        $this->fromDate = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $this->toDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();
    }

    public function setCurrentQuarter(): void
    {
        $this->fromDate = Carbon::now()->firstOfQuarter()->toDateString();
        $this->toDate = Carbon::now()->lastOfQuarter()->toDateString();
    }

    public function setYearToDate(): void
    {
        $this->fromDate = Carbon::now()->startOfYear()->toDateString();
        $this->toDate = Carbon::now()->endOfYear()->toDateString();
    }

    // --- Accrual basis: based on invoices.invoice_date ---

    protected function baseInvoiceQuery()
    {
        $query = Invoice::query()
            ->whereBetween('invoice_date', [$this->fromDate, $this->toDate]);

        if ($this->statusFilter === 'non_draft') {
            $query->where('status', '!=', 'draft');
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query;
    }

    protected function accrualGroupedByRate()
    {
        $invoiceIds = $this->baseInvoiceQuery()->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        return DB::table('invoice_items')
            ->select(
                'tax_rate',
                DB::raw('SUM(CASE WHEN is_taxable = 1 THEN total_amount ELSE 0 END) as taxable_cents'),
                DB::raw('SUM(CASE WHEN is_taxable = 0 THEN total_amount ELSE 0 END) as non_taxable_cents'),
                DB::raw('SUM(tax_amount) as tax_cents'),
                DB::raw('COUNT(DISTINCT invoice_id) as ref_count')
            )
            ->whereIn('invoice_id', $invoiceIds)
            ->groupBy('tax_rate')
            ->orderBy('tax_rate')
            ->get()
            ->map(fn ($row) => $this->mapMoneyRow($row));
    }

    protected function accrualBreakdown()
    {
        return $this->baseInvoiceQuery()
            ->with('client:id,company_name')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get(['id', 'invoice_number', 'invoice_date', 'client_id', 'status', 'subtotal', 'discount_amount', 'tax_total', 'total_amount']);
    }

    // --- Cash basis: based on invoice_payments.payment_date (completed only), prorated ---

    protected function cashGroupedByRate()
    {
        $rows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->join('invoice_payments as ip', 'ip.invoice_id', '=', 'i.id')
            ->where('ip.status', 'completed')
            ->whereBetween('ip.payment_date', [$this->fromDate, $this->toDate])
            ->where('i.total_amount', '>', 0)
            ->selectRaw('ii.tax_rate,
                SUM(CASE WHEN ii.is_taxable = 1 THEN ii.total_amount * (ip.amount / i.total_amount) ELSE 0 END) as taxable_cents,
                SUM(CASE WHEN ii.is_taxable = 0 THEN ii.total_amount * (ip.amount / i.total_amount) ELSE 0 END) as non_taxable_cents,
                SUM(ii.tax_amount * (ip.amount / i.total_amount)) as tax_cents,
                COUNT(DISTINCT ip.id) as ref_count')
            ->groupBy('ii.tax_rate')
            ->orderBy('ii.tax_rate')
            ->get();

        return $rows->map(fn ($row) => $this->mapMoneyRow($row));
    }

    protected function cashBreakdown()
    {
        return InvoicePayment::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$this->fromDate, $this->toDate])
            ->with(['invoice:id,invoice_number,total_amount,tax_total,client_id', 'invoice.client:id,company_name'])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->map(function (InvoicePayment $payment) {
                $invoiceTotal = $payment->invoice?->total_amount ?? 0;
                $share = $invoiceTotal > 0 ? ($payment->amount / $invoiceTotal) : 0;
                $payment->attributed_tax = round(($payment->invoice?->tax_total ?? 0) * $share, 2);
                $payment->payment_share = $share;
                return $payment;
            });
    }

    protected function mapMoneyRow($row)
    {
        $row->taxable = round($row->taxable_cents / 100, 2);
        $row->non_taxable = round($row->non_taxable_cents / 100, 2);
        $row->tax = round($row->tax_cents / 100, 2);
        return $row;
    }

    // --- Public computed properties ---

    public function getGroupedByRateProperty()
    {
        return $this->basis === 'cash' ? $this->cashGroupedByRate() : $this->accrualGroupedByRate();
    }

    public function getBreakdownProperty()
    {
        return $this->basis === 'cash' ? $this->cashBreakdown() : $this->accrualBreakdown();
    }

    public function getTotalsProperty(): array
    {
        $grouped = $this->groupedByRate;

        return [
            'taxable' => $grouped->sum('taxable'),
            'non_taxable' => $grouped->sum('non_taxable'),
            'tax' => $grouped->sum('tax'),
            'ref_count' => $this->basis === 'cash'
                ? InvoicePayment::query()
                    ->where('status', 'completed')
                    ->whereBetween('payment_date', [$this->fromDate, $this->toDate])
                    ->count()
                : $this->baseInvoiceQuery()->count(),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'sales-tax-report_' . $this->basis . '_' . $this->fromDate . '_to_' . $this->toDate . '.csv';
        $grouped = $this->groupedByRate;
        $breakdown = $this->breakdown;
        $totals = $this->totals;
        $basis = $this->basis;
        $countLabel = $basis === 'cash' ? __('Payments') : __('Invoices');

        return response()->streamDownload(function () use ($grouped, $breakdown, $totals, $basis, $countLabel) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [__('Sales Tax Report')]);
            fputcsv($out, [__('Period'), $this->fromDate . ' to ' . $this->toDate]);
            fputcsv($out, [__('Basis'), $basis === 'cash' ? __('Cash (by payment date)') : __('Accrual (by invoice date)')]);
            fputcsv($out, []);

            fputcsv($out, [__('Summary by Tax Rate')]);
            fputcsv($out, [__('Tax Rate %'), __('Taxable Sales'), __('Non-Taxable Sales'), __('Tax Collected'), $countLabel]);
            foreach ($grouped as $row) {
                fputcsv($out, [
                    number_format((float) $row->tax_rate * 100, 4),
                    number_format($row->taxable, 2, '.', ''),
                    number_format($row->non_taxable, 2, '.', ''),
                    number_format($row->tax, 2, '.', ''),
                    $row->ref_count,
                ]);
            }
            fputcsv($out, [
                __('TOTAL'),
                number_format($totals['taxable'], 2, '.', ''),
                number_format($totals['non_taxable'], 2, '.', ''),
                number_format($totals['tax'], 2, '.', ''),
                $totals['ref_count'],
            ]);
            fputcsv($out, []);

            if ($basis === 'cash') {
                fputcsv($out, [__('Payment Breakdown')]);
                fputcsv($out, [__('Payment Date'), __('Invoice #'), __('Client'), __('Payment Method'), __('Payment Amount'), __('Share %'), __('Attributed Tax')]);
                foreach ($breakdown as $p) {
                    fputcsv($out, [
                        $p->payment_date->toDateString(),
                        $p->invoice?->invoice_number ?? '',
                        $p->invoice?->client?->company_name ?? '',
                        $p->getPaymentMethodLabel(),
                        number_format($p->amount, 2, '.', ''),
                        number_format($p->payment_share * 100, 2, '.', ''),
                        number_format($p->attributed_tax, 2, '.', ''),
                    ]);
                }
            } else {
                fputcsv($out, [__('Invoice Breakdown')]);
                fputcsv($out, [__('Invoice #'), __('Date'), __('Client'), __('Status'), __('Subtotal'), __('Discount'), __('Tax'), __('Total')]);
                foreach ($breakdown as $invoice) {
                    fputcsv($out, [
                        $invoice->invoice_number,
                        $invoice->invoice_date->toDateString(),
                        $invoice->client?->company_name ?? '',
                        $invoice->status,
                        number_format($invoice->subtotal, 2, '.', ''),
                        number_format($invoice->discount_amount, 2, '.', ''),
                        number_format($invoice->tax_total, 2, '.', ''),
                        number_format($invoice->total_amount, 2, '.', ''),
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.report.sales-tax-report', [
            'groupedByRate' => $this->groupedByRate,
            'breakdown' => $this->breakdown,
            'totals' => $this->totals,
        ])->layout('components.layouts.app');
    }
}
