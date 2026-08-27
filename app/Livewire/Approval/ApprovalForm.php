<?php

namespace App\Livewire\Approval;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Approval;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\CatalogItem;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Collaboration\DistributionEntry;
use App\Models\FileUpload;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Raise an approval, or edit one.
 *
 * A full page: the form carries the approval's own fields, a repeating
 * distribution list, the certificate block that only one type uses, and its
 * attachments.
 *
 * Files are accepted in the same step as the record, as everywhere else in
 * this module — somebody raising an approval has the ficha técnica in front of
 * them at that moment.
 */
class ApprovalForm extends Component
{
    use AuthorizesAbility, WithFileUploads;

    public ?Approval $approval = null;

    public Project $project;

    public ?JobSite $jobSite = null;

    /** Fields. */
    public ?string $job_site_id = null;

    public string $title = '';

    public string $description = '';

    public string $type = Approval::TYPE_MATERIAL;

    public ?string $spec_section = null;

    public ?string $budget_item_id = null;

    public ?string $catalog_item_id = null;

    public ?string $supplier_id = null;

    public ?string $due_date = null;

    /** Certificate block — only for `laudo_certificado`. */
    public string $issuing_body = '';

    public ?string $certificate_number = null;

    public ?string $issued_at = null;

    public ?string $valid_until = null;

    public array $distributionRows = [];

    public array $uploads = [];

    public function mount(?Project $project = null, ?JobSite $jobSite = null, ?Approval $approval = null): void
    {
        // A class-typed parameter the route did not fill is resolved from the
        // container, so it arrives as an empty model rather than as null.
        $approval = $approval?->exists ? $approval : null;
        $jobSite = $jobSite?->exists ? $jobSite : null;
        $project = $project?->exists ? $project : null;

        if ($approval) {
            $this->authorizeAbility('approvals.edit', $approval);

            $this->approval = $approval;
            $this->project = $approval->project;
            $this->jobSite = $approval->jobSite;

            $this->fillFrom($approval);
        } else {
            $this->jobSite = $jobSite;
            $this->project = $jobSite?->project ?? $project;

            abort_unless($this->project, 404);

            $this->authorizeAbility('approvals.create', $this->jobSite ?? $this->project);

            $this->job_site_id = $jobSite?->id ? (string) $jobSite->id : null;
        }

        if ($this->distributionRows === []) {
            $this->addDistributionRow();
        }
    }

    /** Null-coalesced throughout: a model not read back carries no defaults. */
    protected function fillFrom(Approval $approval): void
    {
        $this->job_site_id = $approval->job_site_id ? (string) $approval->job_site_id : null;
        $this->title = $approval->title ?? '';
        $this->description = $approval->description ?? '';
        $this->type = $approval->type ?: Approval::TYPE_MATERIAL;
        $this->spec_section = $approval->spec_section;
        $this->budget_item_id = $approval->budget_item_id ? (string) $approval->budget_item_id : null;
        $this->catalog_item_id = $approval->catalog_item_id ? (string) $approval->catalog_item_id : null;
        $this->supplier_id = $approval->supplier_id ? (string) $approval->supplier_id : null;
        $this->due_date = $approval->due_date?->toDateString();

        if ($certificate = $approval->certificate) {
            $this->issuing_body = $certificate->issuing_body ?? '';
            $this->certificate_number = $certificate->certificate_number;
            $this->issued_at = $certificate->issued_at?->toDateString();
            $this->valid_until = $certificate->valid_until?->toDateString();
        }

        $this->distributionRows = $approval->distribution->map(fn (DistributionEntry $entry) => [
            'user_id' => $entry->user_id ? (string) $entry->user_id : '',
            'external_name' => $entry->external_name ?? '',
            'external_email' => $entry->external_email ?? '',
            'role' => $entry->role ?? '',
        ])->all();
    }

    protected function scope(): Project|JobSite
    {
        return $this->jobSite ?? $this->project;
    }

    public function getIsEditingProperty(): bool
    {
        return $this->approval !== null;
    }

    /** Whether the certificate block applies to what is being raised. */
    public function getIsCertificateProperty(): bool
    {
        return $this->type === Approval::TYPE_CERTIFICATE;
    }

    /*
    |---------------------------------------------------------------------------
    | Distribution rows
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'type' => 'required|in:'.implode(',', Approval::TYPES),
            'spec_section' => 'nullable|string|max:60',
            'budget_item_id' => 'nullable|integer|exists:budget_items,id',
            'catalog_item_id' => 'nullable|integer|exists:catalog_items,id',
            'supplier_id' => 'nullable|integer|exists:vendors,id',
            'due_date' => 'nullable|date',
            // Required only when the type actually has a certificate: the
            // issuing body is the one fact a laudo is useless without.
            'issuing_body' => 'required_if:type,'.Approval::TYPE_CERTIFICATE.'|nullable|string|max:255',
            'certificate_number' => 'nullable|string|max:120',
            'issued_at' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:issued_at',
            'distributionRows.*.user_id' => 'nullable|integer|exists:users,id',
            'distributionRows.*.external_email' => 'nullable|email|max:255',
            'distributionRows.*.external_name' => 'nullable|string|max:255',
            'uploads.*' => 'nullable|file|max:'.(int) (app(FileUploadService::class)->maxBytes() / 1024),
        ];
    }

    /**
     * Only the names the shared map does not already carry.
     *
     * `title`, `type` and `due_date` are all in `lang/pt_BR/validation.php`
     * already; repeating them here is either dead weight or, in `due_date`'s
     * case, a second opinion that makes this one screen say *data de
     * vencimento* where every other says *vencimento*.
     */
    protected function validationAttributes(): array
    {
        return [
            'job_site_id' => __('job site'),
            'budget_item_id' => __('collaboration.field.budget_line'),
            'catalog_item_id' => __('collaboration.field.catalog_item'),
            'supplier_id' => __('supplier'),
            'issuing_body' => __('collaboration.field.issuing_body'),
            'valid_until' => __('valid until'),
        ];
    }

