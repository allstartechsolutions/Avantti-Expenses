<?php

namespace App\Livewire\Access;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\HasAbilityMatrix;
use App\Models\PermissionAudit;
use App\Models\PermissionTemplate;
use App\Services\AbilityCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Settings → Roles & Access → Templates.
 *
 * A template is a named ability list for **one project or one job site** —
 * "Site Supervisor", "Procurement", "Client (read only)". Inviting somebody
 * copies it onto their membership, and the membership is the truth from then
 * on: editing a template never changes what an existing member can already do.
 * That is deliberate, and the screen says so.
 */
class TemplateManager extends Component
{
    use AuthorizesAbility;
    use HasAbilityMatrix;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $level = 'project';

    public bool $isGuest = false;

    public bool $canSeeMoney = true;

    /** In the display currency, converted to cents on save. */
    public string $approvalLimit = '';

    public function mount(): void
    {
        $this->authorizeAbility('access.view');
    }

    /*
    |---------------------------------------------------------------------------
    | The list
    |---------------------------------------------------------------------------
    */

    #[Computed]
    public function templates()
    {
        return PermissionTemplate::query()
            ->withCount(['abilityRows', 'memberships'])
            ->orderBy('level')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->groupBy('level');
    }

    public function levelName(string $level): string
    {
        return match ($level) {
            'project' => __('Project'),
            'job_site' => __('Job site'),
            default => __('Company-wide'),
        };
    }

    /*
    |---------------------------------------------------------------------------
    | The editor
    |---------------------------------------------------------------------------
    */

    /** Only what can actually be held at the level being edited. */
    #[Computed]
    public function matrix(): array
    {
        return $this->buildMatrix($this->level);
    }

    #[Computed]
    public function grantedCount(): int
    {
        return count($this->grantedAbilities($this->level));
    }

    #[Computed]
    public function editing(): ?PermissionTemplate
    {
        return $this->editingId ? PermissionTemplate::withCount('memberships')->find($this->editingId) : null;
    }

    public function newTemplate(string $level = 'project'): void
    {
        $this->authorizeAbility('access.manage');

        $this->reset(['editingId', 'name', 'description', 'isGuest', 'canSeeMoney', 'approvalLimit', 'granted', 'matrixSearch']);
        $this->level = $level;
        $this->resetValidation();
        $this->showModal = true;

        $this->dispatch('open-modal', 'template-modal');
    }

    public function edit(int $templateId): void
    {
        $this->authorizeAbility('access.view');

        $template = PermissionTemplate::with('abilityRows')->findOrFail($templateId);

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->description = (string) $template->description;
        $this->level = $template->level;
        $this->isGuest = $template->is_guest;
        $this->canSeeMoney = $template->can_see_money;
        $this->approvalLimit = $template->approval_limit ? number_format($template->approval_limit / 100, 2, '.', '') : '';
        $this->matrixSearch = '';

        $this->loadGrants($template->abilities());

        $this->resetValidation();
        $this->showModal = true;

        $this->dispatch('open-modal', 'template-modal');
    }

    /** Start a new template from an existing one — the usual way to make one. */
    public function duplicate(int $templateId): void
    {
        $this->authorizeAbility('access.manage');

        $this->edit($templateId);

        $this->editingId = null;
        $this->name = __(':name (copy)', ['name' => $this->name]);
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', 'template-modal');

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'description', 'isGuest', 'canSeeMoney', 'approvalLimit', 'granted', 'matrixSearch']);
    }

    /**
     * Changing the level mid-edit drops anything that cannot be held there,
     * rather than silently keeping grants the resolver would ignore.
     */
    public function updatedLevel(): void
    {
        $kept = AbilityCatalog::filter($this->grantedAbilities(), $this->level);

        $this->loadGrants($kept);

        unset($this->matrix);
    }

    public function save(): void
    {
        $this->authorizeAbility('access.manage');

        $this->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('permission_templates', 'name')
                    ->where(fn ($query) => $query->where('level', $this->level))
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'level' => ['required', Rule::in(['project', 'job_site'])],
            'approvalLimit' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'name' => __('Name'),
            'description' => __('Description'),
            'approvalLimit' => __('Approval limit'),
        ]);

        $abilities = $this->grantedAbilities($this->level);

        // A guest is an outsider: they may hold nothing sensitive, and never
        // any monetary figures.
        if ($this->isGuest) {
            $abilities = array_values(array_filter(
                $abilities,
                fn ($ability) => ! AbilityCatalog::isSensitive($ability),
            ));

            $this->canSeeMoney = false;
        }

        $template = DB::transaction(function () use ($abilities) {
            $template = $this->editing;
            $before = $template?->abilities() ?? [];

            $attributes = [
                'name' => $this->name,
                'description' => $this->description ?: null,
                'level' => $this->level,
                'is_guest' => $this->isGuest,
                'can_see_money' => $this->canSeeMoney,
                'approval_limit' => $this->approvalLimit === '' ? null : (int) round((float) $this->approvalLimit * 100),
                'updated_by' => auth()->id(),
            ];

            if ($template) {
                $template->update($attributes);
            } else {
                $template = PermissionTemplate::create($attributes + ['created_by' => auth()->id()]);
            }

            $template->syncAbilities($abilities);

            PermissionAudit::record(
                subjectType: 'template',
                subjectId: $template->id,
                action: $before === [] ? 'created' : 'updated',
                summary: $template->name.' — '.($before === []
                    ? __('Template created with :count ability(ies)', ['count' => count($abilities)])
                    : __(':added granted, :removed revoked', [
                        'added' => count(array_diff($abilities, $before)),
                        'removed' => count(array_diff($before, $abilities)),
                    ])),
                before: ['abilities' => $before],
                after: ['abilities' => $abilities],
            );

            return $template;
        });

        unset($this->templates);

        session()->flash('message', __('Template saved. People already using it keep the access they have.'));

        $this->closeModal();
    }

    public function delete(int $templateId): void
    {
        $this->authorizeAbility('access.manage');

        $template = PermissionTemplate::withCount('memberships')->findOrFail($templateId);

        if ($template->is_system) {
            session()->flash('error', __('The built-in templates cannot be deleted. Edit one, or duplicate it and change the copy.'));

            return;
        }

        PermissionAudit::record(
            subjectType: 'template',
            subjectId: $template->id,
            action: 'deleted',
            summary: __('Template deleted: :name', ['name' => $template->name]),
            before: ['abilities' => $template->abilities()],
        );

        $used = $template->memberships_count;

        $template->delete();

        unset($this->templates);

        session()->flash('message', $used > 0
            ? __('Template deleted. The :count person(s) using it keep the access they already have.', ['count' => $used])
            : __('Template deleted.'));
    }

    public function render()
    {
        return view('livewire.access.template-manager');
    }
}
