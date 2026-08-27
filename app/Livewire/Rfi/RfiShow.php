<?php

namespace App\Livewire\Rfi;

use App\Livewire\Concerns\AuthorizesAbility;
use Livewire\WithFileUploads;
use App\Livewire\Concerns\SignsAndDistributes;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\FileUpload;
use App\Models\Rfi;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * One RFI, in full.
 *
 * Everything the record knows is on this page — the question, the answer, both
 * markets' reference fields, the impact flags, the distribution list, the
 * attachments, the audit facts and the whole history including who merely
 * opened it (CLAUDE.md, design standard 2).
 *
 * **Every action here guards itself.** The buttons are hidden from people who
 * may not use them, but a hidden button is not protection: the `wire:click`
 * behind it is a public endpoint, and each method below is guarded against
 * *this* RFI's own scope rather than against whatever the browser sent.
 */
class RfiShow extends Component
{
    use AuthorizesAbility, SignsAndDistributes, WithFileUploads;

    public Rfi $rfi;

    /** Answer form. */
    #[Validate('required|string|min:2|max:20000')]
    public string $answerText = '';

    /** Files attached with a reply — the marked-up prancha, usually. */
    public array $replyUploads = [];

    /** Editing one reply. */
    public ?int $editingReplyId = null;

    public string $editingReplyBody = '';

    /**
     * Why a closed SI's answer is being changed.
     *
     * Required once it is closed: the record has been sent out and quoted, so
     * a correction has to be explicable. While it is still open, editing your
     * own words needs no ceremony.
     */
    public string $editingReplyReason = '';

    /** Ball-in-court form. */
    public ?string $passToUserId = null;

    public ?string $passToDueDate = null;

    public function mount(Rfi $rfi): void
    {
        // Guarded against the RFI's own project or job site — never against a
        // scope the request supplied.
        $this->authorizeAbility('rfis.view', $rfi);

        $this->rfi = $rfi;

        // Recorded deliberately, guests included: "sent on the 4th, opened on
        // the 5th" is a sentence this module has to be able to say.
        $rfi->logView();
    }

    /** What guards are answered against: the narrower of the two. */
    protected function scope(): mixed
    {
        return $this->rfi->jobSite ?? $this->rfi->project;
    }

    protected function signableDocument(): Rfi
    {
        return $this->rfi;
    }

    protected function areaKey(): string
    {
        return 'rfis';
    }

    /*
    |---------------------------------------------------------------------------
    | What this person may do
    |---------------------------------------------------------------------------
    | Read by the view to decide what to render. Never a substitute for the
    | guards in the actions themselves.
    */

    public function getCanSeeImpactProperty(): bool
    {
        return $this->allowsAbility('rfis.view_impact', $this->scope());
    }

    public function getCanAnswerProperty(): bool
    {
        return $this->rfi->answerIsEditable()
            && $this->allowsAbility('rfis.answer', $this->scope());
    }

    public function getCanCloseProperty(): bool
    {
        return $this->rfi->isAnswered()
            && ! $this->rfi->isClosed()
            && $this->allowsAbility('rfis.close', $this->scope());
    }

    public function getCanReopenProperty(): bool
    {
        return $this->rfi->isClosed()
            && $this->allowsAbility('rfis.close', $this->scope());
    }

    public function getCanEditProperty(): bool
    {
        return $this->allowsAbility('rfis.edit', $this->scope());
    }

    /**
     * Whether this person may say which reply counts.
     *
     * The same authority as closing: deciding which answer the work is built
     * to is the same decision as declaring the question settled.
     */
    public function getCanChooseReplyProperty(): bool
    {
        return ! $this->rfi->isClosed()
            && $this->allowsAbility('rfis.close', $this->scope());
    }

    /**
     * Whether this person may correct the wording of a given reply.
     *
     * Your own words while the SI is open, with `rfis.answer`. Somebody
     * else's, or anything at all once it is closed, needs `rfis.revise` —
     * rewriting what another person said is the narrowest thing here.
     */
    public function canEditReply(\App\Models\RfiReply $reply): bool
    {
        if ($this->allowsAbility('rfis.revise', $this->scope())) {
            return true;
        }

        return ! $this->rfi->isClosed()
            && $reply->replied_by_id === auth()->id()
            && $this->allowsAbility('rfis.answer', $this->scope());
    }

    /*
    |---------------------------------------------------------------------------
    | Actions
    |---------------------------------------------------------------------------
    */

