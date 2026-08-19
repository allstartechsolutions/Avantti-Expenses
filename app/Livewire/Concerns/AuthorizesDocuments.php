<?php

namespace App\Livewire\Concerns;

/**
 * Guards for the document repository. Hiding a button is not enough — a
 * wire:click can be invoked directly — so every method that writes calls one
 * of these first.
 *
 * Read is open to any signed-in user, except documents flagged internal.
 */
trait AuthorizesDocuments
{
    /**
     * Upload, rename, move, tag, recategorise, create folders, share.
     */
    protected function authorizeDocumentWrite(): void
    {
        abort_unless(
            auth()->user()?->canManageDocuments(),
            403,
            'Manager or administrator access required.'
        );
    }

    /**
     * Delete, restore and purge.
     */
    protected function authorizeDocumentDelete(): void
    {
        abort_unless(
            auth()->user()?->canDeleteDocuments(),
            403,
            'Administrator access required.'
        );
    }

    public function canManageDocuments(): bool
    {
        return (bool) auth()->user()?->canManageDocuments();
    }

    public function canDeleteDocuments(): bool
    {
        return (bool) auth()->user()?->canDeleteDocuments();
    }
}
