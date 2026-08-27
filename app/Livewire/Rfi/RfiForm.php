<?php

namespace App\Livewire\Rfi;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Collaboration\DistributionEntry;
use App\Models\FileUpload;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Rfi;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Raise an RFI, or edit one.
 *
 * A full page rather than a dialog: the form carries a dozen fields, a
 * repeating distribution list and its attachments, which is the line the
 * modal-size rule draws.
 *
 * **Files are accepted in the same step as the record.** Save-then-reopen-to-
 * attach is the exact thing this codebase was called out for once already; a
 * person raising an RFI has the drawing in front of them at that moment and
 * nowhere to put it. They are stored after the insert, because a file needs a
 * record to hang from, but from the reader's side it is one action.
 */
class RfiForm extends Component
{
    use AuthorizesAbility, WithFileUploads;

    public ?Rfi $rfi = null;

    public Project $project;

    public ?JobSite $jobSite = null;

    /** Fields. */
    public ?string $job_site_id = null;

    public string $subject = '';

    public string $question = '';

    public ?string $discipline = null;

    public ?string $spec_section = null;

    public ?string $drawing_ref = null;

    public string $priority = 'normal';

    public ?string $ball_in_court_id = null;

    public ?string $due_date = null;

    public bool $cost_impact = false;

    public bool $schedule_impact = false;

    public ?string $schedule_impact_days = null;

    public ?string $cost_impact_amount = null;

    /** Repeating rows: who gets a copy. */
    public array $distributionRows = [];

    /** Files chosen before the record exists. */
    public array $uploads = [];

    /**
     * Three routes reach this: create under a project, create under a job
     * site, and edit.
     *
     * `exists` rather than a null check on each: a class-typed parameter the
     * route did not fill is resolved from the container, so an unbound model
     * arrives as an empty instance rather than as null. Testing truthiness
     * would read "editing" on the create route.
     */
    public function mount(?Project $project = null, ?JobSite $jobSite = null, ?Rfi $rfi = null): void
    {
        $rfi = $rfi?->exists ? $rfi : null;
        $jobSite = $jobSite?->exists ? $jobSite : null;
        $project = $project?->exists ? $project : null;

        if ($rfi) {
            // Editing: the scope comes from the record, never from the URL.
            $this->authorizeAbility('rfis.edit', $rfi);

            $this->rfi = $rfi;
            $this->project = $rfi->project;
            $this->jobSite = $rfi->jobSite;

            $this->fillFrom($rfi);
        } else {
            $this->jobSite = $jobSite;
            $this->project = $jobSite?->project ?? $project;

            abort_unless($this->project, 404);

            $this->authorizeAbility('rfis.create', $this->jobSite ?? $this->project);

            $this->job_site_id = $jobSite?->id ? (string) $jobSite->id : null;
        }

        if ($this->distributionRows === []) {
            $this->addDistributionRow();
        }
    }

    /**
     * Read the record into the form.
     *
     * Null-coalesced throughout: the columns the database defaults are not on
     * a model instance that has not been read back, so a caller handing this
     * a freshly created object would otherwise assign null to a typed string.
     */
    protected function fillFrom(Rfi $rfi): void
    {
        $this->job_site_id = $rfi->job_site_id ? (string) $rfi->job_site_id : null;
        $this->subject = $rfi->subject ?? '';
        $this->question = $rfi->question ?? '';
        $this->discipline = $rfi->discipline;
        $this->spec_section = $rfi->spec_section;
        $this->drawing_ref = $rfi->drawing_ref;
        $this->priority = $rfi->priority ?: 'normal';
        $this->ball_in_court_id = $rfi->ball_in_court_id ? (string) $rfi->ball_in_court_id : null;
        $this->due_date = $rfi->due_date?->toDateString();
        $this->cost_impact = (bool) $rfi->cost_impact;
        $this->schedule_impact = (bool) $rfi->schedule_impact;
        $this->schedule_impact_days = $rfi->schedule_impact_days ? (string) $rfi->schedule_impact_days : null;
        $this->cost_impact_amount = $rfi->cost_impact_amount !== null ? (string) $rfi->cost_impact_amount : null;

        $this->distributionRows = $rfi->distribution->map(fn (DistributionEntry $entry) => [
            'user_id' => $entry->user_id ? (string) $entry->user_id : '',
            'external_name' => $entry->external_name ?? '',
            'external_email' => $entry->external_email ?? '',
            'role' => $entry->role ?? '',
        ])->all();
    }

    /** What guards are answered against: the narrower of the two. */
    protected function scope(): Project|JobSite
    {
        return $this->jobSite ?? $this->project;
    }

    public function getIsEditingProperty(): bool
    {
        return $this->rfi !== null;
    }

    public function getCanSeeImpactProperty(): bool
    {
        return $this->allowsAbility('rfis.view_impact', $this->scope());
    }

    /*
    |---------------------------------------------------------------------------
    | The distribution list
    |---------------------------------------------------------------------------
    */

    public function addDistributionRow(): void
    {
        $this->distributionRows[] = ['user_id' => '', 'external_name' => '', 'external_email' => '', 'role' => ''];
    }

