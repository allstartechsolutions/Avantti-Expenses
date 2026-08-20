<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Something a person can be given access to on its own — today a project or a
 * job site, tomorrow whatever else earns its own membership list.
 *
 * The permission resolver never names Project or JobSite: it asks a scope for
 * its parent and walks up. Adding a third kind of scope is implementing this
 * interface and declaring the area's level in config/permissions.php; nothing
 * in the resolver, the matrix or the Team tab has to learn about it.
 */
interface PermissionScope
{
    /**
     * The scope this one sits inside, whose memberships cascade down to it —
     * a job site's project. Null for a top-level scope.
     */
    public function parentScope(): ?Model;

    /**
     * The level key used in config/permissions.php: 'project', 'job_site', …
     */
    public function scopeLevel(): string;

    /** What this scope is called on screen. */
    public function scopeLabel(): string;
}