    public function recordAnswer(): void
    {
        $this->authorizeAbility('rfis.answer', $this->scope());

        // The freeze belongs to the model, but saying so here turns a 500 into
        // a sentence the reader can act on.
        if (! $this->rfi->answerIsEditable()) {
            throw ValidationException::withMessages([
                'answerText' => __('collaboration.help.rfi_closed_reopen_record_correction'),
            ]);
        }

        $this->validateOnly('answerText');
        $this->validate(['replyUploads.*' => 'nullable|file|max:'.(int) (app(FileUploadService::class)->maxBytes() / 1024)]);

        $reply = $this->rfi->addReply($this->answerText, auth()->user());

        $this->storeReplyUploads($reply);

        $this->reset(['answerText', 'replyUploads']);
        $this->rfi->refresh();

        $this->dispatch('close-modal', 'rfi-answer');
        session()->flash('rfi_message', __('collaboration.message.answer_recorded'));
    }

    /** Say which reply the work is built to. */
    public function chooseReply(int $replyId): void
    {
        $this->authorizeAbility('rfis.close', $this->scope());

        // The id came from the browser: it has to be a reply on this SI.
        // `abort` rather than `findOrFail`, so the answer is a 404 response
        // like every other refusal here and not a bare model exception.
        $reply = $this->rfi->replies()->find($replyId);
        abort_unless($reply, 404);

        $this->rfi->markReplyValid($reply, auth()->user());
        $this->rfi->refresh();

        session()->flash('rfi_message', __('collaboration.message.valid_reply_set'));
    }

    public function startEditingReply(int $replyId): void
    {
        $reply = $this->rfi->replies()->find($replyId);

        abort_unless($reply, 404);
        abort_unless($this->canEditReply($reply), 403, __('You do not have permission to do that.'));

        $this->editingReplyId = $reply->id;
        $this->editingReplyBody = $reply->body;
        $this->editingReplyReason = '';
    }

    public function cancelEditingReply(): void
    {
        $this->reset(['editingReplyId', 'editingReplyBody', 'editingReplyReason']);
    }

    public function saveReplyEdit(): void
    {
        $reply = $this->rfi->replies()->find($this->editingReplyId);

        abort_unless($reply, 404);

        // Guarded on the reply itself, not on the form having been opened.
        abort_unless($this->canEditReply($reply), 403, __('You do not have permission to do that.'));

        $this->validate([
            'editingReplyBody' => 'required|string|min:2|max:20000',
            // A closed SI has been sent out and quoted; changing what it says
            // has to be explicable afterwards.
            'editingReplyReason' => ($this->rfi->isClosed() ? 'required|' : 'nullable|').'string|max:500',
        ]);

        $this->rfi->editReply(
            $reply,
            $this->editingReplyBody,
            auth()->user(),
            $this->editingReplyReason ?: null,
        );

        $this->reset(['editingReplyId', 'editingReplyBody', 'editingReplyReason']);
        $this->rfi->refresh();

        session()->flash('rfi_message', __('collaboration.message.reply_updated'));
    }

    public function close(): void
    {
        $this->authorizeAbility('rfis.close', $this->scope());

        if (! $this->rfi->isAnswered()) {
            throw ValidationException::withMessages([
                'answerText' => __('collaboration.help.rfi_cannot_closed_before_been'),
            ]);
        }

        $this->rfi->close();
        $this->rfi->refresh();

        session()->flash('rfi_message', __('collaboration.message.rfi_closed'));
    }

    public function reopen(): void
    {
        $this->authorizeAbility('rfis.close', $this->scope());

        // The button is only rendered on a closed RFI, but the `wire:click`
        // behind it is a public endpoint. Reopening a draft would set it to
        // "answered" with no answer — a state no transition should produce,
        // and one `close()` then refuses, leaving the record stuck.
        if (! $this->rfi->isClosed()) {
            throw ValidationException::withMessages([
                'answerText' => __('collaboration.help.rfi_closed_there_nothing_reopen'),
            ]);
        }

        $this->rfi->reopen();
        $this->rfi->refresh();

        session()->flash('rfi_message', __('collaboration.message.rfi_reopened'));
    }

    /** Hand the RFI to somebody, with the date they are expected by. */
    public function passBall(): void
    {
        $this->authorizeAbility('rfis.edit', $this->scope());

        $this->validate([
            'passToUserId' => 'nullable|integer|exists:users,id',
            'passToDueDate' => 'nullable|date',
        ]);

        // `exists` proves the person exists, not that they belong on this
        // project. Without this, any user id could be made ball-in-court —
        // and their name then goes out on the distributed PDF.
        if ($this->passToUserId
            && ! in_array((int) $this->passToUserId, array_keys($this->assignableUsers()), true)) {
            abort(404);
        }

        $this->rfi->passTo(
            $this->passToUserId ? User::find($this->passToUserId) : null,
            $this->passToDueDate,
        );

        $this->rfi->logActivity(ActivityLogEntry::UPDATED, [
            'ball_in_court' => $this->rfi->ballInCourt?->name,
        ]);

        $this->rfi->refresh();

        $this->dispatch('close-modal', 'rfi-ball');
        session()->flash('rfi_message', __('collaboration.message.ball_court_updated'));
    }

