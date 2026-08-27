<?php

namespace App\Livewire\Approval;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\SignsAndDistributes;
use App\Models\Approval;
use App\Models\Collaboration\ResponseCode;
use App\Models\FileUpload;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * One approval, in full: every round of it, who was asked, and what came back.
 *
 * The revision history is the substance of this page rather than a footnote.
 * "This was rejected in revision 0 for the rejunte, resubmitted, and approved
 * with comments in revision 1" is the sentence somebody needs three months
 * later, and it cannot be reconstructed from the current state alone.
 *
 * **Every action guards itself.** The buttons are hidden from people who may
 * not use them, but a hidden button is not protection — each method below is
 * guarded against *this* approval's own scope.
 */
class ApprovalShow extends Component
{
    use AuthorizesAbility, SignsAndDistributes;

    public Approval $approval;

    /** Submit form: reviewers for the next round. */
    public array $reviewerRows = [];

    /** Respond form. */
    public ?string $responseCodeId = null;

    public string $responseComments = '';

    public function mount(Approval $approval): void
    {
        $this->authorizeAbility('approvals.view', $approval);

        $this->approval = $approval;

        $approval->logView();

        $this->resetReviewerRows();
    }

    protected function scope(): Project|JobSite
    {
        return $this->approval->jobSite ?? $this->approval->project;
    }

    protected function signableDocument(): Approval
    {
        return $this->approval;
    }

    protected function areaKey(): string
    {
        return 'approvals';
    }

    protected function resetReviewerRows(): void
    {
        $this->reviewerRows = [['user_id' => '', 'sequence' => 1, 'role' => '']];
    }

    /*
    |---------------------------------------------------------------------------
    | What this person may do
    |---------------------------------------------------------------------------
    */

    public function getCanSubmitProperty(): bool
    {
        return ! $this->approval->isClosed()
            && $this->approval->openRevision() === null
            && $this->allowsAbility('approvals.submit', $this->scope());
    }

    /**
     * Whether the respond form should be offered.
     *
     * Two conditions, and both matter: the grant, and actually being the
     * person this revision is waiting on. Somebody with `approvals.respond`
     * across the project is still not a reviewer of *this* round.
     */
    public function getCanRespondProperty(): bool
    {
        $revision = $this->approval->openRevision();

        return $revision !== null
            && $revision->load('reviewers')->isWaitingOn(auth()->user())
            && $this->allowsAbility('approvals.respond', $this->scope());
    }

    public function getCanEditProperty(): bool
    {
        return $this->allowsAbility('approvals.edit', $this->scope());
    }

    /*
    |---------------------------------------------------------------------------
    | Reviewer rows on the submit form
    |---------------------------------------------------------------------------
    */

    public function addReviewerRow(): void
    {
        $this->reviewerRows[] = [
            'user_id' => '',
            // Default to the next step in the chain, which is what somebody
            // adding a second reviewer usually means. Set it equal to make
            // them review together.
            'sequence' => count($this->reviewerRows) + 1,
            'role' => '',
        ];
    }

    public function removeReviewerRow(int $index): void
    {
        unset($this->reviewerRows[$index]);

        $this->reviewerRows = array_values($this->reviewerRows);

        if ($this->reviewerRows === []) {
            $this->resetReviewerRows();
        }
    }

    /** Ask the previous round's reviewers again — the usual case on a resubmit. */
    public function reuseLastReviewers(): void
    {
        $last = $this->approval->revisions()->with('reviewers')->latest('id')->first();

        if (! $last || $last->reviewers->isEmpty()) {
            return;
        }

        $this->reviewerRows = $last->reviewers
            ->map(fn ($reviewer) => [
                'user_id' => (string) $reviewer->user_id,
                'sequence' => $reviewer->sequence,
                'role' => $reviewer->role ?? '',
            ])
            ->values()
            ->all();
    }

    /*
    |---------------------------------------------------------------------------
    | Actions
    |---------------------------------------------------------------------------
    */

    public function submitRevision(): void
    {
        $this->authorizeAbility('approvals.submit', $this->scope());

        $this->validate([
            'reviewerRows.*.user_id' => 'nullable|integer|exists:users,id',
            'reviewerRows.*.sequence' => 'required|integer|min:1|max:99',
        ]);

        $assignable = array_keys($this->assignableUsers());

        $reviewers = collect($this->reviewerRows)
            ->filter(fn (array $row) => $row['user_id'] !== '')
            // A reviewer id came from the browser: it has to be somebody on
            // this project, not any user whose id was guessed.
            ->filter(fn (array $row) => in_array((int) $row['user_id'], $assignable, true))
            ->map(fn (array $row) => [
                'user_id' => (int) $row['user_id'],
                'sequence' => (int) $row['sequence'],
                'role' => $row['role'] !== '' ? $row['role'] : null,
            ])
            ->values()
            ->all();

        // The model refuses an empty list with a sentence of its own.
        $this->approval->submit($reviewers, auth()->user());

        $this->approval->refresh();
        $this->resetReviewerRows();

        $this->dispatch('close-modal', 'approval-submit');
        session()->flash('approval_message', __('collaboration.message.revision_submitted', [
            'revision' => $this->approval->current_revision,
        ]));
    }

