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
use Illuminate\Support\Facades\Validator;
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

    /**
     * The files waiting to be attached, and the box the drop zone writes to.
     *
     * A queue rather than one file: people attach three photographs of the same
     * delivery, and doing that one round trip at a time is exactly the repeated
     * work the design standard says to take out. `updatedNewUploads()` moves
     * them across, so a second drop adds instead of replacing.
     */
    public array $uploads = [];

    public array $newUploads = [];

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

    /**
     * Files dropped or chosen join the queue, and the box is emptied.
     *
     * Emptied **whatever happens**, including for a file the rule refuses: one
     * left behind is invisible — the list on screen is the queue, not this —
     * and would fail every later upload with no button to remove it. The
     * refusal is said with `addError()` rather than by throwing, because
     * `validate()` ends with a bare `resetErrorBag()`.
     */
    public function updatedNewUploads(): void
    {
        $dropped = $this->newUploads;
        $this->newUploads = [];

        $refused = [];

        foreach ($dropped as $file) {
            $validator = Validator::make(['file' => $file], ['file' => $this->fileRule()]);

            if ($validator->fails()) {
                $refused[] = $file->getClientOriginalName();

                continue;
            }

            $this->uploads[] = $file;
        }

        if ($refused !== []) {
            $this->addError('newUploads', __('Not attached — must be a PDF, JPG or PNG under 10MB: :files', [
                'files' => implode(', ', $refused),
            ]));
        }
    }

    /**
     * Take a file back out of the queue before it is stored.
     *
     * NOT `removeUpload()`: that name is part of Livewire's own `$wire` API, so
     * a `wire:click` on it never reaches this class.
     */
    public function discardUpload(int $index): void
    {
        $this->uploads[$index]?->delete();

        unset($this->uploads[$index]);

        $this->uploads = array_values($this->uploads);
    }

    /** One place for what an attachment may be, so the drop zone and the save agree. */
    protected function fileRule(): string
    {
        return 'file|mimes:pdf,jpg,jpeg,png|max:10240';
    }

    public function save(): void
    {
        $this->authorizeAbility($this->area().'.edit', $this->scope());

        $this->validate([
            'uploads' => 'required|array|min:1',
            'uploads.*' => $this->fileRule(),
        ], [
            'uploads.required' => __('Choose a file first.'),
            'uploads.min' => __('Choose a file first.'),
        ]);

        $model = $this->resolveModel();
        $directory = $this->storageDirectory();

        foreach ($this->uploads as $upload) {
            $model->attachments()->create([
                'file_path' => $upload->store($directory, 'local'),
                'original_name' => $upload->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $stored = count($this->uploads);

        $this->reset(['uploads', 'newUploads']);

        session()->flash('attachment_message', trans_choice(
            ':count file uploaded.|:count files uploaded.', $stored, ['count' => $stored],
        ));
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
