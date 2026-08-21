<?php

namespace App\Livewire\Documentation;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\DocArticle;
use App\Services\DocumentationService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * One guide, rendered.
 *
 * The same page serves a markdown file shipped with the product and an article
 * somebody wrote in the editor — a reader should not be able to tell, beyond a
 * small badge saying where it came from.
 */
class DocumentationArticle extends Component
{
    use AuthorizesAbility;

    public string $slug;

    public function mount(string $slug): void
    {
        $this->authorizeAbility('documentation.view');

        $this->slug = $slug;

        abort_if($this->entry === null, 404);
    }

    /**
     * Whether unpublished drafts are listed for this reader.
     *
     * A draft is visible to whoever could publish it, which is whoever may
     * write — the same rule as before, asked of the grants instead of the role.
     */
    public function canWrite(): bool
    {
        return $this->allowsAbility('documentation.create')
            || $this->allowsAbility('documentation.edit');
    }

    #[Computed]
    public function entry(): ?array
    {
        return app(DocumentationService::class)->find($this->slug, $this->canWrite());
    }

    /** The rest of the library, for the sidebar. */
    #[Computed]
    public function siblings(): Collection
    {
        return app(DocumentationService::class)->byCategory($this->canWrite());
    }

    /** Where "previous" and "next" go, in reading order. */
    #[Computed]
    public function neighbours(): array
    {
        $all = app(DocumentationService::class)->library($this->canWrite());
        $index = $all->search(fn (array $entry) => $entry['slug'] === $this->slug);

        return [
            'previous' => $index > 0 ? $all[$index - 1] : null,
            'next' => $index !== false && $index < $all->count() - 1 ? $all[$index + 1] : null,
        ];
    }

    public function deleteArticle()
    {
        $this->authorizeAbility('documentation.delete');

        $entry = $this->entry;

        abort_unless($entry && $entry['source'] === 'custom', 403);

        DocArticle::whereKey($entry['model']->id)->delete();

        session()->flash('message', __('Guide deleted.'));

        return $this->redirect(route('documentation.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.documentation.documentation-article')->layout('components.layouts.app');
    }
}
