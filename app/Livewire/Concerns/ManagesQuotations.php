<?php

namespace App\Livewire\Concerns;

use App\Models\BudgetItem;
use App\Models\CatalogItem;
use App\Models\CatalogItemPriceHistory;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\Company;
use App\Models\QuotationNegotiation;
use App\Models\QuotationRfqEmail;
use App\Models\Contract;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\BudgetService;
use App\Services\QuotationComparisonService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The quotation page, shared by the project and job-site levels.
 *
 * Phase 2 of the buy-side chain: the round itself — the scope every vendor
 * prices and the list of vendors asked. Prices, the comparison map and the
 * award come in later phases.
 */
trait ManagesQuotations
{
    // Form modal
    public $editingQuotationId = null;
    public $quo_job_site_id = '';
    public $quo_requisition_id = '';
    public $quo_type = 'material';
    public $quo_title = '';
    public $quo_description = '';
    public $quo_needed_by = '';
    public $quo_responses_due_at = '';
    public $quo_budget_item_id = null;
    public $quo_budget_item_label = '';
    public $budgetItemSearch = '';
    public $catalogSearch = '';
    public $itemRows = [];
    public $vendorRows = [];
    public $vendorSearch = '';
    public $vendorSearchAll = false;
    public $quo_uploads = [];

    // Detail modal
    public $viewingQuotationId = null;

    // Comparison map
    public $comparingQuotationId = null;

    // The award
    public $awardingQuotationId = null;
    public $awardMode = 'whole';
    public $awardVendorRowId = null;
    public $awardLines = [];
    public $awardReason = '';
    public $awardAcknowledgedNorm = false;
    public $awardAcknowledgedExpiry = false;

    // Sending the round out
    public $sendMethod = 'email';

    // Proposal entry — what the vendor e-mailed back, keyed in.
    // The same screen records a negotiation round: prices change either way,
    // and a round of haggling is that change plus the reason for it.
    public $proposalMode = 'entry';
    public $negotiationNote = '';
    public $pricingVendorRowId = null;
    public $priceRows = [];
    public $prop_source = 'email';
    public $prop_received_at = '';
    public $prop_valid_until = '';
    public $prop_lead_time_days = '';
    public $prop_payment_terms = '';
    public $prop_freight_type = '';
    public $prop_freight_amount = '';
    public $prop_discount_amount = '';
    public $prop_tax_amount = '';
    public $prop_notes = '';
    public $prop_uploads = [];

    // The RFQ e-mail
    public $rfqSubject = '';
    public $rfqBody = '';
    public $rfqCc = '';
    public $rfqRecipients = [];
    public $rfqSending = false;

    abstract protected function contextProject(): Project;

    abstract protected function contextJobSite(): ?JobSite;

    // =========================================================================
    // FORM
    // =========================================================================

    /**
     * Arriving from a requisition's "Quote it" button: the round opens
     * pre-filled with that requisition's scope. A stale or foreign id is
     * ignored rather than fatal — the page still loads.
     */
    protected function openRequisitionFromQuery(): void
    {
        $requisitionId = (int) request()->query('requisition');

        if (! $requisitionId) {
            return;
        }

        if (! $this->requisitionQuery()->whereKey($requisitionId)->exists()) {
            return;
        }

        $this->openAddFromRequisition($requisitionId);
    }

    public function openAddModal(): void
    {
        $this->resetForm();
        $this->itemRows = [$this->blankItemRow()];
        $this->dispatch('open-modal', 'quotation-form-modal');
    }

