<?php

namespace App\Livewire\Meeting;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Enums\UserStatus;
use App\Models\JobSite;
use App\Models\MeetingSeries;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The recurring meetings a company holds — "Weekly Site Meeting", "Directors
 * Meeting" — with the people they normally invite and the projects they
 * normally cover.
 *
 * This is what makes carry-forward mean anything: the open items of the site
 * meeting must not land on the agenda of the directors meeting, so "the
 * previous meeting" is always read within one series.
 *
 * See docs/meetings-module-plan.md §3.1.
 */
class MeetingSeriesIndex extends Component
{
    use AuthorizesAbility;

    public bool $showInactive = false;

    // The form
    public ?int $editingId = null;
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public string $cadence = 'weekly';
    public string $default_location = '';
    public bool $is_active = true;

    /** @var array<int, array{user_id:?string, name:string, company:string, email:string, role:string}> */
    public array $members = [];

    /** @var array<int, array{project_id:string, job_site_id:?string}> */
    public array $scopes = [];

    public function mount(): void
    {
        $this->authorizeManage();
    }

    /**
     * A meeting series is a company-wide thing — a standing weekly across
     * several projects — so its grant is asked without a scope and answered by
     * the role, which is what an unscoped ability does.
     */
    protected function authorizeManage(): void
    {
        $this->authorizeAbility('meetings.manage_series');
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    #[Computed]
    public function seriesList(): Collection
    {
        return MeetingSeries::query()
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->withCount(['meetings', 'members', 'scopes'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function users(): Collection
    {
        return User::where('status', UserStatus::ACTIVE)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::orderBy('project_name')->get(['id', 'project_name']);
    }

    /** Job sites for every project on screen, so the row selects can be built. */
    #[Computed]
    public function jobSitesByProject(): Collection
    {
        return JobSite::orderBy('job_site_name')->get(['id', 'project_id', 'job_site_name'])->groupBy('project_id');
    }

    // =========================================================================
    // THE FORM
    // =========================================================================

    public function openCreate(): void
    {
        $this->authorizeManage();
        $this->resetForm();

        // A series with nobody in it and nothing to cover is not useful, so it
        // opens with one row of each rather than empty tables.
        $this->members = [$this->blankMember(['user_id' => (string) auth()->id(), 'role' => 'chair'])];
        $this->scopes = [];

        $this->dispatch('open-modal', 'series-form-modal');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();

        $series = MeetingSeries::with(['members', 'scopes'])->findOrFail($id);

        $this->editingId = $series->id;
        $this->name = $series->name;
        $this->code = $series->code;
        $this->description = (string) $series->description;
        $this->cadence = $series->cadence;
        $this->default_location = (string) $series->default_location;
        $this->is_active = (bool) $series->is_active;

        $this->members = $series->members->map(fn ($m) => [
            'user_id' => $m->user_id ? (string) $m->user_id : '',
            'name' => (string) $m->name,
            'company' => (string) $m->company,
            'email' => (string) $m->email,
            'role' => $m->role,
        ])->all();

        $this->scopes = $series->scopes->map(fn ($s) => [
            'project_id' => (string) $s->project_id,
            'job_site_id' => $s->job_site_id ? (string) $s->job_site_id : '',
        ])->all();

        $this->dispatch('open-modal', 'series-form-modal');
    }

    public function addMember(): void
    {
        $this->members[] = $this->blankMember();
    }

    public function removeMember(int $index): void
    {
        unset($this->members[$index]);
        $this->members = array_values($this->members);
    }

    public function addScope(): void
    {
        $this->scopes[] = ['project_id' => '', 'job_site_id' => ''];
    }

    public function removeScope(int $index): void
    {
        unset($this->scopes[$index]);
        $this->scopes = array_values($this->scopes);
    }

    /** Changing the project invalidates whatever job site was chosen. */
    public function updatedScopes($value, string $key): void
    {
        if (str_ends_with($key, '.project_id')) {
            $index = (int) explode('.', $key)[0];
            $this->scopes[$index]['job_site_id'] = '';
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('meeting_series', 'code')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'cadence' => ['required', 'in:weekly,biweekly,monthly,quarterly,ad_hoc'],
            'default_location' => ['nullable', 'string', 'max:255'],
            'members' => ['array'],
            'members.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'members.*.name' => ['nullable', 'string', 'max:255'],
            'members.*.company' => ['nullable', 'string', 'max:255'],
            'members.*.email' => ['nullable', 'email', 'max:255'],
            'members.*.role' => ['required', 'in:chair,secretary,participant'],
            'scopes' => ['array'],
            'scopes.*.project_id' => ['required', 'integer', 'exists:projects,id'],
            'scopes.*.job_site_id' => ['nullable', 'integer', 'exists:job_sites,id'],
        ], [], [
            'code' => __('code'),
        ]);

        // A member is either a system user or a name — a blank row is neither.
        foreach ($this->members as $index => $member) {
            if (! $member['user_id'] && trim($member['name']) === '') {
                $this->addError("members.{$index}.name", __('Choose a user, or type a name for someone outside the company.'));

                return;
            }
        }

        // The code is part of every meeting number this series ever issues.
        $code = strtoupper($this->code);

        DB::transaction(function () use ($code) {
            $series = MeetingSeries::updateOrCreate(
                ['id' => $this->editingId],
                [
                    'name' => $this->name,
                    'code' => $code,
                    'description' => $this->description ?: null,
                    'cadence' => $this->cadence,
                    'default_location' => $this->default_location ?: null,
                    'is_active' => $this->is_active,
                    'created_by' => $this->editingId ? MeetingSeries::find($this->editingId)?->created_by : auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            $series->members()->delete();

            foreach ($this->members as $member) {
                $series->members()->create([
                    'user_id' => $member['user_id'] ?: null,
                    'name' => $member['user_id'] ? null : $member['name'],
                    'company' => $member['user_id'] ? null : ($member['company'] ?: null),
                    'email' => $member['user_id'] ? null : ($member['email'] ?: null),
                    'role' => $member['role'],
                ]);
            }

            $series->scopes()->delete();

            $seen = [];

            foreach ($this->scopes as $scope) {
                $key = $scope['project_id'].'-'.($scope['job_site_id'] ?: 'p');

                if (in_array($key, $seen, true)) {
                    continue;
                }

                $seen[] = $key;

                $series->scopes()->create([
                    'project_id' => $scope['project_id'],
                    'job_site_id' => $scope['job_site_id'] ?: null,
                ]);
            }
        });

        session()->flash('message', $this->editingId ? __('Series updated.') : __('Series created.'));

        $this->closeForm();
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', 'series-form-modal');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();

        $series = MeetingSeries::findOrFail($id);
        $series->update(['is_active' => ! $series->is_active, 'updated_by' => auth()->id()]);

        unset($this->seriesList);
    }

    public function delete(int $id): void
    {
        // Deleting a series is not the same as running one; it was admin-only
        // and is now the module's own delete grant.
        $this->authorizeAbility('meetings.delete');

        $series = MeetingSeries::withCount('meetings')->findOrFail($id);

        // A series that has held meetings is part of the record. Switch it off
        // instead — the minutes it issued keep their numbers either way.
        if ($series->meetings_count > 0) {
            session()->flash('error', __('This series has :count meetings and cannot be deleted. Make it inactive instead.', [
                'count' => $series->meetings_count,
            ]));

            return;
        }

        $series->delete();

        unset($this->seriesList);

        session()->flash('message', __('Series deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'default_location', 'members', 'scopes']);
        $this->cadence = 'weekly';
        $this->is_active = true;
        $this->resetValidation();
    }

    /** @return array{user_id:string, name:string, company:string, email:string, role:string} */
    protected function blankMember(array $overrides = []): array
    {
        return array_merge([
            'user_id' => '',
            'name' => '',
            'company' => '',
            'email' => '',
            'role' => 'participant',
        ], $overrides);
    }

    public function render()
    {
        return view('livewire.meeting.meeting-series-index')->layout('components.layouts.app');
    }
}
