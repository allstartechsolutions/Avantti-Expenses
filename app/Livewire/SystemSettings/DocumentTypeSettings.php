<?php

namespace App\Livewire\SystemSettings;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\DocumentType;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The kinds of compliance document a subcontractor can be asked for.
 *
 * A type is never deleted while a document is filed under it: it is
 * **retired**, which takes it off the upload picker and out of the
 * "required documents" card while every document already filed keeps its
 * type. Deleting is only offered for a type nothing was ever filed under —
 * a typo, in practice.
 */
class DocumentTypeSettings extends Component
{
    use AuthorizesAbility;

    // Form fields
    public string $name = '';
    public string $description = '';
    public bool $requires_expiration = true;
    public int $sort_order = 0;
    public bool $is_active = true;

    public ?int $editingId = null;
    public bool $showFormModal = false;

    public function mount(): void
    {
        $this->authorizeAbility('settings.view');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('document_types', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:255'],
            'requires_expiration' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'sort_order' => __('sort order'),
            'requires_expiration' => __('requires expiration'),
        ];
    }

    public function create(): void
    {
        $this->authorizeAbility('settings.edit');

        $this->resetForm();
        $this->sort_order = (int) DocumentType::query()->where('sort_order', '<', 99)->max('sort_order') + 1;
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeAbility('settings.edit');

        $type = DocumentType::findOrFail($id);

        $this->resetForm();
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->description = (string) $type->description;
        $this->requires_expiration = $type->requires_expiration;
        $this->sort_order = $type->sort_order;
        $this->is_active = $type->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->authorizeAbility('settings.edit');

        // Trimmed before the unique rule looks, or '  W9  ' slips past 'W9'.
        $this->name = trim($this->name);
        $this->description = trim($this->description);

        $this->validate();

        $attributes = [
            'name' => trim($this->name),
            'description' => trim($this->description) ?: null,
            'requires_expiration' => $this->requires_expiration,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            DocumentType::findOrFail($this->editingId)->update($attributes);
            session()->flash('message', __('Document type updated.'));
        } else {
            DocumentType::create($attributes);
            session()->flash('message', __('Document type created.'));
        }

        $this->closeForm();
    }

    /** Retire or bring back a type. Documents filed under it are untouched either way. */
    public function toggleActive(int $id): void
    {
        $this->authorizeAbility('settings.edit');

        $type = DocumentType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        session()->flash('message', $type->is_active
            ? __(':name is offered on the upload picker again.', ['name' => __($type->name)])
            : __(':name is retired: it no longer appears on the upload picker, and every document already filed under it is kept.', ['name' => __($type->name)]));
    }

    /** Only for a type nothing was ever filed under. Anything else is retired instead. */
    public function delete(int $id): void
    {
        $this->authorizeAbility('settings.edit');

        $type = DocumentType::withCount('documents')->findOrFail($id);

        if ($type->documents_count > 0) {
            session()->flash('error', __(':name has documents filed under it and cannot be deleted. Retire it instead.', ['name' => __($type->name)]));

            return;
        }

        $type->delete();

        session()->flash('message', __('Document type deleted.'));
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->requires_expiration = true;
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $types = DocumentType::query()
            ->withCount('documents')
            ->orderByDesc('is_active')
            ->ordered()
            ->get();

        return view('livewire.system-settings.document-type-settings', [
            'types' => $types,
            'activeCount' => $types->where('is_active', true)->count(),
            'requiredCount' => $types->where('is_active', true)->where('requires_expiration', true)->count(),
        ]);
    }
}