    /** Raised straight from an approved requisition: scope and context copied. */
    public function openAddFromRequisition(int $requisitionId): void
    {
        $requisition = $this->requisitionQuery()->with('items')->findOrFail($requisitionId);

        abort_unless($requisition->canBeQuoted(), 403);

        $this->resetForm();

        $this->quo_requisition_id = (string) $requisition->id;
        $this->quo_job_site_id = (string) ($requisition->job_site_id ?? '');
        $this->quo_type = $requisition->type;
        $this->quo_title = $requisition->title;
        $this->quo_description = $requisition->justification ?? '';
        $this->quo_needed_by = $requisition->needed_by?->format('Y-m-d') ?? '';
        $this->setBudgetItem($requisition->budget_item_id);

        $this->itemRows = $requisition->items->map(fn ($item) => [
            'id' => null,
            'requisition_item_id' => $item->id,
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

        $this->dispatch('open-modal', 'quotation-form-modal');
    }

    public function openEditModal(int $quotationId): void
    {
        $quotation = $this->scopedQuery()->with(['items', 'quotationVendors.vendor'])->findOrFail($quotationId);

        abort_unless($quotation->canBeEdited(), 403);

        // Escape and backdrop clicks close the modal without telling the
        // server, so start clean rather than inherit a stale session.
        $this->resetForm();

        $this->editingQuotationId = $quotation->id;
        $this->quo_requisition_id = (string) ($quotation->purchase_requisition_id ?? '');
        $this->quo_job_site_id = (string) ($quotation->job_site_id ?? '');
        $this->quo_type = $quotation->type;
        $this->quo_title = $quotation->title;
        $this->quo_description = $quotation->description ?? '';
        $this->quo_needed_by = $quotation->needed_by?->format('Y-m-d') ?? '';
        $this->quo_responses_due_at = $quotation->responses_due_at?->format('Y-m-d') ?? '';
        $this->setBudgetItem($quotation->budget_item_id);

        $this->itemRows = $quotation->items->map(fn ($item) => [
            'id' => $item->id,
            'requisition_item_id' => $item->purchase_requisition_item_id,
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

        $this->vendorRows = $quotation->quotationVendors->map(fn ($row) => [
            'id' => $row->id,
            'vendor_id' => $row->vendor_id,
            'vendor_name' => $row->vendor?->name ?? __('Unknown'),
            'email' => $row->invited_email ?? ($row->vendor?->email ?: $row->vendor?->contact_email) ?? '',
            'status' => $row->status,
        ])->all();

        $this->dispatch('open-modal', 'quotation-form-modal');
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', 'quotation-form-modal');
    }

    protected function resetForm(): void
    {
        $this->editingQuotationId = null;
        $this->quo_requisition_id = '';
        $this->quo_job_site_id = (string) ($this->contextJobSite()?->id ?? '');
        $this->quo_type = 'material';
        $this->quo_title = '';
        $this->quo_description = '';
        $this->quo_needed_by = '';
        $this->quo_responses_due_at = '';
        $this->quo_budget_item_id = null;
        $this->quo_budget_item_label = '';
        $this->budgetItemSearch = '';
        $this->catalogSearch = '';
        $this->vendorSearch = '';
        $this->vendorSearchAll = false;
        $this->itemRows = [];
        $this->vendorRows = [];
        $this->quo_uploads = [];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function blankItemRow(): array
    {
        return [
            'id' => null,
            'requisition_item_id' => null,
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
            'requisition_item_id' => null,
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

    // =========================================================================
    // THE INVITED VENDORS
    // =========================================================================

    public function addVendorRow(int $vendorId): void
    {
        $vendor = Vendor::find($vendorId);

        if (! $vendor) {
            return;
        }

        foreach ($this->vendorRows as $row) {
            if ((int) ($row['vendor_id'] ?? 0) === $vendor->id) {
                $this->vendorSearch = '';

                return;
            }
        }

        $this->vendorRows[] = [
            'id' => null,
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'email' => $vendor->email ?: ($vendor->contact_email ?: ''),
            'status' => 'invited',
        ];

        $this->vendorSearch = '';
    }

    public function removeVendorRow(int $index): void
    {
        if (! array_key_exists($index, $this->vendorRows)) {
            return;
        }

        // A vendor who already answered is part of the record; dropping them
        // would quietly delete a proposal.
        if (($this->vendorRows[$index]['status'] ?? 'invited') !== 'invited') {
            $this->addError('vendorRows', __('A vendor who already answered cannot be removed from the round.'));

            return;
        }

        unset($this->vendorRows[$index]);
        $this->vendorRows = array_values($this->vendorRows);
    }

    // =========================================================================
    // BUDGET ITEM
    // =========================================================================

    public function selectBudgetItem(int $budgetItemId): void
    {
        $this->setBudgetItem($budgetItemId);
        $this->budgetItemSearch = '';
    }

    public function clearBudgetItem(): void
    {
        $this->quo_budget_item_id = null;
        $this->quo_budget_item_label = '';
        $this->budgetItemSearch = '';
    }

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

        $this->quo_budget_item_id = $budgetItem->id;
        $this->quo_budget_item_label = $budgetItem->code.' — '.$budgetItem->name;
    }

    protected function projectBudgetItems()
    {
        return BudgetItem::whereHas('budget', function ($q) {
            $q->where('project_id', $this->contextProject()->id);
        });
    }

    // =========================================================================
    // SAVE
    // =========================================================================

    public function saveQuotation(): void
    {
        $validated = $this->validate([
            'quo_type' => 'required|in:material,service',
            'quo_title' => 'required|string|max:255',
            'quo_description' => 'nullable|string',
            'quo_needed_by' => 'nullable|date',
            'quo_responses_due_at' => 'nullable|date',
            'quo_job_site_id' => 'nullable|exists:job_sites,id',
            'quo_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'itemRows' => 'required|array|min:1',
            'itemRows.*.item_name' => 'nullable|string|max:255',
            'itemRows.*.description' => 'nullable|string',
            'itemRows.*.quantity' => 'nullable|numeric|min:0.01|max:99999999',
            'itemRows.*.unit' => 'nullable|string|max:50',
            'vendorRows.*.email' => 'nullable|email|max:255',
            'quo_uploads.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [], [
            'quo_type' => __('type'),
            'quo_title' => __('title'),
            'quo_description' => __('description'),
            'quo_needed_by' => __('needed by date'),
            'quo_responses_due_at' => __('response deadline'),
            'quo_job_site_id' => __('location'),
            'quo_requisition_id' => __('requisition'),
            'vendorRows.*.email' => __('vendor e-mail'),
            'quo_uploads.*' => __('file'),
        ]);

        $jobSiteId = $this->contextJobSite()?->id ?? ($validated['quo_job_site_id'] ?: null);

        if ($jobSiteId && ! $this->contextProject()->jobSites()->whereKey($jobSiteId)->exists()) {
            $this->addError('quo_job_site_id', __('The selected location is invalid.'));

            return;
        }

        // Both the requisition and the budget item are re-checked against this
        // page's own project: the pickers only ever offer valid ones, but a
        // tampered id arrives the same way.
        $requisitionId = $this->quo_requisition_id
            && $this->requisitionQuery()->whereKey($this->quo_requisition_id)->exists()
                ? (int) $this->quo_requisition_id
                : null;

        // A round that already points at a requisition keeps pointing at it.
        // The picker only offers quotable ones, so re-saving a round whose
        // requisition has since moved on would otherwise quietly cut the link
        // between them — and nothing would say so.
        if ($requisitionId === null && $this->editingQuotationId) {
            $existingRequisitionId = Quotation::whereKey($this->editingQuotationId)
                ->value('purchase_requisition_id');

            if ($existingRequisitionId
                && (int) $this->quo_requisition_id === (int) $existingRequisitionId) {
                $requisitionId = (int) $existingRequisitionId;
            }
        }

        $budgetItemId = $this->quo_budget_item_id
            && $this->projectBudgetItems()->whereKey($this->quo_budget_item_id)->exists()
                ? $this->quo_budget_item_id
                : null;

        $items = $this->collectItems();

        if ($items === []) {
            $this->addError('itemRows', __('Add at least one item with a name and a quantity.'));

            return;
        }

        $vendors = $this->collectVendors();

        $data = [
            'job_site_id' => $jobSiteId,
            'purchase_requisition_id' => $requisitionId,
            'type' => $validated['quo_type'],
            'title' => $validated['quo_title'],
            'description' => $validated['quo_description'] ?: null,
            'needed_by' => $validated['quo_needed_by'] ?: null,
            'responses_due_at' => $validated['quo_responses_due_at'] ?: null,
            'budget_item_id' => $budgetItemId,
        ];

        $user = auth()->user();

        $quotation = DB::transaction(function () use ($data, $items, $vendors, $user) {
            if ($this->editingQuotationId) {
                $quotation = $this->scopedQuery()->findOrFail($this->editingQuotationId);

                abort_unless($quotation->canBeEdited(), 403);

                $previousRequisitionId = $quotation->purchase_requisition_id;
                $quotation->update($data);
            } else {
                $previousRequisitionId = null;
                $quotation = Quotation::createWithNumber($data + [
                    'project_id' => $this->contextProject()->id,
                    'status' => 'draft',
                    'created_by' => $user->id,
                ]);

                $quotation->recordStatusChange($user, null, 'draft');
            }

            $this->syncItems($quotation, $items);
            $this->syncVendors($quotation, $vendors, $user);

            // The requisition tracks whether it is being quoted, and a round
            // moved off a requisition must let the old one fall back.
            $quotation->requisition?->refreshChainStatus();

            if ($previousRequisitionId && $previousRequisitionId !== $quotation->purchase_requisition_id) {
                PurchaseRequisition::find($previousRequisitionId)?->refreshChainStatus();
            }

            return $quotation;
        });

        foreach ($this->quo_uploads as $upload) {
            $path = $upload->store('quotations', 'local');

            $quotation->attachments()->create([
                'file_path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'uploaded_by' => $user->id,
            ]);
        }

        session()->flash('message', $this->editingQuotationId
            ? __('Quotation updated successfully.')
            : __('Quotation created. Invite the vendors and send it out.'));

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
                'purchase_requisition_item_id' => $row['requisition_item_id'] ?? null,
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

    protected function collectVendors(): array
    {
        $vendors = [];

        foreach ($this->vendorRows as $row) {
            $vendorId = (int) ($row['vendor_id'] ?? 0);

            if (! $vendorId || isset($vendors[$vendorId])) {
                continue;
            }

            // The picker only ever offers real vendors, but the array is the
            // client's — an id that is not a vendor is dropped rather than
            // sent to the database.
            if (! Vendor::whereKey($vendorId)->exists()) {
                continue;
            }

            $vendors[$vendorId] = [
                'vendor_id' => $vendorId,
                'invited_email' => trim((string) ($row['email'] ?? '')) ?: null,
            ];
        }

        return array_values($vendors);
    }

    protected function syncItems(Quotation $quotation, array $items): void
    {
        $keptIds = [];
        $requoted = [];

        foreach ($items as $item) {
            $id = $item['id'];
            unset($item['id']);

            if ($id && ($existing = $quotation->items()->whereKey($id)->first())) {
                // A quantity that moves invalidates every price already keyed
                // in against the old one.
                if ((float) $existing->quantity !== (float) $item['quantity']) {
                    $requoted[$existing->id] = (float) $item['quantity'];
                }

                $existing->update($item);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $quotation->items()->create($item)->id;
        }

        $quotation->items()->whereNotIn('id', $keptIds ?: [0])->delete();

        $this->requoteVendorLines($quotation, $requoted);
    }

    /**
     * Line totals are unit price × the scope quantity, so when the scope
     * quantity changes the vendors' stored totals have to follow — otherwise
     * the comparison silently uses the price of a quantity nobody quoted.
     */
    protected function requoteVendorLines(Quotation $quotation, array $requoted): void
    {
        if ($requoted === []) {
            return;
        }

        $vendorItems = \App\Models\QuotationVendorItem::query()
            ->whereIn('quotation_item_id', array_keys($requoted))
            ->whereIn('quotation_vendor_id', $quotation->quotationVendors()->select('id'))
            ->get();

        foreach ($vendorItems as $vendorItem) {
            if ($vendorItem->is_unavailable) {
                continue;
            }

            $vendorItem->update([
                'total_amount' => round((float) $vendorItem->unit_price * $requoted[$vendorItem->quotation_item_id], 2),
            ]);
        }
    }

    protected function syncVendors(Quotation $quotation, array $vendors, $user): void
    {
        $keptIds = [];

        foreach ($vendors as $vendor) {
            $existing = $quotation->quotationVendors()->where('vendor_id', $vendor['vendor_id'])->first();

            if ($existing) {
                $existing->update(['invited_email' => $vendor['invited_email']]);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $quotation->quotationVendors()->create($vendor + [
                'status' => 'invited',
                'created_by' => $user->id,
            ])->id;
        }

        // Anyone dropped from the form goes, except a vendor who has already
        // answered — their proposal is part of the record.
        //
        // Deleted one at a time on purpose: a mass delete skips the model's
        // deleting hook, which is what removes the attachments a vendor row
        // carries. Dropping an invited vendor used to leave its attachment
        // rows behind and the files themselves in storage forever.
        $quotation->quotationVendors()
            ->whereNotIn('id', $keptIds ?: [0])
            ->where('status', 'invited')
            ->get()
            ->each
            ->delete();
    }

    // =========================================================================
    // DETAIL + ROUND ACTIONS
    // =========================================================================

    public function openViewModal(int $quotationId): void
    {
        $this->viewingQuotationId = $this->scopedQuery()->findOrFail($quotationId)->id;
        $this->sendMethod = 'email';
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'quotation-view-modal');
    }

    public function closeViewModal(): void
    {
        $this->viewingQuotationId = null;
        $this->dispatch('close-modal', 'quotation-view-modal');
    }

    /**
     * The round goes out. The invitation is stamped on every vendor still
     * waiting, so the map can later show who was asked, how and when.
     */
    public function markAsSent(int $quotationId): void
    {
        $quotation = $this->scopedQuery()->with('quotationVendors')->findOrFail($quotationId);

        if (! $quotation->isOpen()) {
            return;
        }

        if (! $quotation->canBeSent()) {
            $this->addError('sendMethod', __('Add at least one item and one vendor before sending the round out.'));

            return;
        }

        $method = in_array($this->sendMethod, ['email', 'whatsapp', 'phone', 'in_person'], true)
            ? $this->sendMethod
            : 'email';

        DB::transaction(function () use ($quotation, $method) {
            foreach ($quotation->quotationVendors as $row) {
                if ($row->status !== 'invited' || $row->invited_at) {
                    continue;
                }

                $row->update([
                    'invited_at' => now(),
                    'invite_method' => $method,
                    'invited_email' => $method === 'email' ? $row->bestEmail() : $row->invited_email,
                ]);
            }

            $quotation->update(['status' => 'sent']);
            $quotation->recordStatusChange(auth()->user(), 'draft', 'sent');
            $quotation->requisition?->refreshChainStatus();
        });

        session()->flash('message', __('Round marked as sent to :count vendors.', [
            'count' => $quotation->quotationVendors->count(),
        ]));
    }

    // =========================================================================
    // THE RFQ E-MAIL — the request that goes to the vendors
    //
    // Brazilian vendors answer by e-mail, so this is how the round leaves the
    // system; what comes back is keyed in by procurement (phase 3).
    // =========================================================================

    /**
     * True when this install can actually deliver mail. The log and array
     * mailers "work" but reach nobody, so they count as not configured and the
     * screen offers the PDF instead.
     */
    public function rfqMailIsDeliverable(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, [null, '', 'log', 'array'], true)) {
            return false;
        }

        if (blank(config('mail.from.address'))) {
            return false;
        }

        if ($mailer === 'smtp') {
            return filled(config('mail.mailers.smtp.host'));
        }

        return true;
    }

    /** The e-mail composer, prefilled and with every invitable vendor ticked. */
    public function openSendModal(int $quotationId, ?int $onlyVendorRowId = null): void
    {
        $quotation = $this->scopedQuery()->with(['quotationVendors.vendor', 'items'])->findOrFail($quotationId);

        $this->viewingQuotationId = $quotation->id;
        $this->rfqSending = false;
        $this->rfqCc = '';
        $this->resetErrorBag();

        $companyName = Company::first()?->name ?? config('app.name');

        $this->rfqSubject = __('Quotation request :number — :title', [
            'number' => $quotation->quotation_number ?? '#'.$quotation->id,
            'title' => $quotation->title,
        ]);

        $due = $quotation->responses_due_at
            ? __('Please send your proposal by :date.', ['date' => $quotation->responses_due_at->format('M d, Y')])
            : __('Please send your proposal as soon as you can.');

        $this->rfqBody = __('Hello,').'<br><br>'
            .__('We are quoting the scope below and would like your proposal.').' '.$due.'<br><br>'
            .__('The attached PDF lists every item with columns for your unit prices. Please state freight (CIF or FOB), taxes, lead time, payment terms and how long the proposal stands.').'<br><br>'
            .__('Thank you,').'<br>'
            .$companyName;

        $this->rfqRecipients = $quotation->quotationVendors
            ->filter(fn ($row) => $onlyVendorRowId === null
                ? in_array($row->status, ['invited', 'responded'], true)
                : $row->id === $onlyVendorRowId)
            ->map(fn ($row) => [
                'quotation_vendor_id' => $row->id,
                'vendor_name' => $row->vendor?->name ?? __('Unknown'),
                'email' => $row->bestEmail() ?? '',
                'selected' => (bool) $row->bestEmail(),
            ])
            ->values()
            ->all();

        $this->dispatch('open-modal', 'quotation-send-modal');
    }

    /** Re-send to one vendor — a lost e-mail, a corrected address. */
    public function resendRfq(int $quotationVendorId): void
    {
        $row = $this->quotationVendorQuery()->findOrFail($quotationVendorId);

        $this->openSendModal($row->quotation_id, $row->id);
    }

    public function closeSendModal(): void
    {
        $this->rfqRecipients = [];
        $this->rfqSubject = '';
        $this->rfqBody = '';
        $this->rfqCc = '';
        $this->rfqSending = false;
        $this->resetErrorBag();
        $this->dispatch('close-modal', 'quotation-send-modal');
    }

    /**
     * Send the request to every ticked vendor.
     *
     * One failure does not stop the rest: each vendor is sent, logged and
     * reported on its own, so a single bad address cannot cost the round.
     */
    public function sendRfq(int $quotationId): void
    {
        $quotation = $this->scopedQuery()->with(['items', 'quotationVendors.vendor', 'jobSite', 'project'])->findOrFail($quotationId);

        // A cancelled or converted round is closed; nothing more goes out.
        abort_unless($quotation->isOpen(), 403);

        if (! $quotation->items()->exists()) {
            $this->addError('rfqRecipients', __('Add at least one item before sending the round out.'));

            return;
        }

        $this->validate([
            'rfqSubject' => 'required|string|max:255',
            'rfqBody' => 'required|string',
            'rfqCc' => 'nullable|string|max:255',
            'rfqRecipients.*.email' => 'nullable|email|max:255',
        ], [], [
            'rfqSubject' => __('subject'),
            'rfqBody' => __('message'),
            'rfqCc' => __('CC'),
            'rfqRecipients.*.email' => __('vendor e-mail'),
        ]);

        // Only rows that belong to this round survive, however the array
        // arrived — Livewire hands over whatever the client sent.
        $rows = $quotation->quotationVendors->keyBy('id');
        $targets = [];

        foreach ($this->rfqRecipients as $recipient) {
            if (empty($recipient['selected'])) {
                continue;
            }

            $row = $rows->get((int) ($recipient['quotation_vendor_id'] ?? 0));
            $email = trim((string) ($recipient['email'] ?? ''));

            if (! $row || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $targets[] = [$row, $email];
        }

        if ($targets === []) {
            $this->addError('rfqRecipients', __('Tick at least one vendor with a valid e-mail address.'));

            return;
        }

        $this->rfqSending = true;

        $company = Company::first();
        $user = auth()->user();
        $sent = 0;
        $failed = [];

        foreach ($targets as [$row, $email]) {
            $pdf = Pdf::loadView('pdf.quotation-rfq', [
                'quotation' => $quotation,
                'quotationVendor' => $row,
                'vendor' => $row->vendor,
                'company' => $company,
                'deliveryLocation' => $quotation->jobSite?->job_site_name
                    ?? $quotation->project?->project_name
                    ?? '—',
                'replyTo' => $company?->email,
            ]);
            $pdf->setPaper('letter', 'portrait');

            $status = 'sent';
            $error = null;

            try {
                Mail::to($email)->send(new \App\Mail\QuotationRfqMail(
                    quotation: $quotation,
                    quotationVendor: $row,
                    emailSubject: $this->rfqSubject,
                    emailBody: $this->rfqBody,
                    ccAddresses: $this->rfqCc ?: null,
                    pdfContent: $pdf->output(),
                ));

                $sent++;
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
                $failed[] = $row->vendor?->name ?? $email;

                // The user sees the count; the log keeps the stack for support.
                Log::error('RFQ e-mail failed', [
                    'quotation_id' => $quotation->id,
                    'quotation_vendor_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }

            QuotationRfqEmail::create([
                'quotation_id' => $quotation->id,
                'quotation_vendor_id' => $row->id,
                'sent_to' => $email,
                'cc' => $this->rfqCc ?: null,
                'subject' => $this->rfqSubject,
                'body' => $this->rfqBody,
                'status' => $status,
                'error' => $error,
                'sent_by' => $user->id,
                'sent_at' => now(),
            ]);

            if ($status === 'sent') {
                // The first invitation is the one that counts for the record;
                // a re-send does not rewrite when the vendor was first asked.
                $row->update([
                    'invited_at' => $row->invited_at ?? now(),
                    'invite_method' => 'email',
                    'invited_email' => $email,
                ]);
            }
        }

        if ($sent > 0 && $quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
            $quotation->recordStatusChange($user, 'draft', 'sent', __('Sent by e-mail to :count vendors.', ['count' => $sent]));
            $quotation->requisition?->refreshChainStatus();
        }

        $this->rfqSending = false;

        if ($failed !== []) {
            session()->flash('error', __('Sent to :sent, failed for :failed.', [
                'sent' => $sent,
                'failed' => implode(', ', $failed),
            ]));
        } else {
            session()->flash('message', trans_choice(
                'The request was e-mailed to one vendor.|The request was e-mailed to :count vendors.',
                $sent,
                ['count' => $sent]
            ));
        }

        $this->closeSendModal();
    }

    /** What the round has already sent, newest first. */
    protected function rfqEmails()
    {
        if (! $this->viewingQuotationId) {
            return collect();
        }

        return QuotationRfqEmail::where('quotation_id', $this->viewingQuotationId)
            ->with(['sentBy', 'quotationVendor.vendor'])
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    /** A vendor who says they will not bid. */
    public function declineVendor(int $quotationVendorId): void
    {
        $row = $this->quotationVendorQuery()->with('quotation')->findOrFail($quotationVendorId);

        if ($row->status !== 'invited' || ! $row->quotation?->isOpen()) {
            return;
        }

        $row->update(['status' => 'declined', 'responded_at' => now()]);

        session()->flash('message', __(':vendor declined to quote.', ['vendor' => $row->vendor?->name]));
    }

    /** Undo a decline — vendors change their mind. */
    public function reinviteVendor(int $quotationVendorId): void
    {
        $row = $this->quotationVendorQuery()->with('quotation')->findOrFail($quotationVendorId);

        if ($row->status !== 'declined' || ! $row->quotation?->isOpen()) {
            return;
        }

        $row->update(['status' => 'invited', 'responded_at' => null]);
    }

    public function cancelQuotation(int $quotationId): void
    {
        $this->authorizeReview();

        $quotation = $this->scopedQuery()->findOrFail($quotationId);

        if (! $quotation->canBeCancelled()) {
            return;
        }

        $oldStatus = $quotation->status;

        $quotation->update(['status' => 'cancelled']);
        $quotation->recordStatusChange(auth()->user(), $oldStatus, 'cancelled');
        $quotation->requisition?->refreshChainStatus();

        session()->flash('message', __('Quotation cancelled.'));
    }

    public function deleteQuotation(int $quotationId): void
    {
        $this->authorizeAdmin();

        $quotation = $this->scopedQuery()->findOrFail($quotationId);

        if (! $quotation->canBeDeleted()) {
            return;
        }

        $requisition = $quotation->requisition;

        $quotation->delete();
        $requisition?->refreshChainStatus();

        if ($this->viewingQuotationId === $quotationId) {
            $this->closeViewModal();
        }

        session()->flash('message', __('Quotation deleted.'));
    }

    /** Cancelling a round is a reviewer's call, like approving a requisition. */
    protected function authorizeReview(): void
    {
        abort_unless(auth()->user()?->canReviewRequisitions(), 403, 'Manager or administrator access required.');
    }

    // =========================================================================
    // PROPOSAL ENTRY — keying in what the vendor sent back
    //
    // The vendors answer by e-mail; procurement types the numbers in. Every
    // line of the shared scope gets a price, a "cannot supply" flag, or a
    // substitute — never a silent blank, because the comparison map has to be
    // able to say why a cell is empty.
    // =========================================================================

    /** Record a round of haggling: the same form, plus the reason. */
    public function openNegotiationModal(int $quotationVendorId): void
    {
        $row = $this->quotationVendorQuery()->with('quotation')->findOrFail($quotationVendorId);

        // There has to be an offer before there is anything to negotiate.
        abort_unless($this->proposalCanBeEntered($row) && $row->hasResponded(), 403);

        $this->openProposalModal($quotationVendorId, 'negotiation');
    }

    public function openProposalModal(int $quotationVendorId, string $mode = 'entry'): void
    {
        $row = $this->quotationVendorQuery()
            ->with(['vendor', 'items', 'quotation.items'])
            ->findOrFail($quotationVendorId);

        abort_unless($this->proposalCanBeEntered($row), 403);

        $this->resetProposalForm();

        $this->proposalMode = $mode === 'negotiation' ? 'negotiation' : 'entry';
        $this->pricingVendorRowId = $row->id;
        $this->prop_source = $row->source ?? 'email';
        $this->prop_received_at = $row->received_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->prop_valid_until = $row->proposal_valid_until?->format('Y-m-d') ?? '';
        $this->prop_lead_time_days = $row->lead_time_days ?? '';
        $this->prop_payment_terms = $row->payment_terms ?? '';
        $this->prop_freight_type = $row->freight_type ?? '';
        $this->prop_freight_amount = $row->hasResponded() ? $row->freight_amount : '';
        $this->prop_discount_amount = $row->hasResponded() ? $row->discount_amount : '';
        $this->prop_tax_amount = $row->hasResponded() ? $row->tax_amount : '';
        $this->prop_notes = $row->notes ?? '';

        $priced = $row->items->keyBy('quotation_item_id');

        // What this line last really cost, so the buyer keys the new price in
        // against the old one rather than from memory.
        $lastPaidByItem = $this->lastPaidFor($row->quotation->items);

        $this->priceRows = $row->quotation->items->map(function ($item) use ($priced, $lastPaidByItem) {
            $existing = $priced->get($item->id);

            return [
                'quotation_item_id' => $item->id,
                'last_paid' => $lastPaidByItem[$item->catalog_item_id] ?? null,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $existing ? (float) $existing->unit_price : '',
                'is_unavailable' => (bool) ($existing?->is_unavailable),
                'offered_brand' => $existing->offered_brand ?? '',
                'offered_spec' => $existing->offered_spec ?? '',
                'notes' => $existing->notes ?? '',
            ];
        })->all();

        $this->dispatch('open-modal', 'quotation-proposal-modal');
    }

    /**
     * The last recorded real price per catalog item, keyed by catalog id.
     * One query for the whole scope, not one per line.
     */
    protected function lastPaidFor($items): array
    {
        $catalogIds = $items->pluck('catalog_item_id')->filter()->unique()->all();

        if ($catalogIds === []) {
            return [];
        }

        return CatalogItemPriceHistory::whereIn('catalog_item_id', $catalogIds)
            ->whereNotNull('notes')
            ->orderBy('changed_at', 'desc')
            ->get()
            ->groupBy('catalog_item_id')
            ->map(fn ($rows) => [
                'amount' => (float) $rows->first()->new_cost,
                'date' => $rows->first()->changed_at?->format('M d, Y'),
            ])
            ->all();
    }

    public function closeProposalModal(): void
    {
        $this->resetProposalForm();
        $this->dispatch('close-modal', 'quotation-proposal-modal');
    }

    protected function resetProposalForm(): void
    {
        $this->proposalMode = 'entry';
        $this->negotiationNote = '';
        $this->pricingVendorRowId = null;
        $this->priceRows = [];
        $this->prop_source = 'email';
        $this->prop_received_at = '';
        $this->prop_valid_until = '';
        $this->prop_lead_time_days = '';
        $this->prop_payment_terms = '';
        $this->prop_freight_type = '';
        $this->prop_freight_amount = '';
        $this->prop_discount_amount = '';
        $this->prop_tax_amount = '';
        $this->prop_notes = '';
        $this->prop_uploads = [];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    /**
     * Prices belong to a live round and a vendor still in it. An awarded
     * proposal is frozen — changing the numbers after the award would rewrite
     * the reason the award was justified.
     */
    protected function proposalCanBeEntered($row): bool
    {
        return $row->quotation
            && $row->quotation->isOpen()
            && $row->quotation->status !== 'awarded'
            && in_array($row->status, ['invited', 'responded'], true);
    }

    public function saveProposal(): void
    {
        // Nothing open to save — a stale modal or a crafted request.
        if (! $this->pricingVendorRowId) {
            return;
        }

        $row = $this->quotationVendorQuery()
            ->with(['vendor', 'items', 'quotation.items'])
            ->findOrFail($this->pricingVendorRowId);

        abort_unless($this->proposalCanBeEntered($row), 403);

        // A negotiation with no reason on it is just a price change nobody
        // can explain later.
        if ($this->proposalMode === 'negotiation' && trim((string) $this->negotiationNote) === '') {
            $this->addError('negotiationNote', __('Say what was agreed in this round.'));

            return;
        }

        $validated = $this->validate([
            'prop_source' => 'required|in:email,whatsapp,phone,in_person',
            'prop_received_at' => 'required|date',
            'prop_valid_until' => 'nullable|date',
            'prop_lead_time_days' => 'nullable|integer|min:0|max:3650',
            'prop_payment_terms' => 'nullable|string|max:255',
            'prop_freight_type' => 'nullable|in:cif,fob',
            'prop_freight_amount' => 'nullable|numeric|min:0|max:99999999',
            'prop_discount_amount' => 'nullable|numeric|min:0|max:99999999',
            'prop_tax_amount' => 'nullable|numeric|min:0|max:99999999',
            'prop_notes' => 'nullable|string',
            'priceRows.*.unit_price' => 'nullable|numeric|min:0|max:99999999',
            'priceRows.*.offered_brand' => 'nullable|string|max:255',
            'priceRows.*.offered_spec' => 'nullable|string',
            'priceRows.*.notes' => 'nullable|string',
            'prop_uploads.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [], [
            'prop_source' => __('how it arrived'),
            'prop_received_at' => __('received on'),
            'prop_valid_until' => __('valid until'),
            'prop_lead_time_days' => __('lead time'),
            'prop_payment_terms' => __('payment terms'),
            'prop_freight_type' => __('freight type'),
            'prop_freight_amount' => __('freight'),
            'prop_discount_amount' => __('discount'),
            'prop_tax_amount' => __('taxes'),
            'prop_notes' => __('notes'),
            'priceRows.*.unit_price' => __('unit price'),
        ]);

        // A row the server never built can arrive in a public array, and a
        // line from another round must never be priced here.
        $scopeItems = $row->quotation->items->keyBy('id');
        $lines = [];

        foreach ($this->priceRows as $priceRow) {
            $itemId = (int) ($priceRow['quotation_item_id'] ?? 0);
            $item = $scopeItems->get($itemId);

            if (! $item) {
                continue;
            }

            $unavailable = (bool) ($priceRow['is_unavailable'] ?? false);
            $typed = trim((string) ($priceRow['unit_price'] ?? ''));

            // A blank price is a line the vendor did not quote — not a line
            // quoted at nothing. Storing it as zero would make a vendor who
            // skipped half the scope look like the cheapest offer. An
            // explicitly typed 0 is kept, because "included, no charge" is a
            // real answer.
            if (! $unavailable && $typed === '') {
                continue;
            }

            $unitPrice = $unavailable ? 0 : round((float) $typed, 2);

            $lines[$itemId] = [
                'quotation_item_id' => $itemId,
                'unit_price' => $unitPrice,
                'total_amount' => $unavailable ? 0 : round($unitPrice * (float) $item->quantity, 2),
                'is_unavailable' => $unavailable,
                'offered_brand' => trim((string) ($priceRow['offered_brand'] ?? '')) ?: null,
                'offered_spec' => trim((string) ($priceRow['offered_spec'] ?? '')) ?: null,
                'notes' => trim((string) ($priceRow['notes'] ?? '')) ?: null,
            ];
        }

        // A proposal with nothing priced and nothing refused is not a proposal.
        if ($lines === []) {
            $this->addError('priceRows', __('Price at least one line, or mark it as one the vendor cannot supply.'));

            return;
        }

        $user = auth()->user();

        // The equalized total as it stands before this save — the "before"
        // side of the negotiation round.
        $previousTotal = $row->hasResponded() ? $row->equalizedTotal() : 0.0;

        DB::transaction(function () use ($row, $lines, $validated, $user, $previousTotal) {
            foreach ($lines as $itemId => $line) {
                $row->items()->updateOrCreate(
                    ['quotation_item_id' => $itemId],
                    $line
                );
            }

            // Lines dropped from the scope, and lines the buyer blanked out,
            // stop being part of this proposal.
            $row->items()->whereNotIn('quotation_item_id', array_keys($lines) ?: [0])->delete();

            $wasResponded = $row->hasResponded();

            $row->update([
                'status' => $row->status === 'invited' ? 'responded' : $row->status,
                'source' => $validated['prop_source'],
                'received_at' => $validated['prop_received_at'],
                'responded_at' => $row->responded_at ?? now(),
                'proposal_valid_until' => $validated['prop_valid_until'] ?: null,
                'lead_time_days' => $validated['prop_lead_time_days'] !== '' ? (int) $validated['prop_lead_time_days'] : null,
                'payment_terms' => $validated['prop_payment_terms'] ?: null,
                'freight_type' => $validated['prop_freight_type'] ?: null,
                'freight_amount' => (float) ($validated['prop_freight_amount'] ?: 0),
                'discount_amount' => (float) ($validated['prop_discount_amount'] ?: 0),
                'tax_amount' => (float) ($validated['prop_tax_amount'] ?: 0),
                'notes' => $validated['prop_notes'] ?: null,
            ]);

            // The first proposal turns the round from waiting into comparing.
            $quotation = $row->quotation;

            if (! $wasResponded && $quotation->status === 'sent') {
                $quotation->update(['status' => 'comparing']);
                $quotation->recordStatusChange($user, 'sent', 'comparing');
            }

            if ($this->proposalMode === 'negotiation') {
                // Read the new total from a reloaded row: the prices were just
                // rewritten underneath the one in memory.
                $newTotal = $row->fresh()->load('items')->equalizedTotal();

                QuotationNegotiation::create([
                    'quotation_vendor_id' => $row->id,
                    'round' => $row->negotiations()->max('round') + 1,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                    'note' => trim((string) $this->negotiationNote),
                    'negotiated_by' => $user->id,
                    'negotiated_at' => now(),
                ]);

                if (in_array($quotation->status, ['sent', 'comparing'], true)) {
                    $oldStatus = $quotation->status;
                    $quotation->update(['status' => 'negotiating']);
                    $quotation->recordStatusChange($user, $oldStatus, 'negotiating');
                }
            }
        });

        foreach ($this->prop_uploads as $upload) {
            $path = $upload->store('quotations', 'local');

            $row->attachments()->create([
                'file_path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'uploaded_by' => $user->id,
            ]);
        }

        session()->flash('message', $this->proposalMode === 'negotiation'
            ? __('Negotiation round recorded for :vendor.', ['vendor' => $row->vendor?->name ?? __('the vendor')])
            : __('Proposal from :vendor saved.', ['vendor' => $row->vendor?->name ?? __('the vendor')]));

        $this->closeProposalModal();
    }

    /**
     * Take a proposal back off the table — keyed in against the wrong vendor,
     * or withdrawn. Destructive, so it is a reviewer's call.
     */
    public function clearProposal(int $quotationVendorId): void
    {
        $this->authorizeReview();

        $row = $this->quotationVendorQuery()->with('quotation')->findOrFail($quotationVendorId);

        if ($row->status !== 'responded') {
            return;
        }

        DB::transaction(function () use ($row) {
            $row->items()->delete();
            $row->negotiations()->delete();
            $row->update([
                'status' => 'invited',
                'responded_at' => null,
                'received_at' => null,
                'source' => null,
                'proposal_valid_until' => null,
                'lead_time_days' => null,
                'payment_terms' => null,
                'freight_type' => null,
                'freight_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]);

            // Nothing left to compare: the round is waiting on the vendors again.
            $quotation = $row->quotation;

            if ($quotation->status === 'comparing'
                && ! $quotation->quotationVendors()->whereIn('status', ['responded', 'awarded', 'rejected'])->exists()) {
                $quotation->update(['status' => 'sent']);
                $quotation->recordStatusChange(auth()->user(), 'comparing', 'sent');
            }
        });

        session()->flash('message', __('Proposal removed. The vendor is back to invited.'));
    }

    /** The totals as they stand in the form, so the screen never needs a calculator. */
    public function proposalTotals(): array
    {
        $subtotal = 0.0;

        foreach ($this->priceRows as $row) {
            if (! empty($row['is_unavailable'])) {
                continue;
            }

            $subtotal += round((float) ($row['unit_price'] ?: 0), 2) * (float) ($row['quantity'] ?? 0);
        }

        $subtotal = round($subtotal, 2);
        $freight = round((float) ($this->prop_freight_amount ?: 0), 2);
        $tax = round((float) ($this->prop_tax_amount ?: 0), 2);
        $discount = round((float) ($this->prop_discount_amount ?: 0), 2);

        return [
            'subtotal' => $subtotal,
            'freight' => $freight,
            'tax' => $tax,
            'discount' => $discount,
            'total' => round($subtotal + $freight + $tax - $discount, 2),
            'priced' => collect($this->priceRows)->filter(fn ($r) => empty($r['is_unavailable']) && trim((string) ($r['unit_price'] ?? '')) !== '')->count(),
            'unavailable' => collect($this->priceRows)->filter(fn ($r) => ! empty($r['is_unavailable']))->count(),
            'lines' => count($this->priceRows),
        ];
    }

    /** The vendor row the proposal form is working on. */
    protected function pricingVendorRow()
    {
        if (! $this->pricingVendorRowId) {
            return null;
        }

        return $this->quotationVendorQuery()
            ->with(['vendor', 'quotation', 'attachments.uploadedBy', 'negotiations'])
            ->find($this->pricingVendorRowId);
    }

    // =========================================================================
    // THE COMPARISON MAP (mapa comparativo)
    //
    // Items as rows, proposals as columns, equalized. Read-only: the award is
    // phase 6 — this screen exists to make the choice defensible.
    // =========================================================================

    public function openComparisonModal(int $quotationId): void
    {
        $quotation = $this->scopedQuery()->findOrFail($quotationId);

        $this->comparingQuotationId = $quotation->id;

        // Looking at the offers is what "comparing" means; a round still
        // marked as merely sent catches up on its own.
        if ($quotation->status === 'sent' && $quotation->quotationVendors()->whereIn('status', ['responded', 'awarded', 'rejected'])->exists()) {
            $quotation->update(['status' => 'comparing']);
            $quotation->recordStatusChange(auth()->user(), 'sent', 'comparing');
        }

        $this->dispatch('open-modal', 'quotation-comparison-modal');
    }

    public function closeComparisonModal(): void
    {
        $this->comparingQuotationId = null;
        $this->dispatch('close-modal', 'quotation-comparison-modal');
    }

    /** The map, built by the same service the PDF uses. */
    protected function comparison(): ?array
    {
        if (! $this->comparingQuotationId) {
            return null;
        }

        $quotation = $this->scopedQuery()->find($this->comparingQuotationId);

        if (! $quotation) {
            return null;
        }

        return app(QuotationComparisonService::class)->build($quotation);
    }

    // =========================================================================
    // THE AWARD (adjudicação)
    //
    // Two proposals is the floor and three the norm in Brazil, and the choice
    // has to carry a written reason: the cheapest offer is not always the one
    // taken, and the file has to say why.
    // =========================================================================

    public function openAwardModal(int $quotationId): void
    {
        $quotation = $this->scopedQuery()
            ->with(['items', 'quotationVendors.vendor', 'quotationVendors.items'])
            ->findOrFail($quotationId);

        $this->authorizeReview();
        abort_unless($quotation->canBeAwarded(), 403);

        $this->resetAwardForm();

        $this->awardingQuotationId = $quotation->id;
        $this->awardMode = 'whole';

        // The cheapest complete, unexpired offer is proposed as the default —
        // the buyer still has to say why, and can pick another.
        $map = app(QuotationComparisonService::class)->build($quotation);
        $lowest = $map['columns']->firstWhere('is_lowest', true);
        $this->awardVendorRowId = $lowest['row']->id ?? $quotation->awardableProposals()->first()?->id;

        // Per-line, the best offer is the starting point for a split.
        $this->awardLines = $map['rows']->map(function ($row) {
            $best = $row['cells']->firstWhere('is_best', true);

            return [
                'quotation_item_id' => $row['item']->id,
                'item_name' => $row['item']->item_name,
                'quantity' => (float) $row['item']->quantity,
                'unit' => $row['item']->unit,
                'vendor_row_id' => $best['vendor_row_id'] ?? null,
            ];
        })->all();

        $this->dispatch('open-modal', 'quotation-award-modal');
    }

    public function closeAwardModal(): void
    {
        $this->resetAwardForm();
        $this->dispatch('close-modal', 'quotation-award-modal');
    }

    protected function resetAwardForm(): void
    {
        $this->awardingQuotationId = null;
        $this->awardMode = 'whole';
        $this->awardVendorRowId = null;
        $this->awardLines = [];
        $this->awardReason = '';
        $this->awardAcknowledgedNorm = false;
        $this->awardAcknowledgedExpiry = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function awardQuotation(): void
    {
        if (! $this->awardingQuotationId) {
            return;
        }

        $this->authorizeReview();

        $quotation = $this->scopedQuery()
            ->with(['items', 'quotationVendors.vendor', 'quotationVendors.items'])
            ->findOrFail($this->awardingQuotationId);

        abort_unless($quotation->canBeAwarded(), 403);

        // The floor is checked again here, not just when the screen opened:
        // a proposal can be removed while the form is sitting open.
        if (! $quotation->meetsProposalMinimum()) {
            $this->addError('awardReason', __('An award needs at least two proposals on the table.'));

            return;
        }

        $reason = trim((string) $this->awardReason);

        if ($reason === '') {
            $this->addError('awardReason', __('Write why this award was chosen. It is what defends the choice later.'));

            return;
        }

        if (! $quotation->meetsProposalNorm() && ! $this->awardAcknowledgedNorm) {
            $this->addError('awardAcknowledgedNorm', __('Confirm you are awarding with fewer than three proposals.'));

            return;
        }

        $awardable = $quotation->awardableProposals()->keyBy('id');

        $winners = $this->awardMode === 'split'
            ? $this->collectSplitWinners($quotation, $awardable)
            : $this->collectWholeWinner($awardable);

        if ($winners === null) {
            return;
        }

        // An expired proposal can still be awarded — vendors usually honour
        // them — but not by accident.
        $expired = $awardable->only($winners['vendor_row_ids'])->filter(fn ($row) => $row->proposalExpired());

        if ($expired->isNotEmpty() && ! $this->awardAcknowledgedExpiry) {
            $this->addError('awardAcknowledgedExpiry', __('Confirm you are awarding a proposal that has expired.'));

            return;
        }

        $user = auth()->user();

        DB::transaction(function () use ($quotation, $winners, $reason, $user) {
            $oldStatus = $quotation->status;

            $quotation->update([
                'status' => 'awarded',
                'is_split_award' => $this->awardMode === 'split',
                'awarded_vendor_id' => $winners['awarded_vendor_id'],
                'awarded_at' => now(),
                'awarded_by' => $user->id,
                'award_reason' => $reason,
            ]);

            // Per-line winners only mean something on a split; a whole-quote
            // award clears them so the two can never disagree.
            foreach ($quotation->items as $item) {
                $item->update([
                    'awarded_quotation_vendor_id' => $winners['lines'][$item->id] ?? null,
                ]);
            }

            // Everyone who answered is either the winner or, on the record,
            // not selected — nothing is left ambiguous.
            foreach ($quotation->quotationVendors as $row) {
                if (! $row->hasResponded()) {
                    continue;
                }

                $row->update([
                    'status' => in_array($row->id, $winners['vendor_row_ids'], true) ? 'awarded' : 'rejected',
                ]);
            }

            $quotation->recordStatusChange($user, $oldStatus, 'awarded', $reason);

            $this->recordAwardedPrices($quotation, $winners['vendor_row_ids'], $user);
        });

        session()->flash('message', $this->awardMode === 'split'
            ? __('Awarded across :count vendors.', ['count' => count($winners['vendor_row_ids'])])
            : __('Awarded to :vendor.', ['vendor' => $awardable[$winners['vendor_row_ids'][0]]->vendor?->name ?? __('the vendor')]));

        $this->closeAwardModal();
    }

    /** @return array{awarded_vendor_id: int|null, vendor_row_ids: array<int>, lines: array<int, int|null>}|null */
    protected function collectWholeWinner($awardable): ?array
    {
        $row = $awardable->get((int) $this->awardVendorRowId);

        if (! $row) {
            $this->addError('awardVendorRowId', __('Choose which proposal wins.'));

            return null;
        }

        // Awarding a vendor who cannot supply a single line would produce an
        // order with nothing on it.
        if ($row->items->where('is_unavailable', false)->isEmpty()) {
            $this->addError('awardVendorRowId', __('That vendor cannot supply any line of this round.'));

            return null;
        }

        return [
            'awarded_vendor_id' => $row->vendor_id,
            'vendor_row_ids' => [$row->id],
            'lines' => [],
        ];
    }

    /** @return array{awarded_vendor_id: int|null, vendor_row_ids: array<int>, lines: array<int, int|null>}|null */
    protected function collectSplitWinners($quotation, $awardable): ?array
    {
        $scope = $quotation->items->keyBy('id');
        $lines = [];
        $vendorRowIds = [];

        foreach ($this->awardLines as $line) {
            $itemId = (int) ($line['quotation_item_id'] ?? 0);
            $vendorRowId = $line['vendor_row_id'] ? (int) $line['vendor_row_id'] : null;

            if (! $scope->has($itemId)) {
                continue;
            }

            if (! $vendorRowId) {
                $lines[$itemId] = null;

                continue;
            }

            $row = $awardable->get($vendorRowId);

            // A vendor can only win a line they actually priced.
            $priced = $row?->items->firstWhere('quotation_item_id', $itemId);

            if (! $row || ! $priced || $priced->is_unavailable) {
                $this->addError('awardLines', __('A line can only be awarded to a vendor who priced it.'));

                return null;
            }

            $lines[$itemId] = $row->id;
            $vendorRowIds[$row->id] = $row->id;
        }

        if ($vendorRowIds === []) {
            $this->addError('awardLines', __('Award at least one line, or award the whole round to one vendor.'));

            return null;
        }

        return [
            // A split has no single winning vendor on the round itself.
            'awarded_vendor_id' => null,
            'vendor_row_ids' => array_values($vendorRowIds),
            'lines' => $lines,
        ];
    }

    /**
     * Turn the award into the thing that actually gets paid.
     *
     * Material becomes a **draft purchase order** — draft on purpose, because
     * approving a PO is what creates the expense, and that control is not the
     * award's to bypass. Service becomes a **contract**, which is where the
     * payment schedule and the medições live.
     *
     * A split award produces one record per winning vendor: they are separate
     * orders with separate deliveries, and each vendor's freight, taxes and
     * discount belong to their own.
     */
    public function convertAward(int $quotationId): void
    {
        $this->authorizeReview();

        $quotation = $this->scopedQuery()
            ->with(['items', 'quotationVendors.vendor', 'quotationVendors.items'])
            ->findOrFail($quotationId);

        abort_unless($quotation->canBeConverted(), 403);

        $winners = $quotation->is_split_award
            ? $quotation->splitWinners()
            : $quotation->quotationVendors->where('status', 'awarded')->values();

        if ($winners->isEmpty()) {
            $this->addError('convert', __('This round has no winning proposal to convert.'));

            return;
        }

        $user = auth()->user();
        $created = [];

        try {
            DB::transaction(function () use ($quotation, $winners, $user, &$created) {
                // Re-read the row under a row lock, inside the transaction,
                // and check again. Without this a double click or two people
                // converting at once both pass the check above and each create
                // a full set of purchase orders or contracts — committing the
                // money twice, with only the last conversion recorded.
                $locked = Quotation::whereKey($quotation->id)->lockForUpdate()->first();

                if (! $locked || ! $locked->canBeConverted()) {
                    throw new \DomainException(__('This round has already been converted.'));
                }

                foreach ($winners as $winner) {
                    $lines = $this->awardedLinesFor($quotation, $winner);

                    // A winner with nothing to buy would produce an empty order.
                    if ($lines->isEmpty()) {
                        continue;
                    }

                    $created[] = $quotation->type === 'service'
                        ? $this->createContractFromAward($quotation, $winner, $lines, $user)
                        : $this->createPurchaseOrderFromAward($quotation, $winner, $lines, $user);
                }

                if ($created === []) {
                    throw new \DomainException(__('Nothing was awarded to convert.'));
                }

                $quotation->update([
                    'status' => 'converted',
                    // The shortcut only means something when there is one record;
                    // a split is read through the relations instead.
                    'converted_type' => count($created) === 1 ? get_class($created[0]) : null,
                    'converted_id' => count($created) === 1 ? $created[0]->id : null,
                ]);

                $quotation->recordStatusChange($user, 'awarded', 'converted', trans_choice(
                    'Converted into one :kind.|Converted into :count :kind.',
                    count($created),
                    [
                        'count' => count($created),
                        'kind' => $quotation->type === 'service'
                            ? trans_choice('contract|contracts', count($created))
                            : trans_choice('purchase order|purchase orders', count($created)),
                    ]
                ));

                $quotation->requisition?->refreshChainStatus();
            });
        } catch (\DomainException $e) {
            // Nothing suppliable survived the award — report it on the screen
            // and let the transaction roll back.
            $this->addError('convert', $e->getMessage());

            return;
        }

        session()->flash('message', trans_choice(
            'The award became one record. Open it to finish the details.|The award became :count records. Open them to finish the details.',
            count($created),
            ['count' => count($created)]
        ));
    }

    /** The scope lines this winner is actually being given. */
    protected function awardedLinesFor($quotation, $winner)
    {
        return $quotation->items
            ->map(function ($item) use ($quotation, $winner) {
                // On a split, only the lines this vendor won; on a whole-round
                // award, everything they priced and can supply.
                if ($quotation->is_split_award && (int) $item->awarded_quotation_vendor_id !== $winner->id) {
                    return null;
                }

                $priced = $winner->items->firstWhere('quotation_item_id', $item->id);

                if (! $priced || $priced->is_unavailable) {
                    return null;
                }

                return ['item' => $item, 'priced' => $priced];
            })
            ->filter()
            ->values();
    }

    protected function createPurchaseOrderFromAward($quotation, $winner, $lines, $user): PurchaseOrder
    {
        // The PO module reads suppliers off the vendor flag; a vendor who won a
        // material round is one, whether or not anybody ticked the box.
        $this->ensureVendorFlag($winner->vendor, 'is_supplier');

        $purchaseOrder = PurchaseOrder::create([
            'project_id' => $quotation->project_id,
            'job_site_id' => $quotation->job_site_id,
            'supplier_id' => $winner->vendor_id,
            'quotation_id' => $quotation->id,
            'status' => 'draft',
            'po_date' => now()->format('Y-m-d'),
            'notes' => $this->conversionNote($quotation, $winner),
            'total_amount' => $this->winnerTotal($lines, $winner),
            // Kept on the header so the lines stay exactly as the vendor
            // priced them, and so no later recalculation loses them.
            'freight_amount' => (int) $winner->freight_amount,
            'tax_amount' => (int) $winner->tax_amount,
            'discount_amount' => (int) $winner->discount_amount,
            'total_installments' => 1,
            'payment_due_date' => $quotation->needed_by?->format('Y-m-d'),
            'created_by' => $user->id,
        ]);

        foreach ($lines as $index => $line) {
            $purchaseOrder->items()->create([
                'budget_item_id' => $line['item']->budget_item_id
                    ?? $quotation->budget_item_id
                    ?? BudgetService::getDefaultItem($quotation->project_id, $quotation->job_site_id, $user->id)->id,
                'catalog_item_id' => $line['item']->catalog_item_id,
                'item_name' => $line['item']->item_name,
                'item_type' => $line['item']->item_type,
                'description' => $line['item']->description,
                'quantity' => $line['item']->quantity,
                'unit' => $line['item']->unit,
                'unit_price' => $line['priced']->unit_price,
                'total_amount' => $line['priced']->total_amount,
                'sort_order' => $index,
            ]);
        }

        $purchaseOrder->recordStatusChange(
            $user,
            null,
            'draft',
            __('Created from quotation :number.', ['number' => $quotation->quotation_number])
        );

        return $purchaseOrder;
    }

    protected function createContractFromAward($quotation, $winner, $lines, $user): Contract
    {
        $this->ensureVendorFlag($winner->vendor, 'is_subcontractor');

        $contract = Contract::create([
            'project_id' => $quotation->project_id,
            'job_site_id' => $quotation->job_site_id,
            'subcontractor_id' => $winner->vendor_id,
            'quotation_id' => $quotation->id,
            'contract_number' => Contract::generateContractNumber(),
            // A contract off the back of an award still needs its dates,
            // retention and payment schedule before it commits anything —
            // it is activated deliberately, like a purchase order is approved.
            'status' => 'draft',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => $quotation->needed_by?->format('Y-m-d'),
            'amount' => $this->winnerTotal($lines, $winner),
            'retention_percent' => 0,
            'notes' => $this->conversionNote($quotation, $winner, $lines),
            'created_by' => $user->id,
        ]);

        $contract->recordStatusChange(
            $user,
            null,
            'draft',
            __('Created from quotation :number.', ['number' => $quotation->quotation_number])
        );

        return $contract;
    }

    /**
     * What this winner is owed: their awarded lines, plus their own freight and
     * taxes less their discount — charged once per order, not per line.
     */
    protected function winnerTotal($lines, $winner): float
    {
        $lineTotal = $lines->sum(fn ($line) => (float) $line['priced']->total_amount);

        return round(
            (float) $lineTotal
            + (float) $winner->freight_amount
            + (float) $winner->tax_amount
            - (float) $winner->discount_amount,
            2
        );
    }

    /** Where this record came from, in words, on the record itself. */
    protected function conversionNote($quotation, $winner, $lines = null): string
    {
        $parts = [__('Awarded from quotation :number.', ['number' => $quotation->quotation_number])];

        if ($quotation->award_reason) {
            $parts[] = $quotation->award_reason;
        }

        if ($winner->payment_terms) {
            $parts[] = __('Payment terms quoted: :terms', ['terms' => $winner->payment_terms]);
        }

        if ($winner->lead_time_days !== null) {
            $parts[] = __('Lead time quoted: :days', [
                'days' => trans_choice(':count day|:count days', $winner->lead_time_days, ['count' => $winner->lead_time_days]),
            ]);
        }

        // A contract has no line items of its own, so the scope goes in the note.
        if ($lines !== null) {
            $parts[] = __('Scope:').' '.$lines->map(fn ($line) => trim(
                rtrim(rtrim(number_format((float) $line['item']->quantity, 2, '.', ''), '0'), '.')
                .' '.$line['item']->unit.' '.$line['item']->item_name
            ))->implode('; ');
        }

        return implode("\n\n", $parts);
    }

    /**
     * Suppliers and subcontractors are the same table behind two flags; a
     * vendor who just won a round belongs on the side that won it.
     */
    protected function ensureVendorFlag(?Vendor $vendor, string $flag): void
    {
        if ($vendor && ! $vendor->{$flag}) {
            // Vendor is fully guarded on purpose, so set and save rather than
            // mass-assign.
            $vendor->{$flag} = true;
            $vendor->save();
        }
    }

    /**
     * What was actually agreed, written into the catalog's price history.
     *
     * The catalog's own `current_cost` is left alone on purpose: it feeds
     * estimates and PO defaults, and one urgent purchase at a bad price should
     * not quietly rewrite what the company charges. The history is the record
     * of what was really paid, and it is what the next round's pickers show.
     */
    protected function recordAwardedPrices($quotation, array $winnerRowIds, $user): void
    {
        foreach ($quotation->items as $item) {
            if (! $item->catalog_item_id) {
                continue;
            }

            // Which proposal won this line: the split's own winner, or the
            // single winner of the whole round.
            $winnerId = $quotation->is_split_award
                ? $item->awarded_quotation_vendor_id
                : ($winnerRowIds[0] ?? null);

            if (! $winnerId || ! in_array($winnerId, $winnerRowIds, true)) {
                continue;
            }

            $row = $quotation->quotationVendors->firstWhere('id', $winnerId);
            $priced = $row?->items->firstWhere('quotation_item_id', $item->id);

            if (! $priced || $priced->is_unavailable) {
                continue;
            }

            $catalogItem = CatalogItem::find($item->catalog_item_id);

            if (! $catalogItem) {
                continue;
            }

            CatalogItemPriceHistory::create([
                'catalog_item_id' => $catalogItem->id,
                'old_cost' => $catalogItem->current_cost ?? $priced->unit_price,
                'new_cost' => $priced->unit_price,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'notes' => __(':number awarded to :vendor', [
                    'number' => $quotation->quotation_number,
                    'vendor' => $row->vendor?->name ?? __('Unknown'),
                ]),
            ]);
        }
    }

    /**
     * Undo an award that has not been turned into an order yet — the wrong
     * vendor picked, or the winner pulling out.
     */
    public function revokeAward(int $quotationId): void
    {
        $this->authorizeReview();

        $quotation = $this->scopedQuery()->with(['items', 'quotationVendors'])->findOrFail($quotationId);

        if (! $quotation->canRevokeAward()) {
            return;
        }

        $user = auth()->user();

        DB::transaction(function () use ($quotation, $user) {
            foreach ($quotation->quotationVendors as $row) {
                if (in_array($row->status, ['awarded', 'rejected'], true)) {
                    $row->update(['status' => 'responded']);
                }
            }

            $quotation->items()->update(['awarded_quotation_vendor_id' => null]);

            $quotation->update([
                'status' => 'comparing',
                'is_split_award' => false,
                'awarded_vendor_id' => null,
                'awarded_at' => null,
                'awarded_by' => null,
                'award_reason' => null,
            ]);

            $quotation->recordStatusChange($user, 'awarded', 'comparing', __('Award revoked.'));
        });

        session()->flash('message', __('Award revoked. The round is back to comparing.'));
    }

    /** The round the award form is working on. */
    protected function awardingQuotation()
    {
        if (! $this->awardingQuotationId) {
            return null;
        }

        return $this->scopedQuery()
            ->with(['items', 'quotationVendors.vendor', 'quotationVendors.items', 'budgetItem'])
            ->find($this->awardingQuotationId);
    }

    // =========================================================================
    // SHARED QUERIES
    // =========================================================================

    /** Every lookup stays inside the page's own project / job site. */
    protected function scopedQuery()
    {
        $query = Quotation::where('project_id', $this->contextProject()->id);

        if ($jobSite = $this->contextJobSite()) {
            $query->where('job_site_id', $jobSite->id);
        }

        return $query;
    }

    protected function quotationVendorQuery()
    {
        return \App\Models\QuotationVendor::whereIn('quotation_id', $this->scopedQuery()->select('id'));
    }

    /** Requisitions this page may quote: approved (or already being quoted). */
    protected function requisitionQuery()
    {
        $query = PurchaseRequisition::where('project_id', $this->contextProject()->id)
            ->whereIn('status', ['approved', 'quoted']);

        if ($jobSite = $this->contextJobSite()) {
            $query->where('job_site_id', $jobSite->id);
        }

        return $query;
    }

    protected function viewingQuotation(): ?Quotation
    {
        if (! $this->viewingQuotationId) {
            return null;
        }

        return $this->scopedQuery()
            ->with([
                'items.catalogItem',
                'quotationVendors.vendor',
                'quotationVendors.items',
                'quotationVendors.negotiations.negotiatedBy',
                'quotationVendors.attachments',
                'jobSite',
                'budgetItem',
                'requisition',
                'createdBy',
                'statusHistories.changedBy',
                'attachments.uploadedBy',
            ])
            ->find($this->viewingQuotationId);
    }

    protected function catalogSuggestions()
    {
        if (strlen(trim((string) $this->catalogSearch)) < 2) {
            return collect();
        }

        return CatalogItem::where('is_active', true)
            ->where('name', 'like', '%'.trim($this->catalogSearch).'%')
            ->with(['priceHistory' => fn ($q) => $q->whereNotNull('notes')->limit(1)])
            ->orderBy('name')
            ->take(10)
            ->get();
    }

    protected function budgetItemSuggestions()
    {
        if ($this->quo_budget_item_id || strlen(trim((string) $this->budgetItemSearch)) < 1) {
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

    /**
     * Vendor suggestions. A material round offers suppliers and a service
     * round offers subcontractors, because that is what each side of the
     * business keeps — but the whole list is one click away, since a vendor
     * is often both and may simply not be flagged yet.
     */
    protected function vendorSuggestions()
    {
        if (strlen(trim((string) $this->vendorSearch)) < 2) {
            return collect();
        }

        $chosen = collect($this->vendorRows)->pluck('vendor_id')->filter()->all();

        $query = Vendor::where('name', 'like', '%'.trim($this->vendorSearch).'%')
            ->whereNotIn('id', $chosen ?: [0]);

        if (! $this->vendorSearchAll) {
            $query->where($this->quo_type === 'service' ? 'is_subcontractor' : 'is_supplier', true);
        }

        return $query->orderBy('name')->take(10)->get();
    }

    /** Approved requisitions this round could be raised from. */
    protected function quotableRequisitions()
    {
        return $this->requisitionQuery()
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get(['id', 'requisition_number', 'title', 'type', 'status']);
    }
}
