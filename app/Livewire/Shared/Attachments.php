<?php

namespace App\Livewire\Shared;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Attachment;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\QuotationVendor;
use App\Services\PermissionResolver;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The files hung off an expense, a purchase order, an income line, a
 * requisition or a quotation.
 *
 * Until F2 this component had **no guard on uploading at all** — the model was
 * fetched by an id that came from the browser, so anybody signed in could
 * attach a file to any record in the install — and deleting was a hard-coded
 * `is_admin`. Both now answer to the record the file is hanging off: uploading
 * is editing that record, and deleting an attachment is held to whoever may
 * delete the record itself.
 */
class Attachments extends Component
{
    use AuthorizesAbility;
    use WithFileUploads;

    /** 'expense', 'purchase-order', 'income', 'requisition', 'quotation' or 'quotation-vendor'. */
    public string $modelType;

    public int $modelId;

    public $upload = null;

    protected function resolveModel(): Model
    {
        return match ($this->modelType) {
            'expense' => Expense::findOrFail($this->modelId),
            'purchase-order' => PurchaseOrder::findOrFail($this->modelId),
            'income' => Income::findOrFail($this->modelId),
            'requisition' => PurchaseRequisition::findOrFail($this->modelId),
            'quotation' => Quotation::findOrFail($this->modelId),
            'quotation-vendor' => QuotationVendor::findOrFail($this->modelId),
        };
    }

    /** The permission area that owns this attachment's record. */
    protected function area(): string
    {
        return match ($this->modelType) {
            'expense' => 'expenses',
            'purchase-order' => 'purchase-orders',
            'income' => 'income',
            'requisition' => 'requisitions',
            'quotation', 'quotation-vendor' => 'quotations',
        };
    }

    /** The project or job site the record belongs to, or null for a company record. */
    protected function scope(): mixed
    {
        return app(PermissionResolver::class)->scopeOf($this->resolveModel());
    }

    public function mount(): void
    {
        // The id comes from the browser, so the record it names has to be
        // checked and not assumed.
        $this->authorizeAbility($this->area().'.view', $this->scope());
    }

    protected function storageDirectory(): string
    {
        return match ($this->modelType) {
            'expense' => 'expenses',
            'purchase-order' => 'purchase-orders',
            'income' => 'income',
            'requisition' => 'requisitions',
            'quotation' => 'quotations',
            'quotation-vendor' => 'quotations',
        };
    }

    public function save(): void
    {
        $this->authorizeAbility($this->area().'.edit', $this->scope());

        $this->validate([
            'upload' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $model = $this->resolveModel();

        $path = $this->upload->store($this->storageDirectory(), 'local');

        $model->attachments()->create([
            'file_path' => $path,
            'original_name' => $this->upload->getClientOriginalName(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->reset('upload');

        session()->flash('attachment_message', __('File uploaded successfully.'));
    }

    public function deleteAttachment(int $attachmentId): void
    {
        // Held to the record's own delete grant. For five of the six kinds
        // that is administrator-only by seed, exactly as it was; on a purchase
        // order it is whoever may delete the purchase order, which is a small
        // widening and is recorded as such in docs/review-and-improvements.md.
        $this->authorizeAbility($this->area().'.delete', $this->scope());

        $attachment = $this->resolveModel()
            ->attachments()
            ->findOrFail($attachmentId);

        $attachment->delete();

        session()->flash('attachment_message', __('File deleted.'));
    }

    /** For the view. Never a substitute for the guards above. */
    public function canUpload(): bool
    {
        return $this->allowsAbility($this->area().'.edit', $this->scope());
    }

    public function canDelete(): bool
    {
        return $this->allowsAbility($this->area().'.delete', $this->scope());
    }

    public function render()
    {
        return view('livewire.shared.attachments', [
            'attachments' => $this->resolveModel()->attachments()->with('uploadedBy')->get(),
        ]);
    }
}
