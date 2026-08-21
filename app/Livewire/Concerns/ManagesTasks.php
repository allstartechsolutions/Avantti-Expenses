<?php

namespace App\Livewire\Concerns;

use App\Enums\UserStatus;
use App\Models\FileUpload;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\TaskService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * The task screens: the form, the detail view, and every action that moves a
 * task along.
 *
 * Shared on purpose. My Tasks uses it today; the project and job-site task
 * pages (phase 2) and the meeting screens (phase 5) use the same one, so a
 * task behaves identically wherever it is opened from.
 *
 * All of the rules are in TaskService and the guards on the Task model — this
 * only carries the screen state and reports what the rules said.
 *
 * See docs/meetings-module-plan.md §5.
 */
trait ManagesTasks
{
    use AuthorizesAbility;

    // The task open in the detail view.
    public ?int $viewingTaskId = null;

    // The form.
    public ?int $editingTaskId = null;
    public ?int $task_parent_id = null;
    public string $task_title = '';
    public string $task_description = '';
    public ?int $task_project_id = null;
    public ?int $task_job_site_id = null;
    public ?int $task_owner_id = null;
    public array $task_assignees = [];
    public string $task_priority = 'normal';
    public string $task_start_date = '';
    public string $task_due_date = '';

    // The note composer in the detail view.
    public string $newNoteBody = '';

    // Reason prompt, shared by reopen / block / cancel — none of which may
    // happen without one.
    public string $reasonAction = '';
    public string $reasonText = '';

    // =========================================================================
    // CONTEXT — overridden by the project and job-site pages
    // =========================================================================

    protected function taskContextProject(): ?Project
    {
        return null;
    }

    protected function taskContextJobSite(): ?JobSite
    {
        return null;
    }

    /**
     * Whether the form lets the user choose where the task belongs. Fixed on
     * the project and job-site pages, free on My Tasks.
     */
    public function taskScopeIsFixed(): bool
    {
        return $this->taskContextProject() !== null;
    }

    // =========================================================================
    // GUARDS (M13)
    //
    // Tasks are the first module where the scope is not the screen. My Tasks
    // is a cross-project list, so every grant is asked about the **task**, and
    // a task that belongs to no project at all — a personal one — is answered
    // by the role, which is what an unscoped ability does.
    // =========================================================================

    /** Where the form is currently pointing, for a create. */
    protected function taskFormScope(): JobSite|Project|null
    {
        if ($this->taskContextProject() !== null) {
            return $this->taskContextJobSite() ?? $this->taskContextProject();
        }

        if ($this->task_job_site_id) {
            return JobSite::find($this->task_job_site_id);
        }

        return $this->task_project_id ? Project::find($this->task_project_id) : null;
    }

