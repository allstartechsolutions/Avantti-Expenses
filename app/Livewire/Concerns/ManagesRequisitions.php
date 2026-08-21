<?php

namespace App\Livewire\Concerns;

use App\Models\BudgetItem;
use App\Models\CatalogItem;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use Illuminate\Support\Facades\DB;

/**
 * The requisition page, shared by the project and job-site levels.
 *
 * Both levels get the same list, the same full-page form and the same detail
 * view; only the location context differs — the project page lets the user
 * pick a job site, the job-site page fixes it.
 */
trait ManagesRequisitions
{
    // Form modal
    public $editingRequisitionId = null;
    public $req_job_site_id = '';
    public $req_type = 'material';
    public $req_title = '';
    public $req_justification = '';
    public $req_needed_by = '';
    public $req_priority = 'normal';
    public $req_requested_by = '';
    public $req_requested_by_name = '';
    public $req_budget_item_id = null;
    public $req_budget_item_label = '';
    public $budgetItemSearch = '';
    public $catalogSearch = '';
    public $itemRows = [];
    public $req_uploads = [];

    // Detail modal
    public $viewingRequisitionId = null;

    // Review
    public $reviewNotes = '';

    abstract protected function contextProject(): Project;

    abstract protected function contextJobSite(): ?JobSite;

    // =========================================================================
    // FORM
    // =========================================================================

    public function openAddModal(): void
    {
        $this->authorizeAbility('requisitions.create', $this->requisitionScope());

        $this->resetForm();
        $this->req_requested_by = (string) auth()->id();
        $this->itemRows = [$this->blankItemRow()];
        $this->dispatch('open-modal', 'requisition-form-modal');
    }

    public function openEditModal(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->with('items')->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.edit', $requisition);

        // N1: once submitted the content is fixed. Send it back to draft first.
        abort_unless(
            $requisition->canBeEdited(),
            403,
            __('This requisition has been submitted. Return it to draft before changing it.'),
        );

        // Escape and backdrop clicks close the modal without telling the
        // server, so start clean rather than inherit a stale session.
        $this->resetForm();

        $this->editingRequisitionId = $requisition->id;
        $this->req_job_site_id = (string) ($requisition->job_site_id ?? '');
        $this->req_type = $requisition->type;
        $this->req_title = $requisition->title;
        $this->req_justification = $requisition->justification ?? '';
        $this->req_needed_by = $requisition->needed_by?->format('Y-m-d') ?? '';
        $this->req_priority = $requisition->priority;
        $this->req_requested_by = (string) ($requisition->requested_by ?? '');
        $this->req_requested_by_name = $requisition->requested_by_name ?? '';
        $this->setBudgetItem($requisition->budget_item_id);

        $this->itemRows = $requisition->items->map(fn ($item) => [
            'id' => $item->id,
            'catalog_item_id' => $item->catalog_item_id,
            'item_name' => $item->item_name,
            'item_type' => $item->item_type,
            'description' => $item->description ?? '',
            'quantity' => (float) $item->quantity,
            'unit' => $item->unit ?? '',
        ])->all();

        if ($this->itemRows === []) {
            $this->itemRows = [$this->blankItemRow()];
        }

        $this->dispatch('open-modal', 'requisition-form-modal');
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', 'requisition-form-modal');
    }

