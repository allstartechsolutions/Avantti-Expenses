<?php

namespace App\Policies;

/**
 * Whether somebody may open, edit or archive a project. The decision is the
 * resolver's; this only names the area and how to get from a record to its
 * scope — which for a project is itself.
 */
class ProjectPolicy extends ModulePolicy
{
    protected string $area = 'project';
}
