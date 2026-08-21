<?php

namespace App\Policies;

/**
 * Whether somebody may see, key in, correct, settle or delete an expense.
 *
 * The decision is the resolver's; this only names the area. An expense reaches
 * its scope through its own `job_site_id` / `project_id`, which the base class
 * already walks — a job-site expense is answered by the job site's membership
 * where there is one, and by the project's otherwise.
 */
class ExpensePolicy extends ModulePolicy
{
    protected string $area = 'expenses';
}