    protected function resetForm(): void
    {
        $this->editingRequisitionId = null;
        $this->req_job_site_id = (string) ($this->contextJobSite()?->id ?? '');
        $this->req_type = 'material';
        $this->req_title = '';
        $this->req_justification = '';
        $this->req_needed_by = '';
        $this->req_priority = 'normal';
        $this->req_requested_by = '';
        $this->req_requested_by_name = '';
        $this->req_budget_item_id = null;
        $this->req_budget_item_label = '';
        $this->budgetItemSearch = '';
        $this->catalogSearch = '';
        $this->itemRows = [];
        $this->req_uploads = [];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function blankItemRow(): array
    {
        return [
            'id' => null,
            'catalog_item_id' => null,
            'item_name' => '',
            'item_type' => 'custom',
            'description' => '',
            'quantity' => 1,
            'unit' => '',
        ];
    }

    public function addItemRow(): void
    {
        $this->itemRows[] = $this->blankItemRow();
    }

    /**
     * Livewire writes public arrays before this runs, so a row index the
     * server never built can arrive. Guard every index that is walked.
     */
    public function removeItemRow(int $index): void
    {
        if (! array_key_exists($index, $this->itemRows)) {
            return;
        }

        unset($this->itemRows[$index]);
        $this->itemRows = array_values($this->itemRows);

        if ($this->itemRows === []) {
            $this->itemRows = [$this->blankItemRow()];
        }
    }

    /** Add a catalog item as a new line, carrying its name and unit. */
    public function addCatalogItem(int $catalogItemId): void
    {
        $catalogItem = CatalogItem::find($catalogItemId);

        if (! $catalogItem) {
            return;
        }

        // An untouched blank first row is filled rather than pushed past.
        $target = null;
        foreach ($this->itemRows as $index => $row) {
            if (trim((string) ($row['item_name'] ?? '')) === '') {
                $target = $index;
                break;
            }
        }

        $row = [
            'id' => null,
            'catalog_item_id' => $catalogItem->id,
            'item_name' => $catalogItem->name,
            'item_type' => 'catalog',
            'description' => $catalogItem->description ?? '',
            'quantity' => 1,
            'unit' => $catalogItem->purchase_unit ?? '',
        ];

        if ($target === null) {
            $this->itemRows[] = $row;
        } else {
            $this->itemRows[$target] = $row;
        }

        $this->catalogSearch = '';
    }

    /** A hand-typed line is no longer the catalog item it started as. */
    public function updatedItemRows($value, $key): void
    {
        if (! str_ends_with($key, '.item_name')) {
            return;
        }

        $index = (int) explode('.', $key)[0];

        if (! array_key_exists($index, $this->itemRows)) {
            return;
        }

        if (($this->itemRows[$index]['item_type'] ?? 'custom') === 'catalog') {
            $catalogItem = $this->itemRows[$index]['catalog_item_id']
                ? CatalogItem::find($this->itemRows[$index]['catalog_item_id'])
                : null;

            if (! $catalogItem || $catalogItem->name !== $value) {
                $this->itemRows[$index]['catalog_item_id'] = null;
                $this->itemRows[$index]['item_type'] = 'custom';
            }
        }
    }

    public function selectBudgetItem(int $budgetItemId): void
    {
        $this->setBudgetItem($budgetItemId);
        $this->budgetItemSearch = '';
    }

    public function clearBudgetItem(): void
    {
        $this->req_budget_item_id = null;
        $this->req_budget_item_label = '';
        $this->budgetItemSearch = '';
    }

    /** Only budget items belonging to this project's budgets are accepted. */
    protected function setBudgetItem(?int $budgetItemId): void
    {
        if (! $budgetItemId) {
            $this->clearBudgetItem();

            return;
        }

        $budgetItem = $this->projectBudgetItems()->whereKey($budgetItemId)->first();

        if (! $budgetItem) {
            $this->clearBudgetItem();

            return;
        }

        $this->req_budget_item_id = $budgetItem->id;
        $this->req_budget_item_label = $budgetItem->code.' — '.$budgetItem->name;
    }

    protected function projectBudgetItems()
    {
        return BudgetItem::whereHas('budget', function ($q) {
            $q->where('project_id', $this->contextProject()->id);
        });
    }

    /**
     * @param  string  $mode  'draft' keeps it editable, 'pending' sends it for approval
     */
    public function saveRequisition(string $mode = 'pending'): void
    {
        $mode = in_array($mode, ['draft', 'pending'], true) ? $mode : 'pending';

        // Two questions, because "Save and submit" is two acts in one button:
        // may they write this at all, and may they send it for approval.
        $this->authorizeAbility(
            $this->editingRequisitionId ? 'requisitions.edit' : 'requisitions.create',
            $this->requisitionScope(),
        );

        if ($mode === 'pending') {
            $this->authorizeAbility('requisitions.submit', $this->requisitionScope());
        }

        $validated = $this->validate([
            'req_type' => 'required|in:material,service',
            'req_title' => 'required|string|max:255',
            'req_justification' => 'nullable|string',
            'req_needed_by' => 'nullable|date',
            'req_priority' => 'required|in:low,normal,urgent',
            'req_job_site_id' => 'nullable|exists:job_sites,id',
            'req_requested_by' => 'nullable|exists:users,id',
            'req_requested_by_name' => 'nullable|string|max:255',
            'itemRows' => 'required|array|min:1',
            'itemRows.*.item_name' => 'nullable|string|max:255',
            'itemRows.*.description' => 'nullable|string',
            'itemRows.*.quantity' => 'nullable|numeric|min:0.01|max:99999999',
            'itemRows.*.unit' => 'nullable|string|max:50',
            'req_uploads.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [], [
            'req_type' => __('type'),
            'req_title' => __('title'),
            'req_justification' => __('justification'),
            'req_needed_by' => __('needed by date'),
            'req_priority' => __('priority'),
            'req_job_site_id' => __('location'),
            'req_requested_by' => __('requester'),
            'req_requested_by_name' => __('requester name'),
            'req_uploads.*' => __('file'),
        ]);

        $jobSiteId = $this->contextJobSite()?->id ?? ($validated['req_job_site_id'] ?: null);

        // Wherever the Location picker now points is where this lands, so the
        // grant is asked for there too.
        $this->authorizeAbility(
            $this->editingRequisitionId ? 'requisitions.edit' : 'requisitions.create',
            $jobSiteId
                ? JobSite::where('project_id', $this->contextProject()->id)->find($jobSiteId)
                : $this->contextProject(),
        );

        if ($jobSiteId && ! $this->contextProject()->jobSites()->whereKey($jobSiteId)->exists()) {
            $this->addError('req_job_site_id', __('The selected location is invalid.'));

            return;
        }

        $items = $this->collectItems();

        if ($items === []) {
            $this->addError('itemRows', __('Add at least one item with a name and a quantity.'));

            return;
        }

        // The picker only ever sets a project budget item, but a tampered id
        // would arrive the same way — re-check before it is stored.
        $budgetItemId = $this->req_budget_item_id
            && $this->projectBudgetItems()->whereKey($this->req_budget_item_id)->exists()
                ? $this->req_budget_item_id
                : null;

        $data = [
            'job_site_id' => $jobSiteId,
            'type' => $validated['req_type'],
            'title' => $validated['req_title'],
            'justification' => $validated['req_justification'] ?: null,
            'needed_by' => $validated['req_needed_by'] ?: null,
            'priority' => $validated['req_priority'],
            'budget_item_id' => $budgetItemId,
            'requested_by' => $validated['req_requested_by'] ?: null,
            'requested_by_name' => $validated['req_requested_by_name'] ?: null,
        ];

        $user = auth()->user();

        $requisition = DB::transaction(function () use ($data, $items, $mode, $user) {
            if ($this->editingRequisitionId) {
                $requisition = $this->scopedQuery()->findOrFail($this->editingRequisitionId);

                abort_unless(
                    $requisition->canBeEdited(),
                    403,
                    __('This requisition has been submitted. Return it to draft before changing it.'),
                );

                $oldStatus = $requisition->status;
                $requisition->update($data + ['status' => $mode]);

                if ($oldStatus !== $mode) {
                    $requisition->recordStatusChange($user, $oldStatus, $mode);
                }
            } else {
                $requisition = PurchaseRequisition::createWithNumber($data + [
                    'project_id' => $this->contextProject()->id,
                    'status' => $mode,
                    'created_by' => $user->id,
                ]);

                $requisition->recordStatusChange($user, null, $mode);
            }

            $this->syncItems($requisition, $items);

            return $requisition;
        });

        foreach ($this->req_uploads as $upload) {
            $path = $upload->store('requisitions', 'local');

            $requisition->attachments()->create([
                'file_path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'uploaded_by' => $user->id,
            ]);
        }

        session()->flash('message', $this->editingRequisitionId
            ? __('Requisition updated successfully.')
            : ($mode === 'draft'
                ? __('Requisition saved as a draft.')
                : __('Requisition submitted for approval.')));

        $this->closeFormModal();
    }

    /** Rows with a name and a real quantity; the rest are blanks the user left. */
    protected function collectItems(): array
    {
        $items = [];
        $sort = 0;

        foreach ($this->itemRows as $row) {
            $name = trim((string) ($row['item_name'] ?? ''));
            $quantity = (float) ($row['quantity'] ?? 0);

            if ($name === '' || $quantity <= 0) {
                continue;
            }

            $items[] = [
                'id' => $row['id'] ?? null,
                'catalog_item_id' => ($row['item_type'] ?? 'custom') === 'catalog' ? ($row['catalog_item_id'] ?: null) : null,
                'item_name' => $name,
                'item_type' => ($row['item_type'] ?? 'custom') === 'catalog' ? 'catalog' : 'custom',
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'quantity' => $quantity,
                'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
                'sort_order' => $sort++,
            ];
        }

        return $items;
    }

    protected function syncItems(PurchaseRequisition $requisition, array $items): void
    {
        $keptIds = [];

        foreach ($items as $item) {
            $id = $item['id'];
            unset($item['id']);

            if ($id && ($existing = $requisition->items()->whereKey($id)->first())) {
                $existing->update($item);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $requisition->items()->create($item)->id;
        }

        $requisition->items()->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    // =========================================================================
    // DETAIL + REVIEW
    // =========================================================================

    public function openViewModal(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.view', $requisition);

        $this->viewingRequisitionId = $requisition->id;
        $this->reviewNotes = '';
        $this->resetErrorBag('reviewNotes');
        $this->dispatch('open-modal', 'requisition-view-modal');
    }

    public function closeViewModal(): void
    {
        $this->viewingRequisitionId = null;
        $this->reviewNotes = '';
        $this->dispatch('close-modal', 'requisition-view-modal');
    }

    /** The project or job site this page writes to. */
    protected function requisitionScope(): JobSite|Project
    {
        return $this->contextJobSite() ?? $this->contextProject();
    }

    /** Whether this person may review at all, here. */
    protected function canReview(?PurchaseRequisition $requisition = null): bool
    {
        return $this->allowsAbility('requisitions.approve', $requisition ?? $this->requisitionScope());
    }

    /**
     * N2 (docs/permissions-notes.md): the reviewer must not be the requester.
     *
     * "Raised it" means either keying it in or being named as the person it is
     * for; approving your own ask is the same act under either heading.
     *
     * Not a hard stop — `requisitions.approve_own` lifts it. A two-person
     * company that would otherwise deadlock ticks one box, and the grant is on
     * the record rather than being a quiet exception in the code.
     */
    public function isSelfApproval(PurchaseRequisition $requisition): bool
    {
        $userId = auth()->id();

        return $userId !== null
            && ($requisition->created_by === $userId || $requisition->requested_by === $userId);
    }

    protected function authorizeReview(PurchaseRequisition $requisition): void
    {
        $this->authorizeAbility('requisitions.approve', $requisition);

        if ($this->isSelfApproval($requisition)) {
            $this->authorizeAbility('requisitions.approve_own', $requisition);
        }
    }

    public function submitForApproval(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.submit', $requisition);

        if ($requisition->status !== 'draft') {
            return;
        }

        $requisition->update(['status' => 'pending']);
        $requisition->recordStatusChange(auth()->user(), 'draft', 'pending');

        session()->flash('message', __('Requisition submitted for approval.'));
    }

    public function approveRequisition(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        $this->authorizeReview($requisition);

        if (! $requisition->canBeReviewed()) {
            return;
        }

        $notes = trim((string) $this->reviewNotes) ?: null;

        $requisition->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $requisition->recordStatusChange(auth()->user(), 'pending', 'approved', $notes);

        $this->reviewNotes = '';

        session()->flash('message', __('Requisition approved. It can now be quoted.'));
    }

    public function rejectRequisition(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        // Rejecting your own is not the problem self-approval is, so it needs
        // the review grant and nothing more.
        $this->authorizeAbility('requisitions.approve', $requisition);

        if (! $requisition->canBeReviewed()) {
            return;
        }

        // A rejection without a reason tells the site nothing.
        $notes = trim((string) $this->reviewNotes);

        if ($notes === '') {
            $this->addError('reviewNotes', __('Say why the requisition is being rejected.'));

            return;
        }

        $requisition->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $requisition->recordStatusChange(auth()->user(), 'pending', 'rejected', $notes);

        $this->reviewNotes = '';

        session()->flash('message', __('Requisition rejected.'));
    }

    public function cancelRequisition(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.edit', $requisition);

        if (! $requisition->canBeCancelled()) {
            return;
        }

        // Cancelling an approved requisition is a reviewer's call; the raiser
        // can still drop their own draft or pending one.
        if ($requisition->status === 'approved') {
            $this->authorizeAbility('requisitions.approve', $requisition);
        }

        $oldStatus = $requisition->status;

        $requisition->update(['status' => 'cancelled']);
        $requisition->recordStatusChange(auth()->user(), $oldStatus, 'cancelled');

        session()->flash('message', __('Requisition cancelled.'));
    }

    public function deleteRequisition(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.delete', $requisition);

        if (! $requisition->canBeDeleted()) {
            return;
        }

        $requisition->delete();

        if ($this->viewingRequisitionId === $requisitionId) {
            $this->closeViewModal();
        }

        session()->flash('message', __('Requisition deleted.'));
    }

    /**
     * Withdraw a submitted requisition so it can be changed again (N1).
     *
     * Theirs, or a reviewer's — a raiser may always pull their own back, and
     * somebody who could have approved it may send it back for more detail
     * instead of rejecting it outright.
     */
    public function returnToDraft(int $requisitionId): void
    {
        $requisition = $this->scopedQuery()->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.edit', $requisition);

        abort_unless(
            $requisition->created_by === auth()->id() || $this->canReview($requisition),
            403,
            __('Only the person who raised this requisition, or a reviewer, can return it to draft.'),
        );

        if (! $requisition->canReturnToDraft()) {
            return;
        }

        $requisition->update(['status' => 'draft']);
        $requisition->recordStatusChange(auth()->user(), 'pending', 'draft');

        session()->flash('message', __('Requisition returned to draft. It will need approving again once resubmitted.'));
    }

    /**
     * Copy a requisition into a fresh draft owned by whoever duplicated it
     * (N1, the piece the owner asked for).
     *
     * Works from **any** status, including approved and rejected: the point is
     * to raise a near-identical ask without touching a document somebody has
     * already signed. Nothing about the original's review travels with it —
     * the copy starts as a draft with no reviewer, no notes and no attachments.
     */
    public function duplicateRequisition(int $requisitionId): void
    {
        $original = $this->scopedQuery()->with('items')->findOrFail($requisitionId);

        $this->authorizeAbility('requisitions.duplicate', $original);

        $user = auth()->user();

        $copy = DB::transaction(function () use ($original, $user) {
            $copy = PurchaseRequisition::createWithNumber([
                'project_id' => $original->project_id,
                'job_site_id' => $original->job_site_id,
                'type' => $original->type,
                'title' => $original->title,
                'justification' => $original->justification,
                'needed_by' => null,          // the old date is somebody else's deadline
                'priority' => $original->priority,
                'budget_item_id' => $original->budget_item_id,
                'requested_by' => $user->id,
                'requested_by_name' => null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            foreach ($original->items as $item) {
                $copy->items()->create([
                    'catalog_item_id' => $item->catalog_item_id,
                    'item_name' => $item->item_name,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $copy->recordStatusChange($user, null, 'draft');

            return $copy;
        });

        $this->closeViewModal();

        session()->flash('message', __('Copied into :number, a new draft you can change before submitting.', [
            'number' => $copy->requisition_number,
        ]));
    }

    // =========================================================================
    // SHARED QUERIES
    // =========================================================================

    /** Every lookup stays inside the page's own project / job site. */
    protected function scopedQuery()
    {
        $query = PurchaseRequisition::where('project_id', $this->contextProject()->id);

        if ($jobSite = $this->contextJobSite()) {
            $query->where('job_site_id', $jobSite->id);
        }

        return $query;
    }

    protected function viewingRequisition(): ?PurchaseRequisition
    {
        if (! $this->viewingRequisitionId) {
            return null;
        }

        return $this->scopedQuery()
            ->with([
                'items.catalogItem',
                'jobSite',
                'budgetItem',
                'requestedBy',
                'reviewedBy',
                'createdBy',
                'statusHistories.changedBy',
                'attachments.uploadedBy',
                'quotations',
            ])
            ->find($this->viewingRequisitionId);
    }

    /** Catalog suggestions for the item picker. */
    protected function catalogSuggestions()
    {
        if (strlen(trim((string) $this->catalogSearch)) < 2) {
            return collect();
        }

        return CatalogItem::where('is_active', true)
            ->where('name', 'like', '%'.trim($this->catalogSearch).'%')
            ->orderBy('name')
            ->take(10)
            ->get();
    }

    /** Budget item suggestions, scoped to this project's budgets. */
    protected function budgetItemSuggestions()
    {
        if ($this->req_budget_item_id || strlen(trim((string) $this->budgetItemSearch)) < 1) {
            return collect();
        }

        $search = trim($this->budgetItemSearch);

        return $this->projectBudgetItems()
            ->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            })
            ->orderBy('code')
            ->take(15)
            ->get();
    }
}