    public function removeReplyUpload(int $index): void
    {
        unset($this->replyUploads[$index]);

        $this->replyUploads = array_values($this->replyUploads);
    }

    /**
     * Put the files chosen with a reply against that reply.
     *
     * Attached in the same step as the words, because a projetista answering
     * with a marked-up prancha has both in front of them at once. A file the
     * allow-list refuses is named rather than dropped in silence.
     */
    protected function storeReplyUploads(\App\Models\RfiReply $reply): void
    {
        if ($this->replyUploads === []) {
            return;
        }

        $service = app(FileUploadService::class);
        $refused = [];

        foreach ($this->replyUploads as $upload) {
            if (! $service->isAllowedFile($upload->getClientOriginalName(), $upload->getMimeType())) {
                $refused[] = $upload->getClientOriginalName();

                continue;
            }

            $begun = $service->begin(
                $reply,
                $upload->getClientOriginalName(),
                $upload->getSize(),
                $upload->getMimeType(),
            );

            $service->storeLocal(\App\Models\FileUpload::findOrFail($begun['version_id']), $upload);
        }

        if ($refused !== []) {
            session()->flash('rfi_upload_refused', __(
                'These files were not attached because their type is not allowed: :files',
                ['files' => implode(', ', $refused)],
            ));
        }
    }

    /**
     * Serve one of this RFI's attachments.
     *
     * The id came from the browser, so two things are checked and not one:
     * that the file belongs to *this* RFI, and that this person may read this
     * RFI. Without the first, walking the ids fetches any file in the system
     * from a page the reader is legitimately on.
     */
    public function downloadFile(int $fileId, FileUploadService $files)
    {
        // Either the SI's own files or those of one of its replies — and
        // nothing else, whatever id the browser sent.
        $replyIds = $this->rfi->replies()->pluck('id')->all();

        $file = FileUpload::query()
            ->where(function ($q) use ($replyIds) {
                $q->where(fn ($q) => $q
                    ->where('attachable_type', Rfi::class)
                    ->where('attachable_id', $this->rfi->id));

                if ($replyIds !== []) {
                    $q->orWhere(fn ($q) => $q
                        ->where('attachable_type', \App\Models\RfiReply::class)
                        ->whereIn('attachable_id', $replyIds));
                }
            })
            ->find($fileId);

        // A 404 response like every other refusal here, rather than a bare
        // model exception that only becomes one at the HTTP boundary.
        abort_unless($file, 404);

        $this->authorizeAbility('rfis.view', $this->scope());

        abort_unless($file->isAvailable(), 404);

        if ($url = $files->temporaryUrl($file)) {
            return redirect()->away($url);
        }

        // No cloud storage on this install: stream it instead.
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

    public function render()
    {
        $this->rfi->loadMissing([
            'project:id,project_name',
            'jobSite:id,job_site_name',
            'ballInCourt:id,name,company_name',
            'createdBy:id,name',
            'answeredBy:id,name,company_name',
            'changeOrder:id,co_number',
        ]);

        return view('livewire.rfi.rfi-show', [
            // The history, newest first, with the actor eager-loaded so a long
            // trail is one query rather than one per line.
            'activity' => $this->rfi->activity()->with('user:id,name')->limit(100)->get(),
            'distribution' => $this->rfi->distribution()->with('user:id,name,email')->get(),
            'files' => $this->rfi->availableFiles()->get(),
            // Only people who can actually be handed an RFI on this project.
            'assignableUsers' => $this->assignableUsers(),
            'signatures' => $this->rfi->signatures()->with('user:id,name,company_name')->get(),
            'corrections' => $this->rfi->correctionCount(),
            'replies' => $this->rfi->replies()
                ->with('repliedBy:id,name,company_name', 'editedBy:id,name', 'availableFiles')
                ->get(),
        ])->layout('components.layouts.app');
    }

    /**
     * Who this RFI can be handed to.
     *
     * The people with a membership on its project or job site, plus whoever
     * already holds it — never every user in the company, which on a guest's
     * screen would be a staff directory.
     *
     * @return array<int, string> id => name
     */
    protected function assignableUsers(): array
    {
        $memberships = \App\Models\Membership::query()
            ->active()
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('scopeable_type', \App\Models\Project::class)
                    ->where('scopeable_id', $this->rfi->project_id));

                if ($this->rfi->job_site_id) {
                    $q->orWhere(fn ($q) => $q
                        ->where('scopeable_type', \App\Models\JobSite::class)
                        ->where('scopeable_id', $this->rfi->job_site_id));
                }
            })
            ->with('user:id,name')
            ->get()
            ->pluck('user')
            ->filter();

        if ($this->rfi->ballInCourt) {
            $memberships->push($this->rfi->ballInCourt);
        }

        return $memberships
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
