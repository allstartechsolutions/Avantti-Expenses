<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Everything that changes a task.
 *
 * The rules live here rather than in the screens, because a task is moved from
 * three places — the task screens, a meeting being run, and (later) the
 * scheduled jobs — and "only the owner may say it is ready" has to mean the
 * same thing in all of them. Every change writes an activity row: "who moved
 * my due date?" is a question the database has to be able to answer.
 *
 * The guards themselves are on the model (Task::canMarkReady() and friends) so
 * the Blade can hide a control and this service can refuse the action, without
 * the two drifting apart.
 *
 * See docs/meetings-module-plan.md §4.
 */
class TaskService
{
    // =========================================================================
    // CREATING AND EDITING
    // =========================================================================

    /**
     * @param  array<int, int>  $assigneeIds
     */
    public function create(array $data, User $actor, array $assigneeIds = []): Task
    {
        return DB::transaction(function () use ($data, $actor, $assigneeIds) {
            $task = Task::create($data + [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->log($task, $actor, 'created', null, $task->title);

            $this->syncAssignees($task, $assigneeIds, $actor, silent: true);

            $task->parent?->refreshProgressFromSubtasks();

            return $task->fresh();
        });
    }

    /**
     * Edit the descriptive fields. Status and progress have their own methods —
     * they are transitions, not edits.
     *
     * @param  array<int, int>|null  $assigneeIds
     */
    public function update(Task $task, array $data, User $actor, ?array $assigneeIds = null): Task
    {
        abort_unless($task->canEdit($actor), 403, 'You may not edit this task.');

        return DB::transaction(function () use ($task, $data, $actor, $assigneeIds) {
            $before = $task->only(['owner_id', 'due_date', 'title', 'priority', 'project_id', 'job_site_id']);

            $task->fill($data);
            $task->updated_by = $actor->id;

            // A due date pushed into the future earns a fresh overdue warning;
            // without this the reminder never fires again after the first one.
            if ($task->isDirty('due_date') && $task->due_date?->isFuture()) {
                $task->overdue_notified_at = null;
            }

            $task->save();

            if ((string) $before['due_date'] !== (string) $task->due_date) {
                $this->log($task, $actor, 'due_date_changed',
                    $before['due_date']?->toDateString(),
                    $task->due_date?->toDateString());
            }

            if ($before['owner_id'] !== $task->owner_id) {
                $this->log($task, $actor, 'owner_changed',
                    User::find($before['owner_id'])?->name,
                    $task->owner?->name);
            }

            if ($assigneeIds !== null) {
                $this->syncAssignees($task, $assigneeIds, $actor);
            }

            return $task->fresh();
        });
    }

    // =========================================================================
    // THE STATUS MACHINE
    // =========================================================================

    /**
     * The owner declaring their own work finished. Nobody else may do this —
     * not a manager, not an admin. An admin who has to force it changes the
     * owner first, which is logged as an owner change.
     */
    public function markReady(Task $task, User $actor): Task
    {
        if (! $task->canMarkReady($actor)) {
            throw new RuntimeException($task->hasOpenSubtasks()
                ? __('Close the sub-tasks first — this task still has open ones.')
                : __('Only the owner of a task can mark it ready.'));
        }

        return $this->transition($task, $actor, 'ready', function (Task $task) use ($actor) {
            $task->ready_at = now();
            $task->ready_by = $actor->id;
        });
    }

    /**
     * The chair (or an admin or manager) accepting the work — normally during
     * the meeting, in front of everyone.
     */
    public function confirmCompletion(Task $task, User $actor, ?Meeting $meeting = null): Task
    {
        if (! $task->canConfirmCompletion($actor)) {
            throw new RuntimeException(__('This task is not waiting for confirmation, or you may not confirm it.'));
        }

        return $this->transition($task, $actor, 'completed', function (Task $task) use ($actor) {
            $task->completed_at = now();
            $task->completed_by = $actor->id;
            $task->progress = 100;
        }, $meeting);
    }

    /**
     * Completed too soon. Reopening always states why — the record of a task
     * that closed and came back is worth nothing without the reason.
     */
    public function reopen(Task $task, User $actor, string $reason): Task
    {
        if (! $task->canReopen($actor)) {
            throw new RuntimeException(__('You may not reopen this task.'));
        }

        if (trim($reason) === '') {
            throw new RuntimeException(__('Say why the task is being reopened.'));
        }

        $task = $this->transition($task, $actor, 'in_progress', function (Task $task) {
            $task->completed_at = null;
            $task->completed_by = null;
            $task->ready_at = null;
            $task->ready_by = null;
        }, notes: $reason);

        $this->log($task, $actor, 'reopened', null, null, $reason);

        return $task;
    }

    /** Work that cannot go on until something else happens. */
    public function block(Task $task, User $actor, string $reason): Task
    {
        if (trim($reason) === '') {
            throw new RuntimeException(__('Say what the task is waiting on.'));
        }

        abort_unless($task->canEdit($actor) || $task->owner_id === $actor->id, 403, 'You may not block this task.');

        return $this->transition($task, $actor, 'blocked', function (Task $task) use ($reason) {
            $task->blocked_reason = $reason;
        }, notes: $reason);
    }

    /** Back to work — the block is cleared and the reason kept in the log. */
    public function unblock(Task $task, User $actor): Task
    {
        abort_unless($task->status === 'blocked', 409, 'This task is not blocked.');
        abort_unless($task->canEdit($actor) || $task->owner_id === $actor->id, 403, 'You may not change this task.');

        return $this->transition($task, $actor, $task->progress > 0 ? 'in_progress' : 'open', function (Task $task) {
            $task->blocked_reason = null;
        });
    }

    public function cancel(Task $task, User $actor, string $reason): Task
    {
        if (! $task->canCancel($actor)) {
            throw new RuntimeException(__('You may not cancel this task.'));
        }

        if (trim($reason) === '') {
            throw new RuntimeException(__('Say why the task is being cancelled.'));
        }

        return $this->transition($task, $actor, 'cancelled', function (Task $task) use ($actor, $reason) {
            $task->cancelled_at = now();
            $task->cancelled_by = $actor->id;
            $task->cancel_reason = $reason;
        }, notes: $reason);
    }

    // =========================================================================
    // PROGRESS
    // =========================================================================

    /**
     * Move the working figure. A task with sub-tasks has no figure of its own —
     * it is the average of its children — so this refuses rather than
     * overwriting something the server computes.
     *
     * Reaching 100 does not close anything: the owner still presses Ready.
     */
    public function setProgress(Task $task, int $progress, User $actor, ?Meeting $meeting = null): Task
    {
        if ($task->isProgressDerived()) {
            throw new RuntimeException(__('This task takes its progress from its sub-tasks.'));
        }

        if (! $task->canChangeProgress($actor)) {
            throw new RuntimeException(__('You may not change the progress of this task.'));
        }

        $progress = max(0, min(100, $progress));
        $previous = $task->progress;

        if ($progress === $previous) {
            return $task;
        }

        return DB::transaction(function () use ($task, $progress, $previous, $actor, $meeting) {
            $task->progress = $progress;
            $task->updated_by = $actor->id;

            // Work that has started says so, without anyone having to set the
            // status by hand as well.
            if ($progress > 0 && $task->status === 'open') {
                $task->status = 'in_progress';
                $this->log($task, $actor, 'status_changed', 'open', 'in_progress', null, $meeting);
            }

            $task->save();

            $this->log($task, $actor, 'progress_changed', $previous.'%', $progress.'%', null, $meeting);

            $task->parent?->refreshProgressFromSubtasks();

            return $task->fresh();
        });
    }

    // =========================================================================
    // PEOPLE
    // =========================================================================

    /**
     * @param  array<int, int>  $userIds
     */
    public function syncAssignees(Task $task, array $userIds, User $actor, bool $silent = false): void
    {
        // The owner is an implicit assignee and is never listed twice.
        $userIds = collect($userIds)->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $task->owner_id)
            ->unique()
            ->values();

        $existing = $task->assignees()->pluck('users.id');

        $added = $userIds->diff($existing);
        $removed = $existing->diff($userIds);

        $task->assignees()->sync(
            $userIds->mapWithKeys(fn (int $id) => [$id => [
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]])->all()
        );

        if ($silent) {
            return;
        }

        foreach ($added as $id) {
            $this->log($task, $actor, 'assignee_added', null, User::find($id)?->name);
        }

        foreach ($removed as $id) {
            $this->log($task, $actor, 'assignee_removed', User::find($id)?->name, null);
        }
    }

    // =========================================================================
    // NOTES
    // =========================================================================

    public function addNote(Task $task, User $actor, string $body, ?Meeting $meeting = null): TaskNote
    {
        if (trim(strip_tags($body)) === '') {
            throw new RuntimeException(__('Write something before saving the note.'));
        }

        return DB::transaction(function () use ($task, $actor, $body, $meeting) {
            $note = $task->notes()->create([
                'user_id' => $actor->id,
                'meeting_id' => $meeting?->id,
                'body' => $body,
                'progress_snapshot' => $task->progress,
            ]);

            $this->log($task, $actor, 'note_added', null, null, null, $meeting);

            return $note;
        });
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * One status change, its side effects and its log line, in one place so no
     * transition can quietly skip the audit trail.
     */
    private function transition(
        Task $task,
        User $actor,
        string $status,
        ?callable $apply = null,
        ?Meeting $meeting = null,
        ?string $notes = null
    ): Task {
        return DB::transaction(function () use ($task, $actor, $status, $apply, $meeting, $notes) {
            $previous = $task->status;

            $task->status = $status;
            $task->updated_by = $actor->id;

            if ($apply) {
                $apply($task);
            }

            $task->save();

            $this->log($task, $actor, 'status_changed', $previous, $status, $notes, $meeting);

            // A parent's percentage is the average of its children, so it moves
            // whenever one of them does.
            $task->parent?->refreshProgressFromSubtasks();

            return $task->fresh();
        });
    }

    private function log(
        Task $task,
        User $actor,
        string $action,
        ?string $old = null,
        ?string $new = null,
        ?string $notes = null,
        ?Meeting $meeting = null
    ): void {
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'notes' => $notes,
            'meeting_id' => $meeting?->id,
        ]);
    }
}