    public function save(): void
    {
        $this->authorizeAbility($this->isEditing ? 'approvals.edit' : 'approvals.create', $this->scope());

        $this->validate();

        // Ids that came from the browser have to belong here. Existing is not
        // the same as belonging to this project.
        if ($this->job_site_id) {
            $destination = JobSite::where('id', $this->job_site_id)
                ->where('project_id', $this->project->id)
                ->first();

            abort_unless($destination, 404);

            // And the *destination* is authorized too, not only the scope the
            // record sits in now. Otherwise somebody holding the grant on site
            // A could move a record to site B, where they hold nothing.
            $this->authorizeAbility('approvals.' . ($this->isEditing ? 'edit' : 'create'), $destination);
        }

        if ($this->budget_item_id) {
            abort_unless(
                in_array((int) $this->budget_item_id, array_keys($this->budgetLines()), true),
                404,
            );
        }

        $attributes = [
            'project_id' => $this->project->id,
            'job_site_id' => $this->job_site_id ?: null,
            'title' => $this->title,
            'description' => $this->description ?: null,
            'type' => $this->type,
            'spec_section' => $this->spec_section ?: null,
            'budget_item_id' => $this->budget_item_id ?: null,
            'catalog_item_id' => $this->catalog_item_id ?: null,
            'supplier_id' => $this->supplier_id ?: null,
            'due_date' => $this->due_date ?: null,
        ];

        $approval = DB::transaction(function () use ($attributes) {
            if ($this->isEditing) {
                $this->approval->update($attributes);
                $approval = $this->approval;
            } else {
                $approval = Approval::create($attributes + [
                    'status' => Approval::DRAFT,
                    'created_by_id' => auth()->id(),
                ]);
            }

            $this->syncCertificate($approval);
            $approval->syncDistribution($this->distributionEntries());

            return $approval;
        });

        $this->storeUploads($approval);

        $approval->logActivity($this->isEditing ? ActivityLogEntry::UPDATED : ActivityLogEntry::CREATED);

        session()->flash('approval_message', $this->isEditing
            ? __('collaboration.message.approval_updated')
            : __('collaboration.help.approval_raised_submit_when_reviewers', ['number' => $approval->number]));

        $this->redirectRoute('approvals.show', $approval, navigate: true);
    }

    /**
     * Keep the certificate block in step with the type.
     *
     * Changing an approval away from `laudo_certificado` drops the row: a
     * material carrying an orphan certificate would show a validity date on a
     * screen that never asked for one, and would count towards the lapsing
     * total on the index.
     */
    protected function syncCertificate(Approval $approval): void
    {
        if ($this->type !== Approval::TYPE_CERTIFICATE) {
            $approval->certificate()->delete();

            return;
        }

        $approval->certificate()->updateOrCreate(
            ['approval_id' => $approval->id],
            [
                'issuing_body' => $this->issuing_body,
                'certificate_number' => $this->certificate_number ?: null,
                'issued_at' => $this->issued_at ?: null,
                'valid_until' => $this->valid_until ?: null,
            ],
        );
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

    protected function storeUploads(Approval $approval): void
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
                $approval,
                $upload->getClientOriginalName(),
                $upload->getSize(),
                $upload->getMimeType(),
            );

            $service->storeLocal(FileUpload::findOrFail($begun['version_id']), $upload);
        }

        $this->uploads = [];

        if ($refused !== []) {
            session()->flash('approval_upload_refused', __('collaboration.label.these_files_attached_because',
                ['files' => implode(', ', $refused)],
            ));
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Choices
    |---------------------------------------------------------------------------
    */

    /**
     * The project's budget lines, as id => "code name".
     *
     * A `BudgetItem` is this project's applied cost code, so these are the
     * codes the work is actually costed against — not the template library
     * behind them (docs/rfi-aprovacoes-discovery.md item 4).
     *
     * @return array<int, string>
     */
    protected function budgetLines(): array
    {
        $budgetIds = Budget::query()
            ->where('project_id', $this->project->id)
            ->orWhereIn('job_site_id', $this->project->jobSites()->pluck('id'))
            ->pluck('id');

        if ($budgetIds->isEmpty()) {
            return [];
        }

        return BudgetItem::query()
            ->whereIn('budget_id', $budgetIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (BudgetItem $item) => [$item->id => trim($item->code.' '.$item->name)])
            ->all();
    }

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
        return view('livewire.approval.approval-form', [
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(['id', 'job_site_name']),
            'budgetLines' => $this->budgetLines(),
            'catalogItems' => CatalogItem::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'suppliers' => Vendor::orderBy('name')->pluck('name', 'id')->all(),
            'assignableUsers' => $this->assignableUsers(),
            'chosenUsers' => $this->assignableUserDetails(),
            'roleOptions' => DistributionEntry::roleOptions(),
        ])->layout('components.layouts.app');
    }
}
