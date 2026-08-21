<?php

namespace App\Policies;

/**
 * Whether somebody may see, record, correct, distribute or delete income.
 *
 * The decision is the resolver's; this only names the area. Income carries its
 * own `job_site_id` / `project_id`, which the base class already walks — a
 * distributed income belongs to the project, because the shares are what say
 * where the money went.
 */
class IncomePolicy extends ModulePolicy
{
    protected string $area = 'income';
}
