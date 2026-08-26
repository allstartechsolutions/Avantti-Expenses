<?php

namespace App\Livewire\Concerns;

use App\Models\JobSite;
use App\Models\MeetingItem;
use App\Models\Project;
use App\Services\MeetingAgendaService;
use App\Services\TaskService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Raising a line on an agenda.
 *
 * Shared by the agenda builder and the meeting itself: things come up while a
 * meeting is running, and the person taking the minute should not have to
 * leave it to write them down.
 *
 * The component using this must expose a `$meeting` property.
 */
trait RaisesAgendaItems
{
    public bool $showItemForm = false;

    /** Set when an existing line is being changed rather than a new one raised. */
    public ?int $editingItemId = null;

    public ?int $item_parent_id = null;
    public string $item_type = 'information';
    public string $item_title = '';
    public string $item_project_id = '';
    public string $item_job_site_id = '';
    public string $item_task_owner_id = '';
    public string $item_task_due_date = '';

    /**
     * The same list the task form offers, asked for once.
     *
     * Both this and `ManagesTasks::selectableProjects()` are on every screen
     * that raises an agenda item, and they were running the identical query
     * twice a render.
     */
    #[Computed]
    public function projects(): Collection
    {
        return $this->selectableProjects;
    }

    #[Computed]
    public function itemJobSites(): Collection
    {
        return $this->item_project_id
            ? JobSite::where('project_id', $this->item_project_id)->orderBy('job_site_name')->get(['id', 'job_site_name'])
            : collect();
    }

    /** The line currently open in the form, when it is an edit. */
    #[Computed]
    public function editingItem(): ?MeetingItem
    {
        return $this->editingItemId
            ? MeetingItem::with('task.owner')->find($this->editingItemId)
            : null;
    }

    /**
     * Change a line that is already on the agenda.
     *
     * For an action item this is how a meeting moves the work along: a new
     * owner, a new date, a clearer title. Those belong to the task, so they
     * are written to the task and logged there, and the agenda line follows.
     */
    public function editItem(int $itemId): void
    {
        abort_unless($this->meeting->isDraft() && $this->meeting->canEdit(auth()->user()), 403);

        $item = MeetingItem::with('task')
            ->where('meeting_id', $this->meeting->id)
            ->findOrFail($itemId);

        $this->resetItemForm();

        $this->editingItemId = $item->id;
        $this->item_parent_id = $item->parent_id;
        $this->item_type = $item->type;
        $this->item_title = $item->title;
        $this->item_project_id = (string) ($item->project_id ?? '');
        $this->item_job_site_id = (string) ($item->job_site_id ?? '');
        $this->item_task_owner_id = (string) ($item->task?->owner_id ?? auth()->id());
        $this->item_task_due_date = $item->task?->due_date?->format('Y-m-d') ?? '';

        $this->showItemForm = true;
    }

    public function openItemForm(?int $parentId = null): void
    {
        abort_unless($this->meeting->isDraft() && $this->meeting->canEdit(auth()->user()), 403);

        $this->resetItemForm();

        // A sub-item belongs where its parent belongs — and only to a line of
        // this meeting: an id from the browser proves nothing about ownership.
        $parent = $parentId
            ? MeetingItem::where('meeting_id', $this->meeting->id)->whereNull('parent_id')->find($parentId)
            : null;

        $this->item_parent_id = $parent?->id;

        if ($parent) {
            $this->item_project_id = (string) ($parent->project_id ?? '');
            $this->item_job_site_id = (string) ($parent->job_site_id ?? '');
        }

        $this->item_task_owner_id = (string) auth()->id();
        $this->showItemForm = true;
    }

    public function updatedItemProjectId(): void
    {
        $this->item_job_site_id = '';
    }

