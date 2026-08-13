<?php

namespace App\Livewire\Contract;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContractPayments extends Component
{
    #[Url(except: '')]
    public string $clientFilter = '';

    #[Url(except: '')]
    public string $projectFilter = '';

    #[Url(except: '')]
    public string $subcontractorFilter = '';

    #[Url(except: '')]
    public string $projectManagerFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: false)]
    public bool $showZeroBalance = false;

    public string $paymentDate = '';
    public array $payAmounts = [];
    public array $payMethods = [];
    public array $payNotes = [];
    public array $expandedContracts = [];

    public function mount(): void
    {
        $this->paymentDate = now()->format('Y-m-d');
    }

    public function toggleChangeOrders(int $contractId): void
    {
        if (in_array($contractId, $this->expandedContracts)) {
            $this->expandedContracts = array_values(array_diff($this->expandedContracts, [$contractId]));
        } else {
            $this->expandedContracts[] = $contractId;
        }
    }

    public function updatedClientFilter(): void
    {
        if ($this->projectFilter) {
            $project = Project::find($this->projectFilter);
            if ($project && $this->clientFilter && $project->client_id != $this->clientFilter) {
                $this->projectFilter = '';
            }
        }
    }

    #[Computed]
    public function clients()
    {
        return Client::whereHas('projects.contracts')
            ->orderBy('company_name')
            ->get(['id', 'company_name']);
    }

    #[Computed]
    public function projects()
    {
        return Project::whereHas('contracts')
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->orderBy('project_name')
            ->get(['id', 'project_name', 'client_id']);
    }

    #[Computed]
    public function projectManagers()
    {
        return User::whereHas('managedProjects.contracts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function subcontractors()
    {
        return Subcontractor::whereHas('contracts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function contracts()
    {
        return Contract::with(['project.client', 'jobSite', 'subcontractor', 'latestPayment', 'changeOrders'])
            ->withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->unless($this->showZeroBalance, fn ($q) => $q->whereNotIn('status', ['paid', 'cancelled']))
            ->orderBy('project_id')
            ->orderBy('job_site_id')
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $baseQuery = Contract::query()
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)));

        // Total contract value (all non-cancelled)
        $totalValueCents = (clone $baseQuery)->whereNot('status', 'cancelled')->sum('amount');

        // Active contracts count
        $activeCount = (clone $baseQuery)->where('status', 'active')->count();

        // Total pending balance (non-paid, non-cancelled)
        $contracts = (clone $baseQuery)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->withSum('payments as total_paid_cents', 'amount')
            ->get();

        $pendingBalanceCents = $contracts->sum(fn ($c) => $c->amount * 100 - ($c->total_paid_cents ?? 0));

        // Paid this month
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $paidThisMonthCents = ContractPayment::whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->whereHas('contract', function ($q) {
                $q->when($this->clientFilter, fn ($q2) => $q2->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
                    ->when($this->projectFilter, fn ($q2) => $q2->where('project_id', $this->projectFilter))
                    ->when($this->subcontractorFilter, fn ($q2) => $q2->where('subcontractor_id', $this->subcontractorFilter))
                    ->when($this->projectManagerFilter, fn ($q2) => $q2->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)));
            })
            ->sum('amount');

        return [
            'pending_balance' => $pendingBalanceCents / 100,
            'active_count' => $activeCount,
            'paid_this_month' => $paidThisMonthCents / 100,
            'total_value' => $totalValueCents / 100,
        ];
    }

    public function processPayments(): void
    {
        $this->validate([
            'paymentDate' => 'required|date',
        ]);

        $rowsToProcess = collect($this->payAmounts)
            ->filter(fn ($amount) => $amount !== null && $amount !== '' && (float) $amount > 0);

        if ($rowsToProcess->isEmpty()) {
            session()->flash('error', __('No payment amounts entered.'));
            return;
        }

        // Validate each row
        $errors = [];
        foreach ($rowsToProcess as $contractId => $amount) {
            $contract = Contract::withSum('payments as total_paid_cents', 'amount')->find($contractId);
            if (!$contract) {
                $errors[] = __('Contract #:id not found.', ['id' => $contractId]);
                continue;
            }

            $balance = $contract->amount - (($contract->total_paid_cents ?? 0) / 100);
            if ((float) $amount > $balance + 0.01) {
                $errors[] = __('Payment for :number exceeds balance due ($:balance).', [
                    'number' => $contract->contract_number,
                    'balance' => number_format($balance, 2),
                ]);
            }

        }

        if (!empty($errors)) {
            session()->flash('error', implode(' ', $errors));
            return;
        }

        DB::transaction(function () use ($rowsToProcess) {
            $count = 0;
            foreach ($rowsToProcess as $contractId => $amount) {
                ContractPayment::create([
                    'contract_id' => $contractId,
                    'amount' => (float) $amount,
                    'payment_date' => $this->paymentDate,
                    'payment_method' => $this->payMethods[$contractId] ?? null,
                    'notes' => $this->payNotes[$contractId] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $contract = Contract::find($contractId);
                $contract->refresh();
                $contract->updateStatusFromPayments();
                $count++;
            }

            session()->flash('message', __(':count payment(s) processed successfully.', ['count' => $count]));
        });

        // Reset inline inputs
        $this->payAmounts = [];
        $this->payMethods = [];
        $this->payNotes = [];
    }

    public function exportCsv()
    {
        $contracts = Contract::with(['project.client', 'jobSite', 'subcontractor', 'latestPayment', 'changeOrders'])
            ->withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->unless($this->showZeroBalance, fn ($q) => $q->whereNotIn('status', ['paid', 'cancelled']))
            ->orderBy('project_id')
            ->orderBy('job_site_id')
            ->get();

        $filename = 'contract-payments-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($contracts) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                __('Type'),
                __('Subcontractor'),
                __('Project'),
                __('Client'),
                __('Job Site'),
                __('Contract #'),
                __('Original Amount'),
                __('Change Orders (+/-)'),
                __('Adjusted Amount'),
                __('Paid'),
                __('Balance'),
                __('Status'),
                __('Last Payment Date'),
                __('Last Payment Amount'),
                __('CO Date'),
                __('CO Title'),
                __('CO Description'),
                __('CO Amount'),
            ]);

            foreach ($contracts as $contract) {
                $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
                $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
                $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);
                $adjustedAmount = round($contract->amount + $changeOrdersTotal, 2);

                fputcsv($file, [
                    __('Contract'),
                    $contract->subcontractor?->company_name ?? '',
                    $contract->project->project_name,
                    $contract->project->client?->company_name ?? '',
                    $contract->jobSite?->job_site_name ?? __('Project General'),
                    $contract->contract_number,
                    number_format($contract->amount, 2, '.', ''),
                    number_format($changeOrdersTotal, 2, '.', ''),
                    number_format($adjustedAmount, 2, '.', ''),
                    number_format($totalPaidDollars, 2, '.', ''),
                    number_format($balance, 2, '.', ''),
                    ucfirst(str_replace('_', ' ', $contract->status)),
                    $contract->latestPayment?->payment_date->format('Y-m-d') ?? '',
                    $contract->latestPayment ? number_format($contract->latestPayment->amount, 2, '.', '') : '',
                    '', '', '', '',
                ]);

                foreach ($contract->changeOrders as $co) {
                    fputcsv($file, [
                        __('Change Order'),
                        '', '', '', '',
                        $contract->contract_number,
                        '', '', '', '', '', '', '', '',
                        $co->date->format('Y-m-d'),
                        $co->title,
                        $co->description ?? '',
                        number_format($co->amount, 2, '.', ''),
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        return view('livewire.contract.contract-payments')
            ->layout('components.layouts.app');
    }
}
