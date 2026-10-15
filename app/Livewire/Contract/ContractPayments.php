<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\SortsContracts;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\PaymentBatchLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContractPayments extends Component
{
    use AuthorizesAbility;
    use SortsContracts;

    #[Url(except: '')]
    public string $clientFilter = '';

    #[Url(except: '')]
    public string $projectFilter = '';

    #[Url(except: '')]
    public string $subcontractorFilter = '';

    #[Url(except: '')]
    public string $projectManagerFilter = '';

    #[Url(except: '')]
    public string $supervisorFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: false)]
    public bool $showZeroBalance = false;

    /** Client, project, job site or subcontractor; empty lists the contracts ungrouped. */
    #[Url(as: 'group', except: '')]
    public string $groupBy = '';

    public string $paymentDate = '';
    public array $payAmounts = [];
    public array $payMethods = [];
    public array $payNotes = [];
    public array $expandedContracts = [];

    public function mount(): void
    {
        $this->authorizeAbility('payments.view');

        $this->paymentDate = now()->format('Y-m-d');
    }

    public function sort(string $field): void
    {
        if ($this->applySort($field)) {
            unset($this->contractGroups, $this->contracts);
        }
    }

    public function updatedGroupBy(): void
    {
        $this->groupBy = PaymentBatchLayout::cleanGroup($this->groupBy, withStatus: false);
        unset($this->contractGroups, $this->contracts);
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

    /** Supervisors of a job site that has contracts. */
    #[Computed]
    public function supervisors()
    {
        return User::whereHas('supervisedJobSites.contracts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function subcontractors()
    {
        // Active subcontractors only; one already picked stays offered.
        return Subcontractor::whereHas('contracts')
            ->activeOrCurrent($this->subcontractorFilter)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * The filtered contracts, sorted, in their groups (one unlabelled group
     * when ungrouped). The screen, the CSV and the PDF links all follow it.
     */
    #[Computed]
    public function contractGroups(): Collection
    {
        $contracts = Contract::committed()
            ->with([
                'project.client', 'jobSite', 'subcontractorEmployee', 'latestPayment', 'changeOrders',
                // Document health on the payee, counted in one query: the
                // row chip and the notice above the table read it.
                'subcontractor' => fn ($q) => $q->withDocumentHealth(),
            ])
            ->withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)))
            ->when($this->supervisorFilter, fn ($q) => $q->whereHas('jobSite', fn ($s) => $s->where('supervisor_id', $this->supervisorFilter)))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->unless($this->showZeroBalance, fn ($q) => $q->whereNotIn('status', ['paid', 'cancelled']))
            ->orderBy('project_id')
            ->orderBy('job_site_id')
            ->get();

        return $this->groupContracts($contracts, PaymentBatchLayout::cleanGroup($this->groupBy, withStatus: false));
    }

    /** The same contracts in screen order, without the groups. */
    #[Computed]
    public function contracts(): Collection
    {
        return $this->contractGroups->flatMap(fn ($group) => $group['rows'])->values();
    }

    /** Group heading rows, keyed by the first contract of each group. */
    #[Computed]
    public function groupHeaderRows(): array
    {
        return $this->groupHeaders($this->contractGroups, $this->contracts->pluck('id'));
    }

    /**
     * Whose documents the notice judges: every payee on the list, plus any
     * contract with an amount entered that a filter has since hidden —
     * processPayments() pays those too, so they must not go unwarned.
     */
    #[Computed]
    public function payees(): Collection
    {
        $hidden = collect($this->payAmounts)
            ->filter(fn ($amount) => $amount !== null && $amount !== '' && (float) $amount > 0)
            ->keys()
            ->diff($this->contracts->pluck('id'));

        $offList = $hidden->isEmpty()
            ? collect()
            : Subcontractor::withDocumentHealth()
                ->whereIn('id', Contract::whereIn('id', $hidden)->select('subcontractor_id'))
                ->get();

        return $this->contracts->pluck('subcontractor')->merge($offList);
    }

    #[Computed]
    public function totals(): array
    {
        return $this->contractTotals($this->contracts);
    }

    #[Computed]
    public function summary(): array
    {
        $baseQuery = Contract::query()
            ->committed()
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)))
            ->when($this->supervisorFilter, fn ($q) => $q->whereHas('jobSite', fn ($s) => $s->where('supervisor_id', $this->supervisorFilter)));

        // Total contract value (all non-cancelled) — the adjusted value, which
        // is what the table's "Adjusted Amount" column and every balance use:
        // original amount plus or minus the change orders.
        $totalValueCents = (clone $baseQuery)
            ->whereNot('status', 'cancelled')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->get()
            ->sum(fn ($c) => $c->amount * 100 + ($c->change_orders_total_cents ?? 0));

        // Active contracts count
        $activeCount = (clone $baseQuery)->where('status', 'active')->count();

        // Total pending balance (non-paid, non-cancelled)
        $contracts = (clone $baseQuery)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->get();

        $pendingBalanceCents = $contracts->sum(
            fn ($c) => $c->amount * 100 + ($c->change_orders_total_cents ?? 0) - ($c->total_paid_cents ?? 0)
        );

        // Paid this month
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $paidThisMonthCents = ContractPayment::whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->whereHas('contract', function ($q) {
                $q->when($this->clientFilter, fn ($q2) => $q2->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
                    ->when($this->projectFilter, fn ($q2) => $q2->where('project_id', $this->projectFilter))
                    ->when($this->subcontractorFilter, fn ($q2) => $q2->where('subcontractor_id', $this->subcontractorFilter))
                    ->when($this->projectManagerFilter, fn ($q2) => $q2->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)))
                    ->when($this->supervisorFilter, fn ($q2) => $q2->whereHas('jobSite', fn ($s) => $s->where('supervisor_id', $this->supervisorFilter)));
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
        $this->authorizeAbility('payments.pay');

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
            $contract = Contract::find($contractId);
            if (!$contract) {
                $errors[] = __('Contract #:id not found.', ['id' => $contractId]);
                continue;
            }

            // A draft is a contract on paper only — it owes nothing until it
            // is activated, and the list never offers one.
            if ($contract->isDraft()) {
                $errors[] = __(':number is still a draft and cannot be paid.', ['number' => $contract->contract_number]);
                continue;
            }

            // The balance due is the ADJUSTED amount — original plus or minus
            // the change orders — less what was paid, exactly as the table
            // shows it. Checking against the original amount refused a
            // payment on a contract whose whole value came from change orders.
            $balance = $contract->getBalanceDue();
            if ((float) $amount > $balance + 0.01) {
                $errors[] = __('Payment for :number exceeds the balance due of :balance.', [
                    'number' => $contract->contract_number,
                    'balance' => Number::currency($balance, config('app.currency'), config('app.locale')),
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
                    'payment_method' => ($this->payMethods[$contractId] ?? null) ?: ContractPayment::DEFAULT_METHOD,
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

    /**
     * The list as CSV, grouped and sorted as on screen: a Group row opens
     * each group with its totals, a Change Order row follows its contract.
     */
    public function exportCsv()
    {
        $this->authorizeAbility('payments.view');

        $groups = $this->contractGroups;
        $money = fn ($value) => number_format((float) $value, 2, '.', '');

        $filename = 'contract-payments-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($groups, $money) {
            $file = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                __('Type'),
                __('Subcontractor'),
                __('Contact (Employee)'),
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

            foreach ($groups as $group) {
                if ($group['label'] !== null) {
                    $totals = $this->contractTotals($group['rows']);

                    fputcsv($file, [
                        __('Group'),
                        $group['sub'] ? $group['label'] . ' — ' . $group['sub'] : $group['label'],
                        trans_choice(':count contract|:count contracts', $totals['count'], ['count' => $totals['count']]),
                        '', '', '', '',
                        $money($totals['amount']),
                        $money($totals['change_orders']),
                        $money($totals['adjusted']),
                        $money($totals['paid']),
                        $money($totals['balance']),
                        '', '', '', '', '', '', '',
                    ]);
                }

                foreach ($group['rows'] as $contract) {
                    $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
                    $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
                    $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);
                    $adjustedAmount = round($contract->amount + $changeOrdersTotal, 2);

                    fputcsv($file, [
                        __('Contract'),
                        $contract->subcontractor?->company_name ?? '',
                        $contract->subcontractorEmployee?->name ?? '',
                        $contract->project->project_name,
                        $contract->project->client?->company_name ?? '',
                        $contract->jobSite?->job_site_name ?? __('Project General'),
                        $contract->contract_number,
                        $money($contract->amount),
                        $money($changeOrdersTotal),
                        $money($adjustedAmount),
                        $money($totalPaidDollars),
                        $money($balance),
                        $contract->getStatusLabel(),
                        $contract->latestPayment?->payment_date->format('Y-m-d') ?? '',
                        $contract->latestPayment ? $money($contract->latestPayment->amount) : '',
                        '', '', '', '',
                    ]);

                    foreach ($contract->changeOrders as $co) {
                        fputcsv($file, [
                            __('Change Order'),
                            '', '', '', '', '',
                            $contract->contract_number,
                            '', '', '', '', '', '', '', '',
                            $co->date->format('Y-m-d'),
                            $co->title,
                            $co->description ?? '',
                            $money($co->amount),
                        ]);
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        foreach ($this->contracts as $contract) {
            $this->payMethods[$contract->id] = ($this->payMethods[$contract->id] ?? null) ?: ContractPayment::DEFAULT_METHOD;
        }

        return view('livewire.contract.contract-payments')
            ->layout('components.layouts.app');
    }
}
