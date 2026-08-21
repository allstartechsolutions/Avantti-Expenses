<?php

namespace App\Services;

use App\Mail\TaskAssignedMail;
use App\Mail\TaskClosedMail;
use App\Mail\TaskOverdueMail;
use App\Mail\TaskWeeklyDigestMail;
use App\Models\NotificationSetting;
use App\Models\Task;
use App\Models\TaskNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Telling people about their tasks.
 *
 * Four triggers, decided by the owner: created or assigned to you, closed,
 * gone past due, and one weekly digest. Everything else the module knows stays
 * in-app — an inbox nobody reads is worse than no e-mail at all.
 *
 * Three things are checked before any mail is sent, in this order: the install
 * has the trigger switched on, the person has not opted out of it, and it has
 * not already been sent. Every send is written to task_notifications, so a
 * command that runs twice in a day mails nobody twice and "why did I not get
 * an e-mail?" is a query rather than a guess.
 *
 * See docs/meetings-module-plan.md §8.
 */
class TaskNotifier
{
    // =========================================================================
    // THE INTERACTIVE ONES
    // =========================================================================

    /**
     * Somebody was made owner of a task, or added to one.
     *
     * Never the person who did it: being told about your own action is noise.
     *
     * @param  Collection<int, User>|array<int, User>|null  $only  Just these people, when somebody is added later
     */
    public function taskAssigned(Task $task, User $actor, $only = null): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::TASK_CREATED)) {
            return;
        }

        $people = $only !== null
            ? collect($only)
            : collect([$task->owner])->concat($task->assignees)->filter();

        $this->sendAfterResponse(
            NotificationSetting::TASK_CREATED,
            $task,
            $people->reject(fn (User $user) => $user->id === $actor->id),
            fn (User $user) => new TaskAssignedMail($task, $user, $actor),
        );
    }

    /**
     * A task was completed or cancelled.
     *
     * Everyone who was on it, plus whoever raised it and the chair of the
     * meeting it came from — they are the ones who asked for it.
     */
    public function taskClosed(Task $task, User $actor): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::TASK_CLOSED)) {
            return;
        }

        $people = collect([$task->owner, $task->createdBy, $task->originMeeting?->chair])
            ->concat($task->assignees)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $actor->id);

        $this->sendAfterResponse(
            NotificationSetting::TASK_CLOSED,
            $task,
            $people,
            fn (User $user) => new TaskClosedMail($task, $user, $actor),
        );
    }

    // =========================================================================
    // THE SCHEDULED ONES
    // =========================================================================

    /**
     * Tasks that went past their date since the last run.
     *
     * Sent once, not every morning: the weekly digest carries them after that.
     * Moving the due date forward clears the stamp, so a rescheduled task can
     * go overdue again later.
     *
     * @return array{tasks:int, sent:int}
     */
    public function sendOverdue(): array
    {
        if (! NotificationSetting::enabled(NotificationSetting::TASK_OVERDUE)) {
            return ['tasks' => 0, 'sent' => 0];
        }

        $tasks = Task::query()
            ->overdue()
            ->whereNull('overdue_notified_at')
            ->with(['owner', 'assignees', 'project', 'jobSite'])
            ->get();

        $sent = 0;

        foreach ($tasks as $task) {
            $people = collect([$task->owner])->concat($task->assignees)->filter()->unique('id');

            foreach ($people as $user) {
                if ($this->send(NotificationSetting::TASK_OVERDUE, $user, new TaskOverdueMail($task, $user), $task)) {
                    $sent++;
                }
            }

            // Stamped whether or not anybody could be mailed, so a task with no
            // reachable owner does not retry every morning forever.
            $task->forceFill(['overdue_notified_at' => now()])->save();
        }

        return ['tasks' => $tasks->count(), 'sent' => $sent];
    }

    /**
     * One mail per person listing everything of theirs still open.
     *
     * People with nothing open get nothing — an empty digest teaches people to
     * ignore the next one.
     *
     * @return array{users:int, sent:int}
     */
    public function sendWeeklyDigest(): array
    {
        if (! NotificationSetting::enabled(NotificationSetting::TASK_WEEKLY_DIGEST)) {
            return ['users' => 0, 'sent' => 0];
        }

        $users = User::query()
            ->where('status', \App\Enums\UserStatus::ACTIVE)
            ->where(fn (Builder $q) => $q
                ->whereHas('ownedTasks', fn (Builder $t) => $t->open())
                ->orWhereHas('assignedTasks', fn (Builder $t) => $t->open()))
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $tasks = Task::query()
                ->open()
                ->forUser($user->id)
                ->with(['project', 'jobSite', 'owner'])
                ->orderByRaw('due_date is null, due_date asc')
                ->get();

            if ($tasks->isEmpty()) {
                continue;
            }

            // The short company roll-up goes to whoever may see tasks across
            // the company — a count of everybody's work is a company figure.
            $company = app(PermissionResolver::class)->allows($user, 'tasks.view')
                && $user->isCompanyWide()
                ? [
                    'open' => Task::query()->open()->count(),
                    'overdue' => Task::query()->overdue()->count(),
                    'oldest' => Task::query()->open()->with('owner')->orderBy('created_at')->take(5)->get(),
                ]
                : null;

            if ($this->send(NotificationSetting::TASK_WEEKLY_DIGEST, $user, new TaskWeeklyDigestMail($user, $tasks, $company), null, $this->weekKey())) {
                $sent++;
            }
        }

        return ['users' => $users->count(), 'sent' => $sent];
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Queue the sending until after the response.
     *
     * The app sends mail synchronously and cannot assume a queue worker is
     * running, so this keeps the person who pressed the button from waiting on
     * SMTP without needing one.
     *
     * @param  Collection<int, User>  $people
     */
    private function sendAfterResponse(string $key, Task $task, Collection $people, callable $mailFor): void
    {
        $people = $people->filter()->unique('id')->values();

        if ($people->isEmpty()) {
            return;
        }

        $deliver = function () use ($key, $task, $people, $mailFor) {
            foreach ($people as $user) {
                $this->send($key, $user, $mailFor($user), $task);
            }
        };

        // There is no response to be after on the console, and a terminating
        // callback there would run long after the command reported success —
        // or not at all. A task raised by a command still has to tell people.
        if (app()->runningInConsole()) {
            $deliver();

            return;
        }

        dispatch($deliver)->afterResponse();
    }

    /**
     * One mail to one person, if they should get it and have not already.
     */
    private function send(string $key, User $user, Mailable $mail, ?Task $task = null, ?string $window = null): bool
    {
        if (! $user->email || ! $user->isActive() || ! $user->wantsNotification($key)) {
            return false;
        }

        // Already sent: the same task and trigger, or the same digest window.
        $already = TaskNotification::query()
            ->where('user_id', $user->id)
            ->where('type', $key)
            ->when($task, fn (Builder $q) => $q->where('task_id', $task->id))
            ->when($window, fn (Builder $q) => $q->whereJsonContains('meta->window', $window))
            ->whereNotNull('sent_at')
            ->exists();

        if ($already) {
            return false;
        }

        $record = TaskNotification::create([
            'task_id' => $task?->id,
            'user_id' => $user->id,
            'type' => $key,
            'email' => $user->email,
            'meta' => $window ? ['window' => $window] : null,
        ]);

        try {
            Mail::to($user->email)->send($mail);

            $record->update(['sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            // Kept as a failed row: it says who was not reached and why, and it
            // does not block the next attempt.
            $record->update(['error' => substr($e->getMessage(), 0, 500)]);

            Log::warning('Task notification could not be sent', [
                'type' => $key, 'user' => $user->id, 'task' => $task?->id, 'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** The week a digest belongs to, so one week's digest is sent once. */
    private function weekKey(): string
    {
        return now()->format('o-\WW');
    }
}
