<?php

namespace App\Livewire\Shared;

use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HeaderSearch extends Component
{
    /** Minimum characters before the component is allowed to touch the database. */
    public const MIN_LENGTH = 2;

    /** How many rows each group may contribute to the dropdown. */
    public const PER_GROUP = 5;

    public string $search = '';

    public function clearSearch(): void
    {
        $this->search = '';
    }

    /**
     * The trimmed term, or null when it is too short to search on.
     * Everything downstream returns empty while this is null, so a plain page
     * load never issues a query.
     */
    protected function term(): ?string
    {
        $term = trim($this->search);

        return mb_strlen($term) >= self::MIN_LENGTH ? $term : null;
    }

    /** Escape the wildcards LIKE would otherwise treat as operators. */
    protected function likeTerm(string $term): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    }

    #[Computed]
    public function projects(): Collection
    {
        if (! $term = $this->term()) {
            return collect();
        }

        $like = $this->likeTerm($term);

        return Project::query()
            // N9: the search was the easiest way to enumerate records somebody
            // was never meant to see.
            ->visibleTo(auth()->user())
            ->with('client:id,company_name')
            ->select('id', 'client_id', 'project_name', 'street', 'city', 'state', 'status')
            ->where(function ($query) use ($like) {
                $query->where('project_name', 'like', $like)
                    ->orWhere('street', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhereHas('client', fn ($client) => $client->where('company_name', 'like', $like));
            })
            ->orderBy('project_name')
            ->limit(self::PER_GROUP)
            ->get();
    }

    #[Computed]
    public function jobSites(): Collection
    {
        if (! $term = $this->term()) {
            return collect();
        }

        $like = $this->likeTerm($term);

        return JobSite::query()
            ->visibleTo(auth()->user())
            ->with('project:id,project_name')
            ->select('id', 'project_id', 'job_site_name', 'street', 'city', 'state', 'status')
            ->where(function ($query) use ($like) {
                $query->where('job_site_name', 'like', $like)
                    ->orWhere('street', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhereHas('project', fn ($project) => $project->where('project_name', 'like', $like));
            })
            ->orderBy('job_site_name')
            ->limit(self::PER_GROUP)
            ->get();
    }

    #[Computed]
    public function hasResults(): bool
    {
        return $this->projects->isNotEmpty() || $this->jobSites->isNotEmpty();
    }

    #[Computed]
    public function isSearching(): bool
    {
        return $this->term() !== null;
    }

    public function render(): View
    {
        return view('livewire.shared.header-search');
    }
}
