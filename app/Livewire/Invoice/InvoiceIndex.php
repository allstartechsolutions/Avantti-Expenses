<?php

namespace App\Livewire\Invoice;

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function deleteInvoice(int $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if (!$invoice->canBeEdited()) {
            session()->flash('error', 'Only draft or sent invoices can be deleted.');
            return;
        }

        $invoice->items()->delete();
        $invoice->delete();

        session()->flash('message', 'Invoice deleted successfully!');
    }

    public function render()
    {
        $query = Invoice::query()
            ->with(['client', 'project'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('invoice_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function ($q) {
                          $q->where('company_name', 'like', '%' . $this->search . '%');
                      });
                });
            });

        // Apply status filter
        if ($this->statusFilter === 'past_due') {
            $query->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString());
        } elseif ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($this->perPage);

        $statusCounts = [
            'all' => Invoice::count(),
            'draft' => Invoice::where('status', 'draft')->count(),
            'sent' => Invoice::where('status', 'sent')->count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'partial' => Invoice::where('status', 'partial')->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'past_due' => Invoice::whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString())->count(),
        ];

        return view('livewire.invoice.invoice-index', [
            'invoices' => $invoices,
            'statusCounts' => $statusCounts,
        ])->layout('components.layouts.app');
    }
}
