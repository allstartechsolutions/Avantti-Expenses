<?php

namespace App\Livewire\SystemSettings;

use App\Models\DocumentMessage;
use App\Models\DocumentMessageHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DocumentMessageSettings extends Component
{
    // Form fields
    public string $title = '';
    public string $body = '';
    public string $type = 'invoice';
    public bool $is_default = false;
    public bool $is_active = true;

    // Editing state
    public ?int $editingMessageId = null;

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public bool $showHistoryModal = false;

    // Delete state
    public ?int $deletingMessageId = null;
    public array $deleteMessageData = [];

    // History state
    public ?int $historyMessageId = null;
    public array $historyEntries = [];

    // Filter
    public string $filterType = 'all';

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'required|in:invoice,estimate',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
        $this->dispatch('open-modal', 'message-form-modal');
    }

    public function edit(int $id): void
    {
        $message = DocumentMessage::findOrFail($id);

        $this->editingMessageId = $message->id;
        $this->title = $message->title;
        $this->body = $message->body;
        $this->type = $message->type;
        $this->is_default = $message->is_default;
        $this->is_active = $message->is_active;

        $this->showFormModal = true;
        $this->dispatch('open-modal', 'message-form-modal');
    }

    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            if ($this->editingMessageId) {
                $message = DocumentMessage::findOrFail($this->editingMessageId);
                $original = $message->getOriginal();

                // Track changes
                if ($original['title'] !== $this->title) {
                    DocumentMessage::logHistory($message->id, 'updated', 'title', $original['title'], $this->title);
                }
                if ($original['body'] !== $this->body) {
                    DocumentMessage::logHistory($message->id, 'updated', 'body', 'Changed', 'Changed');
                }
                if ($original['type'] !== $this->type) {
                    DocumentMessage::logHistory($message->id, 'updated', 'type', $original['type'], $this->type);
                }
                if ((bool) $original['is_default'] !== $this->is_default) {
                    DocumentMessage::logHistory($message->id, 'updated', 'is_default', $original['is_default'] ? 'Yes' : 'No', $this->is_default ? 'Yes' : 'No');
                }
                if ((bool) $original['is_active'] !== $this->is_active) {
                    DocumentMessage::logHistory($message->id, 'updated', 'is_active', $original['is_active'] ? 'Yes' : 'No', $this->is_active ? 'Yes' : 'No');
                }

                // Unset other defaults of the same type if this one is being set as default
                if ($this->is_default && !$original['is_default']) {
                    DocumentMessage::where('is_default', true)
                        ->where('type', $this->type)
                        ->where('id', '!=', $message->id)
                        ->update(['is_default' => false]);
                }

                // If type changed and is_default, unset defaults in the new type
                if ($this->is_default && $original['type'] !== $this->type) {
                    DocumentMessage::where('is_default', true)
                        ->where('type', $this->type)
                        ->where('id', '!=', $message->id)
                        ->update(['is_default' => false]);
                }

                $message->update([
                    'title' => $this->title,
                    'body' => $this->body,
                    'type' => $this->type,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                ]);

                session()->flash('message', 'Message updated successfully!');
            } else {
                // Unset other defaults of the same type if this one is being set as default
                if ($this->is_default) {
                    DocumentMessage::where('is_default', true)
                        ->where('type', $this->type)
                        ->update(['is_default' => false]);
                }

                $message = DocumentMessage::create([
                    'title' => $this->title,
                    'body' => $this->body,
                    'type' => $this->type,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'created_by' => Auth::id(),
                ]);

                DocumentMessage::logHistory($message->id, 'created');

                session()->flash('message', 'Message created successfully!');
            }
        });

        $this->closeFormModal();
    }

    public function confirmDelete(int $id): void
    {
        $message = DocumentMessage::findOrFail($id);

        $this->deletingMessageId = $id;
        $this->deleteMessageData = [
            'title' => $message->title,
            'type' => $message->type,
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'message-delete-modal');
    }

    public function delete(): void
    {
        $message = DocumentMessage::findOrFail($this->deletingMessageId);

        DocumentMessage::logHistory($message->id, 'deleted', null, $message->title . ' (' . $message->type . ')', null);

        $message->delete();

        $this->showDeleteModal = false;
        $this->deletingMessageId = null;
        $this->deleteMessageData = [];

        session()->flash('message', 'Message deleted successfully!');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingMessageId = null;
        $this->deleteMessageData = [];
        $this->dispatch('close-modal', 'message-delete-modal');
    }

    public function viewHistory(int $id): void
    {
        $this->historyMessageId = $id;
        $this->historyEntries = DocumentMessageHistory::where('document_message_id', $id)
            ->with('changedBy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'action' => $entry->action,
                    'field_changed' => $entry->field_changed,
                    'old_value' => $entry->old_value,
                    'new_value' => $entry->new_value,
                    'changed_by' => $entry->changedBy->name ?? 'Unknown',
                    'created_at' => $entry->created_at->format('M d, Y h:i A'),
                ];
            })
            ->toArray();

        $this->showHistoryModal = true;
        $this->dispatch('open-modal', 'message-history-modal');
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyMessageId = null;
        $this->historyEntries = [];
        $this->dispatch('close-modal', 'message-history-modal');
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
        $this->dispatch('close-modal', 'message-form-modal');
    }

    public function setFilter(string $type): void
    {
        $this->filterType = $type;
    }

    private function resetForm(): void
    {
        $this->editingMessageId = null;
        $this->title = '';
        $this->body = '';
        $this->type = 'invoice';
        $this->is_default = false;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $query = DocumentMessage::query()->with('createdBy');

        if ($this->filterType !== 'all') {
            $query->where('type', $this->filterType);
        }

        $messages = $query->orderBy('type')->orderBy('title')->get();

        return view('livewire.system-settings.document-message-settings', [
            'messages' => $messages,
        ]);
    }
}