    public function saveItem(TaskService $tasks, MeetingAgendaService $agenda): void
    {
        abort_unless($this->meeting->isDraft() && $this->meeting->canEdit(auth()->user()), 403);

        $this->validate([
            'item_title' => ['required', 'string', 'max:255'],
            'item_type' => ['required', 'in:information,decision,action'],
            'item_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'item_job_site_id' => ['nullable', 'integer', 'exists:job_sites,id'],
            'item_task_owner_id' => ['required_if:item_type,action', 'nullable', 'integer', 'exists:users,id'],
            'item_task_due_date' => ['required_if:item_type,action', 'nullable', 'date'],
        ], [
            'item_task_owner_id.required_if' => __('An action item needs somebody to own it.'),
            'item_task_due_date.required_if' => __('An action item needs a date.'),
        ]);

        if ($this->editingItemId) {
            $this->applyItemEdit($tasks);

            return;
        }

        $task = null;

        // An action item is work leaving the meeting, so it becomes a task —
        // owned, dated, and carried forward on its own from here on.
        if ($this->item_type === 'action') {
            $task = $tasks->create([
                'title' => $this->item_title,
                'project_id' => $this->item_project_id ?: null,
                'job_site_id' => $this->item_job_site_id ?: null,
                'owner_id' => $this->item_task_owner_id,
                'due_date' => $this->item_task_due_date ?: null,
                'origin_meeting_id' => $this->meeting->id,
            ], auth()->user());
        }

        $item = $agenda->addItem($this->meeting, [
            'parent_id' => $this->item_parent_id,
            'project_id' => $this->item_project_id ?: null,
            'job_site_id' => $this->item_job_site_id ?: null,
            'type' => $this->item_type,
            'title' => $this->item_title,
        ], auth()->user(), $task);

        $task?->update(['origin_item_id' => $item->id]);

        $this->resetItemForm();
        $this->afterItemRaised($item);

        session()->flash('message', $task
            ? __('Item added, and task :code raised.', ['code' => $task->code()])
            : __('Item added.'));
    }

    /**
     * Write an edit back.
     *
     * What belongs to the task — title, owner, date, where it belongs — is
     * written to the task, so the change is logged on it and every other
     * screen sees it. What belongs only to this meeting's record is written to
     * the line.
     */
    protected function applyItemEdit(TaskService $tasks): void
    {
        $item = MeetingItem::with('task')
            ->where('meeting_id', $this->meeting->id)
            ->findOrFail($this->editingItemId);

        $task = $item->task;

        // Everything on a task line — title, owner, date, location — belongs to
        // the task. If the task is closed those cannot change, and quietly
        // saving the line while dropping them would tell the user their edit
        // worked when it did not.
        if ($task && ! $task->canEdit(auth()->user())) {
            $this->addError('item_title', $task->isClosed()
                ? __('Task :code is :status, so its title, owner and date cannot be changed. Reopen it first.', [
                    'code' => $task->code(), 'status' => mb_strtolower($task->getStatusLabel()),
                ])
                : __('You may not edit task :code.', ['code' => $task->code()]));

            return;
        }

        if ($task) {
            $tasks->update($task, [
                'title' => $this->item_title,
                'project_id' => $this->item_project_id ?: null,
                'job_site_id' => $this->item_job_site_id ?: null,
                'owner_id' => $this->item_task_owner_id ?: $task->owner_id,
                'due_date' => $this->item_task_due_date ?: null,
            ], auth()->user());
        }

        $item->update([
            'title' => $this->item_title,
            // A line carrying a task stays an action item: the work exists
            // whatever the minute calls the line.
            'type' => $task ? 'action' : $this->item_type,
            'project_id' => $this->item_project_id ?: null,
            'job_site_id' => $this->item_job_site_id ?: null,
        ]);

        $this->resetItemForm();
        $this->afterItemRaised($item->fresh());

        session()->flash('message', $task
            ? __('Item and task :code updated.', ['code' => $task->code()])
            : __('Item updated.'));
    }

    public function cancelItemForm(): void
    {
        $this->resetItemForm();
    }

    protected function resetItemForm(): void
    {
        $this->reset([
            'showItemForm', 'editingItemId', 'item_parent_id', 'item_title',
            'item_project_id', 'item_job_site_id', 'item_task_owner_id', 'item_task_due_date',
        ]);
        $this->item_type = 'information';
        $this->resetValidation();
    }

    /** Each screen refreshes whatever it caches about the agenda. */
    abstract protected function afterItemRaised(MeetingItem $item): void;
}