    /** A task this person may at least see, or a 404. */
    protected function taskInScope(int $taskId): Task
    {
        $task = Task::findOrFail($taskId);

        $this->authorizeAbility('tasks.view', $task);

        return $task;
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    #[Computed]
    public function assignableUsers(): Collection
    {
        return User::where('status', UserStatus::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function selectableProjects(): Collection
    {
        return Project::orderBy('project_name')->get(['id', 'project_name']);
    }

    #[Computed]
    public function selectableJobSites(): Collection
    {
        if (! $this->task_project_id) {
            return collect();
        }

        return JobSite::where('project_id', $this->task_project_id)
            ->orderBy('job_site_name')
            ->get(['id', 'job_site_name']);
    }

    #[Computed]
    public function viewingTask(): ?Task
    {
        if (! $this->viewingTaskId) {
            return null;
        }

        return Task::with([
            'project', 'jobSite', 'owner', 'assignees', 'parent',
            'subtasks.owner',
            'notes.user', 'notes.meeting', 'notes.availableFiles',
            'availableFiles.uploadedBy',
            'activities.user', 'activities.meeting',
            'meetingItems.meeting', 'originMeeting',
            'createdBy', 'updatedBy', 'readyBy', 'completedBy', 'cancelledBy',
        ])->find($this->viewingTaskId);
    }

    // =========================================================================
    // THE DETAIL VIEW
    // =========================================================================

    public function viewTask(int $taskId): void
    {
        $this->taskInScope($taskId);

        $this->viewingTaskId = $taskId;
        $this->newNoteBody = '';
        $this->resetReason();

        $this->dispatch('open-modal', 'task-detail-modal');
    }

    public function closeTaskDetail(): void
    {
        $this->viewingTaskId = null;
        $this->newNoteBody = '';
        $this->resetReason();

        $this->dispatch('close-modal', 'task-detail-modal');
    }

    // =========================================================================
    // THE FORM
    // =========================================================================

    public function openTaskForm(?int $parentId = null): void
    {
        $this->authorizeAbility('tasks.create', $this->taskFormScope());

        $this->resetTaskForm();

        $this->task_parent_id = $parentId;
        $this->task_owner_id = auth()->id();

        // A sub-task lives where its parent lives, and cannot be moved away.
        if ($parentId && $parent = Task::find($parentId)) {
            $this->task_project_id = $parent->project_id;
            $this->task_job_site_id = $parent->job_site_id;
        } else {
            $this->task_project_id = $this->taskContextProject()?->id;
            $this->task_job_site_id = $this->taskContextJobSite()?->id;
        }

        $this->dispatch('open-modal', 'task-form-modal');
    }

    public function editTask(int $taskId): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope($taskId));

        $task = Task::with('assignees')->findOrFail($taskId);

        abort_unless($task->canEdit(auth()->user()), 403);

        $this->editingTaskId = $task->id;
        $this->task_parent_id = $task->parent_task_id;
        $this->task_title = $task->title;
        $this->task_description = (string) $task->description;
        $this->task_project_id = $task->project_id;
        $this->task_job_site_id = $task->job_site_id;
        $this->task_owner_id = $task->owner_id;
        $this->task_assignees = $task->assignees->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->task_priority = $task->priority;
        $this->task_start_date = $task->start_date?->format('Y-m-d') ?? '';
        $this->task_due_date = $task->due_date?->format('Y-m-d') ?? '';

        $this->dispatch('open-modal', 'task-form-modal');
    }

    public function saveTask(TaskService $tasks): void
    {
        $this->editingTaskId
            ? $this->authorizeAbility('tasks.edit', $this->taskInScope($this->editingTaskId))
            : $this->authorizeAbility('tasks.create', $this->taskFormScope());

        $this->validate([
            'task_title' => ['required', 'string', 'max:255'],
            'task_description' => ['nullable', 'string', 'max:5000'],
            'task_owner_id' => ['required', 'integer', 'exists:users,id'],
            'task_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'task_job_site_id' => ['nullable', 'integer', 'exists:job_sites,id'],
            'task_priority' => ['required', 'in:low,normal,high,urgent'],
            'task_start_date' => ['nullable', 'date'],
            'task_due_date' => ['nullable', 'date', 'after_or_equal:task_start_date'],
            'task_assignees' => ['array'],
            'task_assignees.*' => ['integer', 'exists:users,id'],
        ], [
            'task_due_date.after_or_equal' => __('The due date cannot be before the start date.'),
        ]);

        // A job site only means anything inside its own project.
        if ($this->task_job_site_id && ! $this->task_project_id) {
            $this->addError('task_project_id', __('Choose the project this job site belongs to.'));

            return;
        }

        $data = [
            'title' => $this->task_title,
            'description' => $this->task_description ?: null,
            'project_id' => $this->task_project_id ?: null,
            'job_site_id' => $this->task_job_site_id ?: null,
            'owner_id' => $this->task_owner_id,
            'priority' => $this->task_priority,
            'start_date' => $this->task_start_date ?: null,
            'due_date' => $this->task_due_date ?: null,
        ];

        $assignees = array_map('intval', $this->task_assignees);

        if ($this->editingTaskId) {
            $tasks->update(Task::findOrFail($this->editingTaskId), $data, auth()->user(), $assignees);
            session()->flash('message', __('Task updated.'));
        } else {
            $task = $tasks->create($data + ['parent_task_id' => $this->task_parent_id], auth()->user(), $assignees);
            session()->flash('message', __('Task :code raised.', ['code' => $task->code()]));

            // A sub-task added from the detail view leaves that view open on
            // the parent, where its effect on the roll-up is visible.
            if (! $this->task_parent_id) {
                $this->viewingTaskId = $task->id;
            }
        }

        $this->closeTaskForm();
    }

    public function closeTaskForm(): void
    {
        $this->resetTaskForm();
        $this->dispatch('close-modal', 'task-form-modal');
    }

    public function updatedTaskProjectId(): void
    {
        // The job site belonged to the previous project.
        $this->task_job_site_id = null;
    }

    protected function resetTaskForm(): void
    {
        $this->reset([
            'editingTaskId', 'task_parent_id', 'task_title', 'task_description',
            'task_project_id', 'task_job_site_id', 'task_owner_id',
            'task_assignees', 'task_start_date', 'task_due_date',
        ]);

        $this->task_priority = 'normal';
        $this->resetValidation();
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    public function setTaskProgress(int $taskId, int $progress, TaskService $tasks): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope($taskId));

        $this->runTaskAction(fn () => $tasks->setProgress(Task::findOrFail($taskId), $progress, auth()->user()));
    }

