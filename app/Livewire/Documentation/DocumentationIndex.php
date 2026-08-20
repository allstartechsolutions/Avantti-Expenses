<?php

namespace App\Livewire\Documentation;

use App\Services\DocumentationService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The library: every guide, shipped and written here, in one index.
 */
class DocumentationIndex extends Component
{
    public string $search = '';

    protected $queryString = ['search' => ['except' => '']];

    public function canWrite(): bool
    {
        return (bool) (auth()->user()?->is_admin || auth()->user()?->is_manager);
    }

    #[Computed]
    public function groups(): Collection
    {
        return app(DocumentationService::class)->byCategory($this->canWrite(), $this->search ?: null);
    }

    #[Computed]
    public function counts(): array
    {
        $service = app(DocumentationService::class);

        return [
            'total' => $service->library($this->canWrite())->count(),
            'shipped' => $service->shipped()->count(),
            'custom' => $service->custom($this->canWrite())->count(),
            'drafts' => $this->canWrite()
                ? $service->custom(true)->reject(fn (array $e) => $e['is_published'])->count()
                : 0,
        ];
    }

    public function render()
    {
        return view('livewire.documentation.documentation-index')->layout('components.layouts.app');
    }
}