    public function removeDistributionRow(int $index): void
    {
        unset($this->distributionRows[$index]);

        $this->distributionRows = array_values($this->distributionRows);

        if ($this->distributionRows === []) {
            $this->addDistributionRow();
        }
    }

    /** Copy in everybody on the project — the shortcut people would repeat by hand. */
    public function addEveryoneOnProject(): void
    {
        $existing = collect($this->distributionRows)->pluck('user_id')->filter()->all();

        foreach ($this->assignableUsers() as $id => $name) {
            if (! in_array((string) $id, $existing, true)) {
                $this->distributionRows[] = [
                    'user_id' => (string) $id,
                    'external_name' => '',
                    'external_email' => '',
                    'role' => '',
                ];
            }
        }

        // Drop the blank row the list opens with, if it is still blank.
        $this->distributionRows = array_values(array_filter(
            $this->distributionRows,
            fn (array $row) => $row['user_id'] !== '' || $row['external_email'] !== '',
        ));

        if ($this->distributionRows === []) {
            $this->addDistributionRow();
        }
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);

        $this->uploads = array_values($this->uploads);
    }

    /*
    |---------------------------------------------------------------------------
    | Saving
    |---------------------------------------------------------------------------
    */

    protected function rules(): array
    {
        return [
            'job_site_id' => 'nullable|integer|exists:job_sites,id',
            'subject' => 'required|string|max:255',
            'question' => 'required|string|max:20000',
            'discipline' => 'nullable|string|max:60',
            'spec_section' => 'nullable|string|max:60',
            'drawing_ref' => 'nullable|string|max:120',
            'priority' => 'required|in:'.implode(',', Rfi::PRIORITIES),
            'ball_in_court_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
            'schedule_impact_days' => 'nullable|integer|min:1|max:9999',
            'cost_impact_amount' => 'nullable|numeric|min:-999999999|max:999999999',
            'distributionRows.*.user_id' => 'nullable|integer|exists:users,id',
            'distributionRows.*.external_email' => 'nullable|email|max:255',
            'distributionRows.*.external_name' => 'nullable|string|max:255',
            'uploads.*' => 'nullable|file|max:'.(int) (app(FileUploadService::class)->maxBytes() / 1024),
        ];
    }

    /**
     * Only the names the shared map does not already carry.
     *
     * `lang/pt_BR/validation.php` holds `due_date` as *vencimento*, and a
     * second opinion here would make one screen say something different from
     * every other (CLAUDE.md, pt_BR rules 5 and 6).
     */
    protected function validationAttributes(): array
    {
        return [
            'job_site_id' => __('job site'),
            'subject' => __('subject'),
            'question' => __('question'),
            'ball_in_court_id' => __('collaboration.field.ball_court'),
            'schedule_impact_days' => __('collaboration.field.schedule_impact_days'),
            'cost_impact_amount' => __('collaboration.field.estimated_cost_impact'),
        ];
    }

    public function save(): void
    {
        // Guarded before anything is read from the form.
        $this->authorizeAbility($this->isEditing ? 'rfis.edit' : 'rfis.create', $this->scope());

        $data = $this->validate();

        // A job site from the browser must belong to THIS project. Existing is
        // not the same as being allowed.
        if ($this->job_site_id) {
            $destination = JobSite::where('id', $this->job_site_id)
                ->where('project_id', $this->project->id)
                ->first();

            abort_unless($destination, 404);

            // And the *destination* is authorized too, not only the scope the
            // record sits in now. Otherwise somebody holding the grant on site
            // A could move a record to site B, where they hold nothing.
            $this->authorizeAbility('rfis.' . ($this->isEditing ? 'edit' : 'create'), $destination);
        }

        // The record freezes its own question and answer once it closes; say
        // so on the field the reader is looking at, rather than letting the
        // model throw under a key this form does not render.
        if ($this->isEditing && $this->rfi->isClosed()) {
            if ($this->subject !== $this->rfi->subject) {
                $this->addError('subject', __('collaboration.help.rfi_closed_subject_can_longer'));
            }

            if ($this->question !== $this->rfi->question) {
                $this->addError('question', __('collaboration.help.rfi_closed_question_can_longer'));
            }

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        $attributes = [
            'project_id' => $this->project->id,
            'job_site_id' => $this->job_site_id ?: null,
            'subject' => $this->subject,
            'question' => $this->question,
            'discipline' => $this->discipline ?: null,
            'spec_section' => $this->spec_section ?: null,
            'drawing_ref' => $this->drawing_ref ?: null,
            'priority' => $this->priority,
            'ball_in_court_id' => $this->ball_in_court_id ?: null,
            'due_date' => $this->due_date ?: null,
        ];

        // Impact is only writable by somebody who may see it — otherwise
        // saving the form would silently clear flags they were never shown.
        if ($this->canSeeImpact) {
            $attributes += [
                'cost_impact' => $this->cost_impact,
                // Clearing the flag clears the figure with it: an amount left
                // behind on an RFI that no longer claims a cost impact would
                // still be counted by anything that reads the column.
                'cost_impact_amount' => $this->cost_impact ? ($this->cost_impact_amount ?: null) : null,
                'schedule_impact' => $this->schedule_impact,
                'schedule_impact_days' => $this->schedule_impact ? ($this->schedule_impact_days ?: null) : null,
            ];
        }

        $rfi = DB::transaction(function () use ($attributes) {
            if ($this->isEditing) {
                $this->rfi->update($attributes);
                $rfi = $this->rfi;
            } else {
                $rfi = Rfi::create($attributes + [
                    'status' => Rfi::OPEN,
                    'created_by_id' => auth()->id(),
                ]);
            }

            $rfi->syncDistribution($this->distributionEntries());

            return $rfi;
        });

        $this->storeUploads($rfi);

        $rfi->logActivity($this->isEditing ? ActivityLogEntry::UPDATED : ActivityLogEntry::CREATED);

        session()->flash('rfi_message', $this->isEditing
            ? __('collaboration.message.rfi_updated')
            : __('collaboration.message.rfi_raised', ['number' => $rfi->number]));

        $this->redirectRoute('rfis.show', $rfi, navigate: true);
    }

    /**
     * The distribution list, with every user id checked against this project.
     *
     * `exists:users,id` proves a person exists, not that they belong here. A
     * crafted payload could otherwise name anybody in the system, and the
     * distributor would post them the document for a project they hold no
     * membership on — the "never act on an id that came from the browser"
     * rule, which `ApprovalShow::submitRevision()` already applies to
     * reviewers.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function distributionEntries(): array
    {
        $assignable = array_keys($this->assignableUsers());

        return collect($this->distributionRows)
            ->reject(fn (array $row) => $row['user_id'] !== ''
                && ! in_array((int) $row['user_id'], $assignable, true))
            ->map(fn (array $row) => [
                'user_id' => $row['user_id'] !== '' ? (int) $row['user_id'] : null,
                'external_name' => $row['external_name'] !== '' ? $row['external_name'] : null,
                'external_email' => $row['external_email'] !== '' ? $row['external_email'] : null,
                'role' => $row['role'] !== '' ? $row['role'] : null,
            ])
            ->all();
    }

    /**
     * Put the chosen files against the record now that it has an id.
     *
     * Streamed server-side, so this works whether the install stores on R2 or
     * on a local disk — the same path the uploader uses when the browser
     * cannot talk to storage directly.
     */
    protected function storeUploads(Rfi $rfi): void
    {
        if ($this->uploads === []) {
            return;
        }

        $service = app(FileUploadService::class);
        $refused = [];

        foreach ($this->uploads as $upload) {
            // Skipping quietly is how somebody attaches a drawing, is told the
            // record was saved, and finds out weeks later that it never
            // arrived. Say which files were refused.
            if (! $service->isAllowedFile($upload->getClientOriginalName(), $upload->getMimeType())) {
                $refused[] = $upload->getClientOriginalName();

                continue;
            }

            $begun = $service->begin(
                $rfi,
                $upload->getClientOriginalName(),
                $upload->getSize(),
                $upload->getMimeType(),
            );

            $service->storeLocal(FileUpload::findOrFail($begun['version_id']), $upload);
        }

        $this->uploads = [];

        if ($refused !== []) {
            session()->flash('rfi_upload_refused', __('collaboration.label.these_files_attached_because',
                ['files' => implode(', ', $refused)],
            ));
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Rendering
    |---------------------------------------------------------------------------
    */

    /**
     * Who this RFI can be addressed to, or copied to.
     *
     * The people with a membership here — never every user in the company,
     * which on a guest's screen would be a staff directory.
     *
     * @return array<int, string> id => name
     */
    /**
     * The assignable people with their addresses, for the distribution rows.
     *
     * Keyed by id as a string, because that is what the select binds.
     *
     * @return array<string, array{name: string, email: string}>
     */
    protected function assignableUserDetails(): array
    {
        return Membership::query()
            ->active()
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('scopeable_type', Project::class)
                    ->where('scopeable_id', $this->project->id));

                $q->orWhere(fn ($q) => $q
                    ->where('scopeable_type', JobSite::class)
                    ->whereIn('scopeable_id', $this->project->jobSites()->pluck('id')));
            })
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($user) => [(string) $user->id => [
                'name' => $user->name,
                'email' => $user->email,
            ]])
            ->all();
    }

    protected function assignableUsers(): array
    {
        return Membership::query()
            ->active()
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('scopeable_type', Project::class)
                    ->where('scopeable_id', $this->project->id));

                $q->orWhere(fn ($q) => $q
                    ->where('scopeable_type', JobSite::class)
                    ->whereIn('scopeable_id', $this->project->jobSites()->pluck('id')));
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
        return view('livewire.rfi.rfi-form', [
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(['id', 'job_site_name']),
            'assignableUsers' => $this->assignableUsers(),
            'chosenUsers' => $this->assignableUserDetails(),
            'disciplines' => Rfi::disciplineOptions(),
            'roleOptions' => DistributionEntry::roleOptions(),
        ])->layout('components.layouts.app');
    }
}
