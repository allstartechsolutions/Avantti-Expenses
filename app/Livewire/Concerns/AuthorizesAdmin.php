<?php

namespace App\Livewire\Concerns;

/**
 * Adds an admin guard for sensitive Livewire actions (e.g. deletes).
 * Call $this->authorizeAdmin() at the start of any method that must be
 * restricted to administrators — hiding the button is not enough, since the
 * wire:click can still be invoked directly.
 */
trait AuthorizesAdmin
{
    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403, 'Administrator access required.');
    }
}
