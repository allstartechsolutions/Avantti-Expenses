<?php

namespace App\Services;

use App\Mail\QuotationAssignedMail;
use App\Mail\QuotationCancelledMail;
use App\Mail\QuotationDueSoonMail;
use App\Mail\RequisitionAssignedMail;
use App\Mail\RequisitionAwaitingMail;
use App\Mail\RequisitionCancelledMail;
use App\Mail\RequisitionDecidedMail;
use App\Mail\RequisitionStalledMail;
use App\Mail\RequisitionSubmittedMail;
use App\Models\DefaultAssignment;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Telling people they have been given buying work to do.
 *
 * The same three checks TaskNotifier makes, in the same order: the install has
 * the trigger switched on, the person has not opted out of it, and it has not
 * already been sent. Every send is written to `notification_log` before the
 * mail leaves, so a scheduled command that runs twice in a day mails nobody
 * twice.
 *
 * **The e-mails are instructions, not FYIs.** The subject is imperative and
 * the link lands on the thing to do, not on a list to go hunting through — a
 * person who has to navigate is a person who does it tomorrow.
 *
 * The roughly sixty lines below that repeat TaskNotifier's mechanics are
 * deliberate: extracting a shared dispatcher would mean editing the live task
 * mail path, which is a bad trade on a production system. Logged in
 * docs/review-and-improvements.md for when a third module wants the same.
 */
