<?php

namespace App\Livewire\Subcontractor;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesVendorDocuments;
use App\Livewire\Concerns\TogglesVendorActive;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubcontractorShow extends Component
{
    use AuthorizesAbility;
    use ManagesVendorDocuments;
    use TogglesVendorActive;
    use WithFileUploads;

    public Subcontractor $subcontractor;

    public string $activeTab = 'overview';

    // Employee form — one form for adding and editing
    public ?int $editing_employee_id = null;

    public $employee_title = '';

    public $employee_name = '';

    public $employee_phone = '';

    public $employee_email = '';

    public $employee_tax_id = '';

    public $employee_started_at = '';

    public $employee_ended_at = '';

    public $employee_notes = '';

    public bool $showEmployeeForm = false;

    /** Employees tab filter: '' (everyone), 'active' or 'inactive'. */
    public string $employeeStatusFilter = '';

    /**
     * A row at another company the form has been told is the same worker.
     * Chosen from the lookalikes the form shows as the details are typed;
     * the link is made when the employee is saved.
     */
    public ?int $employee_link_to = null;

    public string $employee_link_reason = '';

    // Link dialog: tie an existing row to one at another company
    public ?int $linking_employee_id = null;

    public string $link_search = '';

    public ?int $link_target_id = null;

    public string $link_reason = '';

    // Delete modal
    public $showDeleteModal = false;

    /**
     * Only the names that differ from the shared map in
     * lang/<locale>/validation.php — everything else falls through to it.
     */
    public function validationAttributes(): array
    {
        return [
            'employee_email' => __('email'),
            'employee_tax_id' => __('tax id'),
            'employee_started_at' => __('start date'),
            'employee_ended_at' => __('end date'),
            'employee_link_to' => __('linked worker'),
            'employee_link_reason' => __('reason'),
            'link_target_id' => __('record to link'),
            'link_reason' => __('reason'),
        ];
    }

    protected function rules()
    {
        return $this->documentRules();
    }

    public function mount(Subcontractor $subcontractor)
    {
        $this->authorizeAbility('vendors.view');

        $this->subcontractor = $subcontractor->load(['createdBy', 'deactivatedBy']);

        // `?tab=employees` opens a tab directly — the worker page links here.
        $tab = request()->query('tab');
        if (in_array($tab, ['overview', 'documents', 'employees'], true)) {
            $this->activeTab = $tab;
        }
    }

    protected function afterVendorToggled(Vendor $vendor): void
    {
        $this->subcontractor = $this->subcontractor->fresh(['createdBy', 'deactivatedBy']);
    }

    /** The vendor the documents belong to (ManagesVendorDocuments). */
    protected function documentVendorId(): int
    {
        return $this->subcontractor->id;
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    /*
    |---------------------------------------------------------------------------
    | Employees
    |---------------------------------------------------------------------------
    */

    /** Open the form empty, for a new employee. */
    public function startEmployee()
    {
        $this->authorizeAbility('vendors.edit');

        $this->resetEmployeeForm();
        $this->showEmployeeForm = true;
    }

    /** Open the form filled with one of this vendor's rows. */
    public function editEmployee(int $employeeId)
    {
        $this->authorizeAbility('vendors.edit');

        $employee = $this->ownEmployee($employeeId);

        $this->resetEmployeeForm();
        $this->editing_employee_id = $employee->id;
        $this->employee_title = $employee->title ?? '';
        $this->employee_name = $employee->name;
        $this->employee_phone = $employee->phone ?? '';
        $this->employee_email = $employee->email ?? '';
        $this->employee_tax_id = $employee->tax_id ?? '';
        $this->employee_started_at = $employee->started_at?->format('Y-m-d') ?? '';
        $this->employee_ended_at = $employee->ended_at?->format('Y-m-d') ?? '';
        $this->employee_notes = $employee->notes ?? '';
        $this->showEmployeeForm = true;
    }

    /** Close the form. Not guarded: leaving is always allowed. */
    public function cancelEmployeeForm()
    {
        $this->resetEmployeeForm();
        $this->showEmployeeForm = false;
    }

    public function resetEmployeeForm()
    {
        $this->editing_employee_id = null;
        $this->employee_title = '';
        $this->employee_name = '';
        $this->employee_phone = '';
        $this->employee_email = '';
        $this->employee_tax_id = '';
        $this->employee_started_at = '';
        $this->employee_ended_at = '';
        $this->employee_notes = '';
        $this->employee_link_to = null;
        $this->employee_link_reason = '';
        $this->resetValidation([
            'employee_title',
            'employee_name',
            'employee_phone',
            'employee_email',
            'employee_tax_id',
            'employee_started_at',
            'employee_ended_at',
            'employee_notes',
            'employee_link_to',
            'employee_link_reason',
        ]);
    }

    /** Choose, or clear, the row at another company the new employee is the same worker as. */
    public function chooseEmployeeLink(?int $employeeId)
    {
        $this->authorizeAbility('workers.link');

        $this->employee_link_to = $this->employee_link_to === $employeeId ? null : $employeeId;
        $this->resetValidation(['employee_link_to', 'employee_link_reason']);
    }

    /**
     * Rows at other companies that look like the worker being typed into the
     * form. Shown only to somebody who may link, because there is nothing
     * else to do with them.
     *
     * @return Collection<int, array{employee: SubcontractorEmployee, reasons: array<int, string>}>
     */
    public function getEmployeeSuggestionsProperty()
    {
        if (! $this->showEmployeeForm || ! $this->allowsAbility('workers.link')) {
            return collect();
        }

        $editing = $this->editing_employee_id
            ? $this->subcontractor->employees()->find($this->editing_employee_id)
            : null;

        return SubcontractorEmployee::lookalikes([
            'name' => $this->employee_name,
            'phone' => $this->employee_phone,
            'email' => $this->employee_email,
            'tax_id' => $this->employee_tax_id,
        ], $this->subcontractor->id, $editing?->worker_id);
    }

    public function saveEmployee()
    {
        $this->authorizeAbility('vendors.edit');

        $editing = $this->editing_employee_id ? $this->ownEmployee($this->editing_employee_id) : null;

        // The link is a separate grant: without it the form never offered a
        // row to choose, and whatever the browser sends is ignored.
        if (! $this->allowsAbility('workers.link')) {
            $this->employee_link_to = null;
            $this->employee_link_reason = '';
        }

        $this->validate([
            'employee_name' => 'required|string|max:255',
            'employee_title' => 'nullable|string|max:255',
            'employee_phone' => 'nullable|string|max:50',
            'employee_email' => 'nullable|email|max:255',
            'employee_tax_id' => 'nullable|string|max:50',
            'employee_started_at' => 'nullable|date',
            'employee_ended_at' => 'nullable|date|after_or_equal:employee_started_at',
            'employee_notes' => 'nullable|string|max:1000',
            'employee_link_to' => [
                'nullable',
                Rule::exists('subcontractor_employees', 'id')->where(
                    fn ($query) => $query->where('subcontractor_id', '!=', $this->subcontractor->id),
                ),
            ],
            'employee_link_reason' => 'nullable|string|max:255',
        ]);

        $attributes = [
            'title' => $this->employee_title ?: null,
            'name' => trim($this->employee_name),
            'phone' => $this->employee_phone ?: null,
            'email' => $this->employee_email ?: null,
            'tax_id' => trim($this->employee_tax_id) ?: null,
            'started_at' => $this->employee_started_at ?: null,
            'ended_at' => $this->employee_ended_at ?: null,
            'notes' => $this->employee_notes ?: null,
        ];

        $linkTarget = $this->employee_link_to
            ? SubcontractorEmployee::where('subcontractor_id', '!=', $this->subcontractor->id)->find($this->employee_link_to)
            : null;

        $employee = DB::transaction(function () use ($editing, $attributes, $linkTarget) {
            if ($editing) {
                $editing->update($attributes);
                $employee = $editing;
            } else {
                $employee = SubcontractorEmployee::create(['subcontractor_id' => $this->subcontractor->id] + $attributes);
            }

            if ($linkTarget) {
                $employee->linkWith($linkTarget, Auth::user(), trim($this->employee_link_reason) ?: null);
            }

            return $employee;
        });

        $wasEditing = $editing !== null;

        $this->resetEmployeeForm();
        $this->showEmployeeForm = false;

        session()->flash('message', match (true) {
            $linkTarget && $wasEditing => __('Employee updated and linked to :name at :company.', ['name' => $linkTarget->name, 'company' => $linkTarget->subcontractor?->company_name]),
            $linkTarget !== null => __('Employee added and linked to :name at :company.', ['name' => $linkTarget->name, 'company' => $linkTarget->subcontractor?->company_name]),
            $wasEditing => __('Employee updated successfully!'),
            default => __('Employee added successfully!'),
        });
    }

    public function deleteEmployee(int $employeeId)
    {
        $this->authorizeAbility('vendors.edit');

        $employee = $this->ownEmployee($employeeId);

        // A back charge against the employee is money owed: it blocks the delete.
        if ($employee->backCharges()->exists()) {
            session()->flash('error', __(':name has back charges and cannot be deleted. Switch them off instead.', ['name' => $employee->name]));

            return;
        }

        $employee->delete();

        session()->flash('message', __('Employee deleted successfully!'));
    }

    /** Switch an employee off or back on. Contracts that already name them are untouched. */
    public function toggleEmployeeActive(int $employeeId)
    {
        $this->authorizeAbility('vendors.edit');

        $employee = $this->ownEmployee($employeeId);

        if ($employee->is_active) {
            $employee->deactivate(auth()->id());
            session()->flash('message', __(':name is now inactive. Contracts that already name them are kept; new contracts will not offer them as the contact.', ['name' => $employee->name]));
        } else {
            $employee->activate();
            session()->flash('message', __(':name is active again and can be picked as a contract contact.', ['name' => $employee->name]));
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Linking an employee to the same worker at another company
    |---------------------------------------------------------------------------
    */

    public function startLink(int $employeeId)
    {
        $this->authorizeAbility('workers.link');

        $this->linking_employee_id = $this->ownEmployee($employeeId)->id;
        $this->link_search = '';
        $this->link_target_id = null;
        $this->link_reason = '';
        $this->resetValidation(['link_target_id', 'link_reason']);
        $this->dispatch('open-modal', 'link-employee-modal');
    }

    public function cancelLink()
    {
        $this->linking_employee_id = null;
        $this->link_search = '';
        $this->link_target_id = null;
        $this->link_reason = '';
        $this->resetValidation(['link_target_id', 'link_reason']);
        $this->dispatch('close-modal', 'link-employee-modal');
    }

    public function chooseLinkTarget(int $employeeId)
    {
        $this->authorizeAbility('workers.link');

        $this->link_target_id = $this->link_target_id === $employeeId ? null : $employeeId;
        $this->resetValidation('link_target_id');
    }

    /** The row the dialog is linking, with its worker and their companies. */
    public function getLinkingEmployeeProperty(): ?SubcontractorEmployee
    {
        return $this->linking_employee_id
            ? $this->subcontractor->employees()->with('worker.employees.subcontractor')->find($this->linking_employee_id)
            : null;
    }

    /**
     * What the dialog offers: the lookalikes of the row first, then whatever
     * the search box finds at other companies. Rows already on the same
     * worker are left out of both.
     *
     * @return array{suggested: Collection, found: Collection}
     */
    public function getLinkCandidatesProperty(): array
    {
        $employee = $this->linkingEmployee;

        if (! $employee) {
            return ['suggested' => collect(), 'found' => collect()];
        }

        $suggested = SubcontractorEmployee::lookalikes(
            $employee->only(['name', 'phone', 'email', 'tax_id']),
            $this->subcontractor->id,
            $employee->worker_id,
        );

        $term = trim($this->link_search);
        $found = collect();

        if (mb_strlen($term) >= 2) {
            $found = SubcontractorEmployee::query()
                ->with(['subcontractor', 'worker.employees.subcontractor'])
                ->where('subcontractor_id', '!=', $this->subcontractor->id)
                ->when($employee->worker_id, fn ($q) => $q->where(
                    fn ($q) => $q->whereNull('worker_id')->orWhere('worker_id', '!=', $employee->worker_id),
                ))
                ->whereNotIn('id', $suggested->pluck('employee.id'))
                ->where(function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('tax_id', 'like', $like)
                        ->orWhereHas('subcontractor', fn ($v) => $v->where('name', 'like', $like));
                })
                ->orderBy('name')
                ->limit(25)
                ->get();
        }

        return ['suggested' => $suggested, 'found' => $found];
    }

    public function linkEmployee()
    {
        $this->authorizeAbility('workers.link');

        $employee = $this->ownEmployee((int) $this->linking_employee_id);

        $this->validate([
            'link_target_id' => [
                'required',
                Rule::exists('subcontractor_employees', 'id')->where(
                    fn ($query) => $query->where('subcontractor_id', '!=', $this->subcontractor->id),
                ),
            ],
            'link_reason' => 'nullable|string|max:255',
        ], [
            'link_target_id.required' => __('Choose the record at the other company first.'),
        ]);

        $target = SubcontractorEmployee::with('subcontractor')
            ->where('subcontractor_id', '!=', $this->subcontractor->id)
            ->findOrFail($this->link_target_id);

        if ($employee->worker_id && $employee->worker_id === $target->worker_id) {
            $this->addError('link_target_id', __('These two records are already the same worker.'));

            return;
        }

        $employee->linkWith($target, Auth::user(), trim($this->link_reason) ?: null);

        $this->cancelLink();

        session()->flash('message', __(':name is now linked to :other at :company.', [
            'name' => $employee->name,
            'other' => $target->name,
            'company' => $target->subcontractor?->company_name,
        ]));
    }

    public function unlinkEmployee(int $employeeId)
    {
        $this->authorizeAbility('workers.link');

        $employee = $this->ownEmployee($employeeId);

        if (! $employee->unlink()) {
            session()->flash('error', __('This record is not linked to any other company.'));

            return;
        }

        session()->flash('message', __(':name is no longer linked to any other record.', ['name' => $employee->name]));
    }

    /**
     * An employee by id, but only one of this vendor's — the id came from the
     * browser and proves nothing on its own.
     */
    protected function ownEmployee(int $employeeId): SubcontractorEmployee
    {
        return SubcontractorEmployee::where('id', $employeeId)
            ->where('subcontractor_id', $this->subcontractor->id)
            ->firstOrFail();
    }

    public function confirmDeleteSubcontractor()
    {
        $this->authorizeAbility('vendors.delete');

        if ($this->subcontractor->contracts()->exists() || $this->subcontractor->paymentBatches()->exists() || $this->subcontractor->backCharges()->exists()) {
            return;
        }

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-subcontractor-modal');
    }

    public function deleteSubcontractor()
    {
        $this->authorizeAbility('vendors.delete');

        // Re-check as a safety guard
        if ($this->subcontractor->contracts()->exists() || $this->subcontractor->paymentBatches()->exists() || $this->subcontractor->backCharges()->exists()) {
            $this->cancelDeleteSubcontractor();

            return;
        }

        // A company that is also a supplier only loses its subcontractor
        // classification — the record survives on the Suppliers page, and its
        // documents and employees are kept (restored by re-flagging).
        if ($this->subcontractor->is_supplier) {
            $this->subcontractor->is_subcontractor = false;
            $this->subcontractor->save();

            session()->flash('message', __('Subcontractor classification removed. The company still exists as a supplier.'));

            return redirect()->route('subcontractors.index');
        }

        DB::transaction(function () {
            $this->subcontractor->delete();
        });

        session()->flash('message', __('Subcontractor deleted successfully!'));

        return redirect()->route('subcontractors.index');
    }

    public function cancelDeleteSubcontractor()
    {
        $this->showDeleteModal = false;
        $this->dispatch('close-modal', 'delete-subcontractor-modal');
    }

    public function render()
    {
        $allEmployees = $this->subcontractor->employees()
            ->with(['worker.employees.subcontractor', 'linkedBy', 'deactivatedBy'])
            ->withCount('contracts')
            ->orderBy('name')
            ->get();

        $linkedEmployees = $allEmployees->filter->isLinked()->count();

        $employeeCounts = [
            'all' => $allEmployees->count(),
            'active' => $allEmployees->where('is_active', true)->count(),
            'inactive' => $allEmployees->where('is_active', false)->count(),
        ];

        $employees = match ($this->employeeStatusFilter) {
            'active' => $allEmployees->where('is_active', true)->values(),
            'inactive' => $allEmployees->where('is_active', false)->values(),
            default => $allEmployees,
        };

        $linkedContracts = $this->subcontractor->contracts()->count();
        $linkedPaymentBatches = $this->subcontractor->paymentBatches()->count();

        // Its back charges (docs/back-charges.md): the open ones, with what is left of each.
        $backCharges = $this->allowsAbility('back-charges.view')
            ? $this->subcontractor->backCharges()->withDeducted()->with('subcontractorEmployee:id,name', 'jobSite:id,job_site_name', 'project:id,project_name')->orderByDesc('date')->get()
            : collect();

        return view('livewire.subcontractor.subcontractor-show', $this->documentViewData() + [
            'employees' => $employees,
            'employeeCounts' => $employeeCounts,
            'linkedEmployees' => $linkedEmployees,
            'linkedContracts' => $linkedContracts,
            'linkedPaymentBatches' => $linkedPaymentBatches,
            'linkedBackCharges' => $this->subcontractor->backCharges()->count(),
            'backCharges' => $backCharges,
        ])->layout('components.layouts.app');
    }
}