    public function recordResponse(): void
    {
        $this->authorizeAbility('approvals.respond', $this->scope());

        $this->validate([
            'responseCodeId' => 'required|integer',
            'responseComments' => 'nullable|string|max:20000',
        ]);

        // The code must be one actually on offer here — not any row id.
        $code = ResponseCode::offered('approval', $this->approval->project_id)
            ->firstWhere('id', (int) $this->responseCodeId);

        abort_unless($code instanceof ResponseCode, 422);

        // Whether this person is a reviewer of this round, and whether it is
        // their turn, is the model's to answer; it throws a sentence.
        $this->approval->recordResponse($code, auth()->user(), $this->responseComments ?: null);

        $this->approval->refresh();
        $this->reset(['responseCodeId', 'responseComments']);

        $this->dispatch('close-modal', 'approval-respond');
        session()->flash('approval_message', __('collaboration.message.response_recorded'));
    }

    /**
     * Serve one of this approval's files.
     *
     * Two checks, not one: that the file hangs on this approval or one of its
     * revisions, and that this person may read the approval. Without the
     * first, walking the ids from a page you are legitimately on fetches any
     * file in the system.
     */
    public function downloadFile(int $fileId, FileUploadService $files)
    {
        $this->authorizeAbility('approvals.view', $this->scope());

        $revisionIds = $this->approval->revisions()->pluck('id')->all();

        $file = FileUpload::query()
            ->where(function ($q) use ($revisionIds) {
                $q->where(fn ($q) => $q
                    ->where('attachable_type', Approval::class)
                    ->where('attachable_id', $this->approval->id));

                if ($revisionIds !== []) {
                    $q->orWhere(fn ($q) => $q
                        ->where('attachable_type', \App\Models\ApprovalRevision::class)
                        ->whereIn('attachable_id', $revisionIds));
                }
            })
            ->find($fileId);

        // A 404 response like every other refusal here, rather than a bare
        // model exception that only becomes one at the HTTP boundary.
        abort_unless($file, 404);

        abort_unless($file->isAvailable(), 404);

        if ($url = $files->temporaryUrl($file)) {
            return redirect()->away($url);
        }

        return response()->download(
            Storage::disk($file->disk)->path($file->object_key),
            $file->original_name,
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Rendering
    |---------------------------------------------------------------------------
    */

    /**
     * Who may be named as a reviewer.
     *
     * The people with a membership on this project or its job sites — never
     * every user in the company, which on a guest's screen would be a staff
     * directory.
     *
     * @return array<int, string> id => name
     */
    protected function assignableUsers(): array
    {
        return Membership::query()
            ->active()
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('scopeable_type', Project::class)
                    ->where('scopeable_id', $this->approval->project_id));

                $q->orWhere(fn ($q) => $q
                    ->where('scopeable_type', JobSite::class)
                    ->whereIn('scopeable_id', $this->approval->project->jobSites()->pluck('id')));
            })
            ->with('user:id,name')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function render()
    {
        $this->approval->loadMissing([
            'project:id,project_name',
            'jobSite:id,job_site_name',
            'ballInCourt:id,name',
            'createdBy:id,name',
            'certificate',
            'budgetItem:id,code,name',
            'catalogItem:id,name,sku',
            'supplier:id,name',
            'package:id,number,title',
        ]);

        return view('livewire.approval.approval-show', [
            'revisions' => $this->approval->revisions()
                ->with([
                    'reviewers.user:id,name,company_name',
                    'responseCode',
                    'submittedBy:id,name',
                    'respondedBy:id,name,company_name',
                ])
                ->get()
                ->reverse()
                ->values(),
            'activity' => $this->approval->activity()->with('user:id,name')->limit(100)->get(),
            'distribution' => $this->approval->distribution()->with('user:id,name,email')->get(),
            'files' => $this->approval->availableFiles()->get(),
            'responseCodes' => ResponseCode::offered('approval', $this->approval->project_id),
            'assignableUsers' => $this->assignableUsers(),
            'signatures' => $this->approval->signatures()->with('user:id,name,company_name')->get(),
        ])->layout('components.layouts.app');
    }
}