class ProcurementNotifier
{
    /**
     * A requisition was submitted and now needs somebody to decide on it.
     *
     * Goes to the **named approver** for that location — the
     * `requisition_approver` default, walking job site → project → install —
     * and falls back to *everybody* holding `requisitions.approve` there when
     * nobody is named. The fallback is deliberately a fan-out: a submitted
     * requisition that reaches nobody is how a site waits a week for an
     * answer, and a copy too many is a much smaller problem than silence.
     *
     * Naming somebody grants them nothing. `requisitions.approve` remains the
     * only thing that decides who may approve; this only decides who is asked
     * first, which is why the fallback still reaches everyone who holds it.
     */
    public function requisitionSubmitted(PurchaseRequisition $requisition, User $actor): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::REQUISITION_SUBMITTED)) {
            return;
        }

        // Only a requisition actually waiting for a decision. A draft is not
        // an ask, and an approved one has had its answer.
        if ($requisition->status !== 'pending') {
            return;
        }

        // Never to the person who submitted it. Everything else about who is
        // on the hook lives in whoDecidesOn(), shared with the chase.
        $people = $this->whoDecidesOn($requisition, $actor);

        $this->sendAfterResponse(
            NotificationSetting::REQUISITION_SUBMITTED,
            $requisition,
            $people,
            fn (User $user) => new RequisitionSubmittedMail($requisition, $user, $actor),
            // One notice per submission. Returning a requisition to draft and
            // sending it again writes a new history row, so the next
            // submission is a new window and does mail again.
            'submitted:'.$this->submissionKey($requisition),
        );
    }

    /** N2's rule, asked of somebody other than the person acting. */
    protected function wouldBeSelfApproval(PurchaseRequisition $requisition, User $user): bool
    {
        return $requisition->created_by === $user->id || $requisition->requested_by === $user->id;
    }

    /**
     * Which submission this is.
     *
     * The id of the history row that recorded the move to `pending`. A
     * requisition pulled back to draft and sent again gets a new row, so it is
     * a new notice rather than one the dedupe swallows.
     */
    protected function submissionKey(PurchaseRequisition $requisition): string
    {
        return (string) ($requisition->statusHistories()
            ->where('new_status', 'pending')
            ->max('id') ?? 0);
    }

    /**
     * A requisition was approved or rejected — the answer, back to whoever
     * asked for it.
     *
     * The rejection half matters most: the reason is a required field, and
     * until now the text somebody was made to write reached nobody. The person
     * who raised it learned the outcome by going back and looking.
     *
     * Never to the person who made the decision, and never when they are also
     * the person who asked — approving your own requisition needs a grant, and
     * somebody who has just used it does not need telling what they did.
     */
    public function requisitionDecided(PurchaseRequisition $requisition, User $actor): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::REQUISITION_DECIDED)) {
            return;
        }

        if (! in_array($requisition->status, ['approved', 'rejected'], true)) {
            return;
        }

        $recipient = $requisition->decisionRecipient();

        if (! $recipient || $recipient->id === $actor->id) {
            return;
        }

        $this->sendAfterResponse(
            NotificationSetting::REQUISITION_DECIDED,
            $requisition,
            collect([$recipient]),
            fn (User $user) => new RequisitionDecidedMail($requisition, $user, $actor),
            // Keyed on the decision. A rejected requisition returned to draft,
            // resubmitted and decided again is a second answer and mails again.
            'decided:'.$requisition->status.':'.($requisition->reviewed_at?->timestamp ?? 0),
        );
    }

    /**
     * Submitted, and still nobody has decided.
     *
     * The mirror of the quoting stall, one step earlier in the chain: an
     * approval is a minute's work and the site is blocked until it happens, so
     * the default wait is shorter. Same window arithmetic —
     * `floor(days_waiting / N)` — so two runs in a day resolve to one window,
     * and the same cap, because a requisition nobody intends to answer should
     * show up in a review rather than mail forever.
     *
     * Goes to whoever would have been asked in the first place: the named
     * approver for that location, or everybody who may approve there.
     *
     * @return array{requisitions:int, sent:int}
     */
    public function sendAwaitingApproval(): array
    {
        if (! NotificationSetting::enabled(NotificationSetting::REQUISITION_AWAITING)) {
            return ['requisitions' => 0, 'sent' => 0];
        }

        $days = NotificationSetting::awaitingDays();
        $maxReminders = NotificationSetting::awaitingMaxReminders();

        $requisitions = PurchaseRequisition::query()
            ->where('status', 'pending')
            ->with(['project', 'jobSite', 'items', 'requestedBy', 'createdBy', 'statusHistories'])
            ->get();

        $sent = 0;
        $chased = 0;

        foreach ($requisitions as $requisition) {
            $waiting = $requisition->daysAwaitingDecision();

            if ($waiting === null) {
                continue;
            }

            $window = intdiv($waiting, $days);

            if ($window < 1 || $window > $maxReminders) {
                continue;
            }

            $people = $this->whoDecidesOn($requisition, actor: null);

            if ($people->isEmpty()) {
                continue;
            }

            $chased++;

            foreach ($people as $user) {
                if ($this->send(
                    NotificationSetting::REQUISITION_AWAITING,
                    $user,
                    new RequisitionAwaitingMail($requisition, $user, $waiting),
                    $requisition,
                    'awaiting:'.$this->submissionKey($requisition).':'.$window,
                )) {
                    $sent++;
                }
            }
        }

        return ['requisitions' => $chased, 'sent' => $sent];
    }

    /**
     * Who should be asked to decide on this requisition.
     *
     * The named approver for its location, falling back to everybody holding
     * `requisitions.approve` there. Shared by the submission notice and the
     * chase so the two can never disagree about who is on the hook.
     *
     * @return Collection<int, User>
     */
    protected function whoDecidesOn(PurchaseRequisition $requisition, ?User $actor): Collection
    {
        $scope = $requisition->jobSite ?? $requisition->project;
        $resolver = app(PermissionResolver::class);

        $named = DefaultAssignment::resolve(
            DefaultAssignment::REQUISITION_APPROVER,
            $requisition->jobSite,
            $requisition->project,
        );

        // A named approver who no longer holds the grant is not a valid
        // answer — that is exactly the case the fan-out exists for.
        $people = $named && $resolver->allows($named, 'requisitions.approve', $scope)
            ? collect([$named])
            : app(BuyerDirectory::class)->holdersOf('requisitions.approve', $scope);

        return $people
            ->when($actor !== null, fn (Collection $rows) => $rows->reject(fn (User $u) => $u->id === $actor->id))
            // Somebody who could not act on it anyway: N2 says the reviewer
            // must not be the requester, so without `approve_own` the mail is
            // a dead letter.
            ->reject(fn (User $user) => $this->wouldBeSelfApproval($requisition, $user)
                && $resolver->denies($user, 'requisitions.approve_own', $scope))
            ->values();
    }

    /**
     * A requisition was approved and handed to somebody to quote.
     *
     * Never on a draft: a suggestion on an unapproved requisition is not an
     * instruction, and mailing about one teaches people to ignore the next.
     * Never to the person who did the handing over, either — being told about
     * your own action is noise, which is TaskNotifier's rule and holds here.
     */
    public function requisitionAssigned(PurchaseRequisition $requisition, User $actor): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::REQUISITION_ASSIGNED)) {
            return;
        }

        // Approved AND assigned, in either order: the hand-off can happen on
        // the approve dialog or afterwards, and both are the same instruction.
        if ($requisition->status === 'draft' || $requisition->status === 'pending') {
            return;
        }

        $buyer = $requisition->assignedBuyer;

        if (! $buyer || $buyer->id === $actor->id) {
            return;
        }

        $this->sendAfterResponse(
            NotificationSetting::REQUISITION_ASSIGNED,
            $requisition,
            collect([$buyer]),
            fn (User $user) => new RequisitionAssignedMail($requisition, $user, $actor),
            // Keyed on the assignment so a requisition handed to Maria, taken
            // back, and handed to Maria again mails her the second time too.
            // Without this the dedupe would silently swallow it.
            $this->assignmentWindow($requisition),
        );
    }

    /**
     * Which hand-off this is, for dedupe.
     *
     * The buyer's id and the moment they were given it. Reassigning to the
     * same person later produces a different key, so the mail goes again;
     * pressing Approve twice in a minute does not.
     */
    public function assignmentWindow(PurchaseRequisition $requisition): string
    {
        return $requisition->assigned_buyer_id.':'.($requisition->assigned_at?->timestamp ?? 0);
    }

    /**
     * Somebody was made owner of a quotation round.
     *
     * Only the owner, and never the person who did it. Taking a round off
     * somebody mails nobody — they find out from the queue rather than from a
     * message telling them to stop.
     */
    public function quotationAssigned(Quotation $quotation, User $actor): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::QUOTATION_ASSIGNED)) {
            return;
        }

        $owner = $quotation->assignedTo;

        if (! $owner || $owner->id === $actor->id) {
            return;
        }

        $this->sendAfterResponse(
            NotificationSetting::QUOTATION_ASSIGNED,
            $quotation,
            collect([$owner]),
            fn (User $user) => new QuotationAssignedMail($quotation, $user, $actor, owns: true),
            // Keyed on the hand-off, not the round: handing it away and back
            // is a fresh instruction, and must mail again.
            'owner:'.$quotation->assigned_to.':'.($quotation->assigned_at?->timestamp ?? 0),
        );
    }

    /**
     * Somebody was added to a round as a collaborator.
     *
     * Just the person added — the owner does not need telling that the help
     * they asked for arrived.
     */
    public function quotationCollaboratorAdded(Quotation $quotation, User $added, User $actor): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::QUOTATION_ASSIGNED)) {
            return;
        }

        if ($added->id === $actor->id) {
            return;
        }

        $this->sendAfterResponse(
            NotificationSetting::QUOTATION_ASSIGNED,
            $quotation,
            collect([$added]),
            fn (User $user) => new QuotationAssignedMail($quotation, $user, $actor, owns: false),
            'collaborator:'.$added->id.':'.now()->timestamp,
        );
    }

    /**
     * A requisition was cancelled — the work on it stops.
     *
     * Goes to the two people it costs: whoever asked for it, who now has to
     * decide whether to raise another, and whoever was told to quote it, who
     * would otherwise carry on collecting prices for something nobody wants.
     * Never to the person who cancelled it.
     */
    public function requisitionCancelled(PurchaseRequisition $requisition, User $actor, ?string $reason = null): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::REQUISITION_CANCELLED)) {
            return;
        }

        if ($requisition->status !== 'cancelled') {
            return;
        }

        $people = collect([$requisition->decisionRecipient(), $requisition->assignedBuyer])
            ->filter()
            ->reject(fn (User $user) => $user->id === $actor->id);

        $this->sendAfterResponse(
            NotificationSetting::REQUISITION_CANCELLED,
            $requisition,
            $people,
            fn (User $user) => new RequisitionCancelledMail($requisition, $user, $actor, $reason),
        );
    }

    /**
     * A quotation round was cancelled.
     *
     * Goes to whoever was working it. The point is to stop them chasing
     * vendors for a round that no longer exists — which is the one thing an
     * in-app status change on its own will not do, because they are not
     * looking at the screen.
     */
    public function quotationCancelled(Quotation $quotation, User $actor, ?string $reason = null): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::QUOTATION_CANCELLED)) {
            return;
        }

        if ($quotation->status !== 'cancelled') {
            return;
        }

        $people = $quotation->workers()->reject(fn (User $user) => $user->id === $actor->id);

        $this->sendAfterResponse(
            NotificationSetting::QUOTATION_CANCELLED,
            $quotation,
            $people,
            fn (User $user) => new QuotationCancelledMail($quotation, $user, $actor, $reason),
        );
    }

    // =========================================================================
    // THE SCHEDULED ONES
    // =========================================================================

    /**
     * Approved, handed to somebody, and still with no round after N days.
     *
     * A one-shot stall notice is lost the first time it is archived, which
     * defeats the point, so this repeats every N days while the requisition is
     * still stalled — up to `max_reminders`, because a requisition nobody is
     * going to quote should stop shouting and start showing up in a review
     * rather than mailing forever.
     *
     * Idempotent through the window key rather than a stamp column: the key is
     * `floor(days_waiting / N)`, so two runs on the same day resolve to the
     * same window and the second sends nothing.
     *
     * The clock runs from `assigned_at`, not from the approval — a requisition
     * approved with nobody on it is not stalled, it is unassigned, and the
     * queue shows that rather than mailing about it.
     *
     * @return array{requisitions:int, sent:int}
     */
    public function sendStalled(): array
    {
        if (! NotificationSetting::enabled(NotificationSetting::REQUISITION_STALLED)) {
            return ['requisitions' => 0, 'sent' => 0];
        }

        $days = NotificationSetting::stallDays();
        $maxReminders = NotificationSetting::stallMaxReminders();

        $requisitions = PurchaseRequisition::query()
            ->where('status', 'approved')
            ->whereNotNull('assigned_buyer_id')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', now()->subDays($days))
            ->with(['assignedBuyer', 'reviewedBy', 'project', 'jobSite', 'items', 'quotations'])
            ->get()
            // `status === approved` already means no live round — the chain
            // status moves to `quoted` the moment one exists — but a stale
            // status would mail somebody about work they have already done,
            // so it is checked rather than assumed.
            ->filter(fn (PurchaseRequisition $requisition) => ! $requisition->isAlreadyQuoted());

        $sent = 0;
        $nudged = 0;

        foreach ($requisitions as $requisition) {
            $waiting = (int) $requisition->assigned_at->startOfDay()->diffInDays(now()->startOfDay());
            $window = intdiv($waiting, $days);

            if ($window < 1 || $window > $maxReminders) {
                continue;
            }

            $nudged++;

            $buyer = $requisition->assignedBuyer;

            if (! $buyer) {
                continue;
            }

            $sentThis = $this->send(
                NotificationSetting::REQUISITION_STALLED,
                $buyer,
                new RequisitionStalledMail($requisition, $buyer, $waiting, $requisition->reviewedBy),
                $requisition,
                'stall:'.$window,
            );

            if ($sentThis) {
                $sent++;
            }
        }

        return ['requisitions' => $nudged, 'sent' => $sent];
    }

    /**
     * Rounds whose responses are due shortly, and rounds already past due.
     *
     * Once each, per round rather than per person, using the two stamps — the
     * same mechanism `tasks.overdue_notified_at` uses. Both stamps are cleared
     * when `responses_due_at` moves, so a round whose deadline is pushed can
     * warn again later.
     *
     * @return array{due:int, overdue:int, sent:int}
     */
    public function sendDueWarnings(): array
    {
        $result = ['due' => 0, 'overdue' => 0, 'sent' => 0];

        // Open means the vendors have been asked and the round is not yet
        // awarded: a draft has nobody to chase, and an awarded round's
        // deadline no longer matters.
        $openStatuses = ['sent', 'comparing', 'negotiating'];

        if (NotificationSetting::enabled(NotificationSetting::QUOTATION_DUE_SOON)) {
            $lead = NotificationSetting::dueLeadDays();

            $approaching = Quotation::query()
                ->whereIn('status', $openStatuses)
                ->whereNull('due_notified_at')
                ->whereNotNull('responses_due_at')
                ->whereDate('responses_due_at', '>=', now()->toDateString())
                ->whereDate('responses_due_at', '<=', now()->addDays($lead)->toDateString())
                ->with(['assignedTo', 'assignees', 'project', 'jobSite', 'quotationVendors.vendor'])
                ->get();

            foreach ($approaching as $quotation) {
                $result['due']++;

                foreach ($quotation->workers() as $user) {
                    if ($this->send(
                        NotificationSetting::QUOTATION_DUE_SOON,
                        $user,
                        new QuotationDueSoonMail($quotation, $user, overdue: false),
                        $quotation,
                        // Keyed on the deadline itself. The stamp stops a
                        // second run today; this is what lets a round warn
                        // again after its date is pushed, which the stamp
                        // alone cannot do — the log would otherwise refuse
                        // every later warning about the same round.
                        'due:'.$quotation->responses_due_at->toDateString(),
                    )) {
                        $result['sent']++;
                    }
                }

                // Stamped whether or not anybody could be mailed, so a round
                // with no owner does not retry every morning forever.
                $quotation->forceFill(['due_notified_at' => now()])->save();
            }
        }

        if (NotificationSetting::enabled(NotificationSetting::QUOTATION_OVERDUE)) {
            $overdue = Quotation::query()
                ->whereIn('status', $openStatuses)
                ->whereNull('overdue_notified_at')
                ->whereNotNull('responses_due_at')
                ->whereDate('responses_due_at', '<', now()->toDateString())
                ->with(['assignedTo', 'assignees', 'project', 'jobSite', 'quotationVendors.vendor'])
                ->get();

            foreach ($overdue as $quotation) {
                $result['overdue']++;

                foreach ($quotation->workers() as $user) {
                    if ($this->send(
                        NotificationSetting::QUOTATION_OVERDUE,
                        $user,
                        new QuotationDueSoonMail($quotation, $user, overdue: true),
                        $quotation,
                        'overdue:'.$quotation->responses_due_at->toDateString(),
                    )) {
                        $result['sent']++;
                    }
                }

                $quotation->forceFill(['overdue_notified_at' => now()])->save();
            }
        }

        return $result;
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Queue the sending until after the response.
     *
     * The app sends mail synchronously and cannot assume a queue worker is
     * running, so this keeps the person who pressed Approve from waiting on
     * SMTP without needing one.
     *
     * @param  Collection<int, User>  $people
     */
    protected function sendAfterResponse(
        string $key,
        ?Model $subject,
        Collection $people,
        callable $mailFor,
        ?string $window = null,
    ): void {
        $people = $people->filter()->unique('id')->values();

        if ($people->isEmpty()) {
            return;
        }

        $deliver = function () use ($key, $subject, $people, $mailFor, $window) {
            foreach ($people as $user) {
                $this->send($key, $user, $mailFor($user), $subject, $window);
            }
        };

        // There is no response to be after on the console, and a terminating
        // callback there would run long after the command reported success —
        // or not at all.
        if (app()->runningInConsole()) {
            $deliver();

            return;
        }

        dispatch($deliver)->afterResponse();
    }

    /** One mail to one person, if they should get it and have not already. */
    public function send(
        string $key,
        User $user,
        Mailable $mail,
        ?Model $subject = null,
        ?string $window = null,
    ): bool {
        if (! $user->email || ! $user->isActive() || ! $user->wantsNotification($key)) {
            return false;
        }

        $already = NotificationLogEntry::query()
            ->where('user_id', $user->id)
            ->where('type', $key)
            ->when($subject, fn (Builder $q) => $q
                ->where('notifiable_type', $subject::class)
                ->where('notifiable_id', $subject->getKey()))
            ->when($window, fn (Builder $q) => $q->whereJsonContains('meta->window', $window))
            ->whereNotNull('sent_at')
            ->exists();

        if ($already) {
            return false;
        }

        $record = NotificationLogEntry::create([
            'notifiable_type' => $subject ? $subject::class : null,
            'notifiable_id' => $subject?->getKey(),
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

            Log::warning('Procurement notification could not be sent', [
                'type' => $key,
                'user' => $user->id,
                'subject' => $subject ? $subject::class.':'.$subject->getKey() : null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
