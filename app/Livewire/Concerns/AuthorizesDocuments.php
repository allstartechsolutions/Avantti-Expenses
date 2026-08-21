<?php

namespace App\Livewire\Concerns;

/**
 * Guards for the document repository.
 *
 * Hiding a button is not enough — a wire:click can be invoked directly — so
 * every method that writes calls one of these first.
 *
 * Until M12 this was a two-way split (`canManageDocuments` for every write,
 * `canDeleteDocuments` for every delete) reading `is_admin` and `is_manager`
 * off the user. It is now five grants, asked about the project or job site the
 * screen is on, so a person can be given documents on one project and not
 * another — and reading is a grant too, which it never was.
 *
 * The host supplies the location through `documentScope()`, which
 * ManagesDocuments derives from its context.
 */
trait AuthorizesDocuments
{
    use AuthorizesAbility;

    /** Uploading, and creating a folder. */
    protected function authorizeDocumentCreate(): void
    {
        $this->authorizeAbility('documents.create', $this->documentScope());
    }

    /** Renaming, moving, tagging, recategorising, restoring a version. */
    protected function authorizeDocumentEdit(): void
    {
        $this->authorizeAbility('documents.edit', $this->documentScope());
    }

    /** Delete, restore and purge. */
    protected function authorizeDocumentDelete(): void
    {
        $this->authorizeAbility('documents.delete', $this->documentScope());
    }

    /**
     * Creating, and revoking, a link that hands a file to somebody with no
     * login at all. Its own grant because it is the one place in the
     * application where access leaves the application.
     */
    protected function authorizeDocumentShare(): void
    {
        $this->authorizeAbility('documents.share', $this->documentScope());
    }

    // =========================================================================
    // For the views. Never a substitute for the guards above.
    // =========================================================================

    public function canCreateDocuments(): bool
    {
        return $this->allowsAbility('documents.create', $this->documentScope());
    }

    public function canEditDocuments(): bool
    {
        return $this->allowsAbility('documents.edit', $this->documentScope());
    }

    public function canDeleteDocuments(): bool
    {
        return $this->allowsAbility('documents.delete', $this->documentScope());
    }

    public function canShareDocuments(): bool
    {
        return $this->allowsAbility('documents.share', $this->documentScope());
    }

    /**
     * The old name, kept because the views ask "may this person change
     * anything here?" in a dozen places and that question is still worth one
     * answer. Create or edit — either is enough to be shown the toolbar.
     */
    public function canManageDocuments(): bool
    {
        return $this->canCreateDocuments() || $this->canEditDocuments();
    }
}
