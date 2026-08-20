<?php

namespace App\Policies;

/**
 * A job site is governed by the same area as its project: its own membership
 * where it has one, the project's otherwise.
 */
class JobSitePolicy extends ModulePolicy
{
    protected string $area = 'project';
}
