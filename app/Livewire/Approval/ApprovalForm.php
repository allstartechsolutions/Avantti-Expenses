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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

    /**
     * The three type-to-search pickers.
     *
     * A project's budget can run to hundreds of lines and the vendor book to
     * thousands of names, which is more than a `<select>` can be walked
     * through. Each string holds what was typed; when a row is taken, the
     * string becomes that row's label and the id beside it is what saves.
     */
    public string $budgetItemSearch = '';

    public string $supplierSearch = '';

    public string $catalogItemSearch = '';

    /** Certificate block — only for `laudo_certificado`. */
    public string $issuing_body = '';

    public ?string $certificate_number = null;

    public ?string $issued_at = null;

    public ?string $valid_until = null;

    public array $distributionRows = [];

    /**
     * Files chosen for this approval but not yet stored.
     *
     * They are held rather than sent because the record they belong to may not
     * exist yet — `save()` creates the approval and the attachments in one
     * step. `newUploads` is what the drop zone writes to; `updatedNewUploads()`
     * moves them across, so a second drop adds to the queue instead of
     * replacing what was dropped first.
     */
    public array $uploads = [];

    public array $newUploads = [];

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

        // The pickers open showing what is linked, not an empty box the user
        // has to search again to find out.
        $this->budgetItemSearch = $approval->budgetItem ? $this->budgetItemLabel($approval->budgetItem) : '';
        $this->supplierSearch = $approval->supplier?->name ?? '';
        $this->catalogItemSearch = $approval->catalogItem?->name ?? '';

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

    /*
    |---------------------------------------------------------------------------
    | The pickers
    |---------------------------------------------------------------------------
    |
    | Each one is the same three moves: take a row, drop the link, and drop the
    | link again the moment the text stops being the label of what was taken —
    | otherwise somebody types over a chosen supplier, saves, and the record
    | keeps a vendor whose name is no longer on the screen.
    */

    public function selectBudgetItem(int $id): void
    {
        $this->authorizeAbility($this->isEditing ? 'approvals.edit' : 'approvals.create', $this->scope());

        // Never act on an id that came from the browser: this one has to be a
        // line of *this* project's budget, the same check `save()` makes.
        $item = $this->projectBudgetItems()->whereKey($id)->first();

        if (! $item) {
            return;
        }

        $this->budget_item_id = (string) $item->id;
        $this->budgetItemSearch = $this->budgetItemLabel($item);
    }

    public function clearBudgetItem(): void
    {
        $this->budget_item_id = null;
        $this->budgetItemSearch = '';
    }

    public function updatedBudgetItemSearch(): void
    {
        if ($this->budget_item_id && $this->budgetItemSearch !== $this->selectedBudgetItemLabel()) {
            $this->budget_item_id = null;
        }
    }

    public function selectSupplier(int $id): void
    {
        $this->authorizeAbility($this->isEditing ? 'approvals.edit' : 'approvals.create', $this->scope());

        $supplier = Vendor::whereKey($id)->first();

        if (! $supplier) {
            return;
        }

        $this->supplier_id = (string) $supplier->id;
        $this->supplierSearch = $supplier->name;
    }

    public function clearSupplier(): void
    {
        $this->supplier_id = null;
        $this->supplierSearch = '';
    }

    public function updatedSupplierSearch(): void
    {
        if ($this->supplier_id && $this->supplierSearch !== $this->selectedSupplierLabel()) {
            $this->supplier_id = null;
        }
    }

    public function selectCatalogItem(int $id): void
    {
        $this->authorizeAbility($this->isEditing ? 'approvals.edit' : 'approvals.create', $this->scope());

        $item = CatalogItem::where('is_active', true)->whereKey($id)->first();

        if (! $item) {
            return;
        }

        $this->catalog_item_id = (string) $item->id;
        $this->catalogItemSearch = $item->name;
    }

    public function clearCatalogItem(): void
    {
        $this->catalog_item_id = null;
        $this->catalogItemSearch = '';
    }

    public function updatedCatalogItemSearch(): void
    {
        if ($this->catalog_item_id && $this->catalogItemSearch !== $this->selectedCatalogItemLabel()) {
            $this->catalog_item_id = null;
        }
    }

    /**
     * Files dropped or chosen join the queue, and the box is emptied.
     *
     * Emptied **whatever happens**, including for a file that fails the size
     * rule. One left behind is invisible — the list on screen is the queue,
     * not this — and would fail every later save on a file with no button to
     * remove it, with nothing to do but reload and lose the form.
     *
     * The refusal is said with `addError()` rather than by throwing, because
     * `validate()` ends with a bare `resetErrorBag()`: a file dropped onto a
     * form that is already showing "the title field is required" would
     * silently clear that message while the title was still empty.
     */
    public function updatedNewUploads(): void
    {
        $dropped = $this->newUploads;
        $this->newUploads = [];

        $refused = [];

        foreach ($dropped as $file) {
            $validator = Validator::make(['file' => $file], ['file' => $this->fileRule()]);

            if ($validator->fails()) {
                $refused[] = $file->getClientOriginalName();

                continue;
            }

            $this->uploads[] = $file;
        }

        if ($refused !== []) {
            $this->addError('newUploads', __('Not attached — larger than the :size limit: :files', [
                'size' => \App\Services\DocumentSettings::formatBytes(app(FileUploadService::class)->maxBytes()),
                'files' => implode(', ', $refused),
            ]));
        }
    }

    /**
     * Take a file back out of the queue before it is stored.
     *
     * NOT `removeUpload()`: that name is part of Livewire's own `$wire` API
     * (`$wire.removeUpload(property, tmpFilename)`), so a `wire:click` on it
     * never reaches this class — it reaches Livewire's uploader with the row
     * index where a property name should be, and the request dies with
     * "Property [$0] not found".
     */
    public function discardUpload(int $index): void
    {
        // Livewire's own `_removeUpload()` deletes the temporary file; dropping
        // only the array entry would leave it in livewire-tmp until the daily
        // sweep, which on a form where drawings are dropped and thought better
        // of is real disk.
        $this->uploads[$index]?->delete();

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
            // `newUploads` carries no rule: `updatedNewUploads()` empties it on
            // every change, so a rule here could only ever fail on a file the
            // user cannot see.
            'uploads.*' => $this->fileRule(),
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
    /** One place for the size cap, so the drop zone and `save()` cannot disagree. */
    protected function fileRule(): string
    {
        return 'nullable|file|max:'.(int) (app(FileUploadService::class)->maxBytes() / 1024);
    }

    protected function validationAttributes(): array
    {
        return [
            'job_site_id' => __('job site'),
            'budget_item_id' => __('collaboration.field.budget_line'),
            'catalog_item_id' => __('collaboration.field.catalog_item'),
            'supplier_id' => __('supplier'),
            'uploads.*' => __('attachment'),
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
                $this->projectBudgetItems()->whereKey($this->budget_item_id)->exists(),
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
     * Per-request caches.
     *
     * `render()` runs on every debounced keystroke, and each picker asks for
     * the label of what is linked, the project's job sites and the people who
     * can be named. Protected, so Livewire neither ships them to the browser
     * nor carries them into the next request, where they could be stale.
     */
    protected ?array $jobSiteIdCache = null;

    protected array $labelCache = [];

    protected ?array $assignableCache = null;

    protected ?array $assignableDetailCache = null;

    /** How many rows a picker offers at a time. */
    protected const RESULT_LIMIT = 20;

    /** How much has to be typed before a search runs. */
    protected const MIN_SEARCH = 2;

    /**
     * The project's budget lines.
     *
     * A `BudgetItem` is this project's applied cost code, so these are the
     * codes the work is actually costed against — not the template library
     * behind them (docs/rfi-aprovacoes-discovery.md item 4). Everything that
     * offers or accepts a line goes through this query, so a line from
     * another project can neither be listed nor saved.
     */
    protected function projectBudgetItems(): Builder
    {
        return BudgetItem::query()
            ->whereIn('budget_id', Budget::query()
                ->where(fn ($q) => $q
                    ->where('project_id', $this->project->id)
                    ->orWhereIn('job_site_id', $this->projectJobSiteIds()))
                ->select('id'));
    }

    /** @return array<int, int> */
    protected function projectJobSiteIds(): array
    {
        return $this->jobSiteIdCache ??= $this->project->jobSites()->pluck('id')->all();
    }

    protected function budgetItemLabel(BudgetItem $item): string
    {
        return trim($item->code.' '.$item->name);
    }

    protected function selectedBudgetItemLabel(): string
    {
        return $this->cachedLabel('budget', $this->budget_item_id, function (string $id) {
            $item = $this->projectBudgetItems()->whereKey($id)->first();

            return $item ? $this->budgetItemLabel($item) : '';
        });
    }

    protected function selectedSupplierLabel(): string
    {
        return $this->cachedLabel('supplier', $this->supplier_id,
            fn (string $id) => Vendor::whereKey($id)->value('name') ?? '');
    }

    protected function selectedCatalogItemLabel(): string
    {
        return $this->cachedLabel('catalog', $this->catalog_item_id,
            fn (string $id) => CatalogItem::whereKey($id)->value('name') ?? '');
    }

    /**
     * The label of what is linked, read once per id per request.
     *
     * Asked for three times over in a single render — by the view, by the
     * search that has to know it is looking at a read-back and not a search,
     * and by the unlink check.
     */
    protected function cachedLabel(string $key, ?string $id, callable $read): string
    {
        if (! $id) {
            return '';
        }

        return $this->labelCache[$key.':'.$id] ??= $read($id);
    }

    /**
     * Whether a picker should run its query at all.
     *
     * Not while the box still holds the label of what is already linked: that
     * text is a read-back, not a search, and querying on it would drop a
     * panel over the field on every re-render.
     */
    protected function isSearching(string $term, string $selectedLabel): bool
    {
        $term = trim($term);

        return mb_strlen($term) >= self::MIN_SEARCH && $term !== $selectedLabel;
    }

    /**
     * Budget lines matching what was typed, by code or by name.
     *
     * The location comes back with each row because a project's own budget
     * and its job sites' budgets can carry the same code twice — "01.02
     * Alvenaria" alone would not say which one is being costed.
     *
     * @return array<int, array{id: int, label: string, meta: ?string}>
     */
    protected function budgetItemResults(): array
    {
        if (! $this->isSearching($this->budgetItemSearch, $this->selectedBudgetItemLabel())) {
            return [];
        }

        $term = trim($this->budgetItemSearch);

        return $this->projectBudgetItems()
            ->with('budget.jobSite:id,job_site_name')
            ->where(fn ($q) => $q
                ->where('code', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%'))
            ->orderBy('code')
            ->take(self::RESULT_LIMIT)
            ->get()
            ->map(fn (BudgetItem $item) => [
                'id' => $item->id,
                'label' => $this->budgetItemLabel($item),
                'meta' => $item->budget?->jobSite?->job_site_name ?? __('Project (General)'),
            ])
            ->all();
    }

    /**
     * Vendors matching what was typed, by name or by the person to call.
     *
     * @return array<int, array{id: int, label: string, meta: ?string}>
     */
    protected function supplierResults(): array
    {
        if (! $this->isSearching($this->supplierSearch, $this->selectedSupplierLabel())) {
            return [];
        }

        $term = trim($this->supplierSearch);

        return Vendor::query()
            ->where(fn ($q) => $q
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('contact_name', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->take(self::RESULT_LIMIT)
            ->get(['id', 'name', 'contact_name', 'city', 'state'])
            ->map(fn (Vendor $vendor) => [
                'id' => $vendor->id,
                'label' => $vendor->name,
                'meta' => collect([$vendor->contact_name, collect([$vendor->city, $vendor->state])->filter()->implode('/')])
                    ->filter()
                    ->implode(' · ') ?: null,
            ])
            ->all();
    }

    /**
     * Active catalog items matching what was typed, by name or SKU.
     *
     * @return array<int, array{id: int, label: string, meta: ?string}>
     */
    protected function catalogItemResults(): array
    {
        if (! $this->isSearching($this->catalogItemSearch, $this->selectedCatalogItemLabel())) {
            return [];
        }

        $term = trim($this->catalogItemSearch);

        return CatalogItem::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('sku', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->take(self::RESULT_LIMIT)
            ->get(['id', 'name', 'sku', 'usage_unit', 'purchase_unit'])
            ->map(fn (CatalogItem $item) => [
                'id' => $item->id,
                'label' => $item->name,
                'meta' => collect([$item->sku, $item->usage_unit ?? $item->purchase_unit])
                    ->filter()
                    ->implode(' · ') ?: null,
            ])
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
        return $this->assignableDetailCache ??= Membership::query()
            ->active()
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('scopeable_type', Project::class)
                    ->where('scopeable_id', $this->project->id));

                $q->orWhere(fn ($q) => $q
                    ->where('scopeable_type', JobSite::class)
                    ->whereIn('scopeable_id', $this->projectJobSiteIds()));
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
        return $this->assignableCache ??= Membership::query()
            ->active()
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('scopeable_type', Project::class)
                    ->where('scopeable_id', $this->project->id));

                $q->orWhere(fn ($q) => $q
                    ->where('scopeable_type', JobSite::class)
                    ->whereIn('scopeable_id', $this->projectJobSiteIds()));
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
            'budgetItemResults' => $this->budgetItemResults(),
            'budgetItemLabel' => $this->selectedBudgetItemLabel(),
            'budgetLineCount' => $this->projectBudgetItems()->count(),
            'supplierResults' => $this->supplierResults(),
            'supplierLabel' => $this->selectedSupplierLabel(),
            'supplierCount' => Vendor::count(),
            'catalogItemResults' => $this->catalogItemResults(),
            'catalogItemLabel' => $this->selectedCatalogItemLabel(),
            'catalogItemCount' => CatalogItem::where('is_active', true)->count(),
            'minSearch' => self::MIN_SEARCH,
            'assignableUsers' => $this->assignableUsers(),
            'chosenUsers' => $this->assignableUserDetails(),
            'roleOptions' => DistributionEntry::roleOptions(),
        ])->layout('components.layouts.app');
    }
}
