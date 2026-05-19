<?php

namespace App\Livewire\Report;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesTaxReport extends Component
{
    public string $fromDate = '';
    public string $toDate = '';
    public string $statusFilter = 'non_draft';

    protected $queryString = [
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'statusFilter' => ['except' => 'non_draft'],
    ];

    public function mount(): void
    {
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

    public function getGroupedByRateProperty()
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
                DB::raw('COUNT(DISTINCT invoice_id) as invoice_count')
            )
            ->whereIn('invoice_id', $invoiceIds)
            ->groupBy('tax_rate')
            ->orderBy('tax_rate')
            ->get()
            ->map(function ($row) {
                $row->taxable = round($row->taxable_cents / 100, 2);
                $row->non_taxable = round($row->non_taxable_cents / 100, 2);
                $row->tax = round($row->tax_cents / 100, 2);
                return $row;
            });
    }

    public function getInvoiceBreakdownProperty()
    {
        return $this->baseInvoiceQuery()
            ->with('client:id,company_name')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get(['id', 'invoice_number', 'invoice_date', 'client_id', 'status', 'subtotal', 'discount_amount', 'tax_total', 'total_amount']);
    }

    public function getTotalsProperty(): array
    {
        $grouped = $this->groupedByRate;

        return [
            'taxable' => $grouped->sum('taxable'),
            'non_taxable' => $grouped->sum('non_taxable'),
            'tax' => $grouped->sum('tax'),
            'invoice_count' => $this->baseInvoiceQuery()->count(),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'sales-tax-report_' . $this->fromDate . '_to_' . $this->toDate . '.csv';
        $grouped = $this->groupedByRate;
        $invoices = $this->invoiceBreakdown;
        $totals = $this->totals;

        return response()->streamDownload(function () use ($grouped, $invoices, $totals) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Sales Tax Report']);
            fputcsv($out, ['Period', $this->fromDate . ' to ' . $this->toDate]);
            fputcsv($out, ['Basis', 'Accrual (by invoice date)']);
            fputcsv($out, []);

            fputcsv($out, ['Summary by Tax Rate']);
            fputcsv($out, ['Tax Rate %', 'Taxable Sales', 'Non-Taxable Sales', 'Tax Collected', 'Invoices']);
            foreach ($grouped as $row) {
                fputcsv($out, [
                    number_format((float) $row->tax_rate * 100, 4),
                    number_format($row->taxable, 2, '.', ''),
                    number_format($row->non_taxable, 2, '.', ''),
                    number_format($row->tax, 2, '.', ''),
                    $row->invoice_count,
                ]);
            }
            fputcsv($out, [
                'TOTAL',
                number_format($totals['taxable'], 2, '.', ''),
                number_format($totals['non_taxable'], 2, '.', ''),
                number_format($totals['tax'], 2, '.', ''),
                $totals['invoice_count'],
            ]);
            fputcsv($out, []);

            fputcsv($out, ['Invoice Breakdown']);
            fputcsv($out, ['Invoice #', 'Date', 'Client', 'Status', 'Subtotal', 'Discount', 'Tax', 'Total']);
            foreach ($invoices as $invoice) {
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

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.report.sales-tax-report', [
            'groupedByRate' => $this->groupedByRate,
            'invoices' => $this->invoiceBreakdown,
            'totals' => $this->totals,
        ])->layout('components.layouts.app');
    }
}
