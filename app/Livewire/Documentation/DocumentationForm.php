<?php

namespace App\Livewire\Documentation;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\DocArticle;
use App\Support\RichText;
use Livewire\Component;

/**
 * Writing one of this company's own guides.
 *
 * Shipped guides are files and are not editable here — the screen says so
 * rather than pretending otherwise.
 */
class DocumentationForm extends Component
{
    use AuthorizesAbility;

    public ?DocArticle $article = null;

    public string $title = '';
    public string $category = 'general';
    public string $summary = '';
    public string $body = '';
    public bool $is_published = false;
    public int $position = 0;

    public function mount(?DocArticle $article = null): void
    {
        // Was a hard-coded manager-or-above check. Writing a new guide and
        // editing an existing one are separate grants, seeded to exactly the
        // people who could do it before.
        $this->authorizeAbility($article?->exists ? 'documentation.edit' : 'documentation.create');

        if ($article?->exists) {
            $this->article = $article;
            $this->title = $article->title;
            $this->category = $article->category;
            $this->summary = (string) $article->summary;
            $this->body = (string) $article->body;
            $this->is_published = (bool) $article->is_published;
            $this->position = (int) $article->position;
        }
    }

    public function categories(): array
    {
        return collect(config('documentation.categories', []))
            ->map(fn (array $category) => __($category['label']))
            ->all();
    }

    public function save()
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(config('documentation.categories', [])))],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'position' => ['integer', 'min:0', 'max:9999'],
        ]);

        if (RichText::isEmpty($this->body)) {
            $this->addError('body', __('The guide has no content yet.'));

            return null;
        }

        $article = DocArticle::updateOrCreate(
            ['id' => $this->article?->id],
            [
                'title' => $this->title,
                'category' => $this->category,
                'summary' => $this->summary ?: null,
                'body' => $this->body,
                'is_published' => $this->is_published,
                'position' => $this->position,
                'created_by' => $this->article?->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        session()->flash('message', $this->article
            ? __('Guide updated.')
            : __('Guide created.'));

        return $this->redirect(route('documentation.show', $article->slug), navigate: true);
    }

    public function render()
    {
        return view('livewire.documentation.documentation-form')->layout('components.layouts.app');
    }
}
