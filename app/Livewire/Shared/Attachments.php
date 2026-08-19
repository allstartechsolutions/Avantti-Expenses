<?php

namespace App\Livewire\Shared;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Attachment;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\QuotationVendor;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\WithFileUploads;

class Attachments extends Component
{
    use WithFileUploads, AuthorizesAdmin;

    public string $modelType; // 'expense', 'purchase-order', 'income', 'requisition', 'quotation' or 'quotation-vendor'
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
        $this->authorizeAdmin();

        $attachment = $this->resolveModel()
            ->attachments()
            ->findOrFail($attachmentId);

        $attachment->delete();

        session()->flash('attachment_message', __('File deleted.'));
    }

    public function render()
    {
        return view('livewire.shared.attachments', [
            'attachments' => $this->resolveModel()->attachments()->with('uploadedBy')->get(),
        ]);
    }
}