    public function markTaskReady(int $taskId, TaskService $tasks): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope($taskId));

        $this->runTaskAction(
            fn () => $tasks->markReady(Task::findOrFail($taskId), auth()->user()),
            __('Marked ready. It now waits for confirmation.')
        );
    }

    public function confirmTaskCompletion(int $taskId, TaskService $tasks): void
    {
        // Saying a task is finished is its own grant: the doer marks it ready,
        // somebody else confirms it.
        $this->authorizeAbility('tasks.close', $this->taskInScope($taskId));

        $this->runTaskAction(
            fn () => $tasks->confirmCompletion(Task::findOrFail($taskId), auth()->user()),
            __('Task completed.')
        );
    }

    public function unblockTask(int $taskId, TaskService $tasks): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope($taskId));

        $this->runTaskAction(fn () => $tasks->unblock(Task::findOrFail($taskId), auth()->user()));
    }

    public function deleteTask(int $taskId, TaskService $tasks): void
    {
        $this->authorizeAbility('tasks.delete', $this->taskInScope($taskId));

        $task = Task::findOrFail($taskId);

        $done = $this->runTaskAction(
            fn () => $tasks->delete($task, auth()->user()),
            __('Task :code deleted.', ['code' => $task->code()])
        );

        if ($done) {
            $this->closeTaskDetail();
        }
    }

    /**
     * Reopen, block and cancel all have to say why, so they go through one
     * prompt rather than three.
     */
    public function promptReason(string $action): void
    {
        $this->reasonAction = $action;
        $this->reasonText = '';
        $this->resetValidation();

        $this->dispatch('open-modal', 'task-reason-modal');
    }

    public function submitReason(TaskService $tasks): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope((int) $this->viewingTaskId));

        $task = $this->viewingTask;

        if (! $task) {
            return;
        }

        if (trim($this->reasonText) === '') {
            $this->addError('reasonText', __('This cannot be left empty.'));

            return;
        }

        $done = $this->runTaskAction(fn () => match ($this->reasonAction) {
            'reopen' => $tasks->reopen($task, auth()->user(), $this->reasonText),
            'block' => $tasks->block($task, auth()->user(), $this->reasonText),
            'cancel' => $tasks->cancel($task, auth()->user(), $this->reasonText),
            default => null,
        });

        if ($done) {
            $this->resetReason();
            $this->dispatch('close-modal', 'task-reason-modal');
        }
    }

    protected function resetReason(): void
    {
        $this->reasonAction = '';
        $this->reasonText = '';
    }

    // =========================================================================
    // NOTES AND FILES
    // =========================================================================

    public function addTaskNote(TaskService $tasks): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope((int) $this->viewingTaskId));

        $task = $this->viewingTask;

        if (! $task) {
            return;
        }

        $done = $this->runTaskAction(fn () => $tasks->addNote($task, auth()->user(), $this->newNoteBody));

        if ($done) {
            $this->newNoteBody = '';
        }
    }

    /**
     * Called by the uploader once the bytes are in storage and verified. The
     * browser only says which row landed; what it is attached to was decided
     * by the server when the upload was started.
     */
    public function taskFileUploaded(int $fileId): void
    {
        $this->authorizeAbility('tasks.edit', $this->taskInScope((int) $this->viewingTaskId));

        $file = FileUpload::with('attachable')->find($fileId);

        if (! $file || ! $file->isAvailable()) {
            return;
        }

        // A file may hang off the task itself or off one of its notes; either
        // way the task's trail is where it has to show up.
        $task = match (true) {
            $file->attachable instanceof Task => $file->attachable,
            $file->attachable instanceof TaskNote => $file->attachable->task,
            default => null,
        };

        if ($task) {
            $task->activities()->create([
                'user_id' => auth()->id(),
                'action' => 'file_added',
                'new_value' => $file->original_name,
            ]);
        }

        unset($this->viewingTask);
    }

    /**
     * The task an attachment belongs to. A file may hang off the task itself
     * or off one of its notes; either way the task is what governs it.
     */
    protected function taskBehind(FileUpload $file): ?Task
    {
        return match (true) {
            $file->attachable instanceof Task => $file->attachable,
            $file->attachable instanceof TaskNote => $file->attachable->task,
            default => null,
        };
    }

    public function downloadTaskFile(int $fileId, FileUploadService $files)
    {
        $file = FileUpload::with('attachable')->findOrFail($fileId);

        // Until M13 this had no check of any kind: any signed-in person could
        // fetch any task's attachment by walking the ids. The file is answered
        // by the task it hangs on, whether directly or through a note.
        $this->authorizeAbility('tasks.view', $this->taskBehind($file));

        abort_unless($file->isAvailable(), 404);

        $url = $files->temporaryUrl($file);

        if ($url) {
            return redirect()->away($url);
        }

        // No cloud storage on this install: the file is streamed instead.
        return response()->download(
            \Illuminate\Support\Facades\Storage::disk($file->disk)->path($file->object_key),
            $file->original_name
        );
    }

    public function deleteTaskFile(int $fileId, FileUploadService $files): void
    {
        $file = FileUpload::with('attachable')->findOrFail($fileId);

        // Your own attachment, or somebody who may change the task. The second
        // half used to be "admin or manager", asked about the person and never
        // about which task it was.
        abort_unless(
            $file->uploaded_by === auth()->id()
                || $this->allowsAbility('tasks.edit', $this->taskBehind($file)),
            403,
            __('You do not have permission to do that.'),
        );

        $files->abort($file);

        unset($this->viewingTask);

        session()->flash('message', __('File removed.'));
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Run one rule-bearing action and put whatever it refused in front of the
     * user, rather than a blank screen or a 500.
     */
    protected function runTaskAction(callable $action, ?string $success = null): bool
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return false;
        }

        unset($this->viewingTask);

        if ($success) {
            session()->flash('message', $success);
        }

        return true;
    }
}
