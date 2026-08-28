<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Services\AbilityCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the permission module's starting data, and is safe to run again on
 * every deploy — `php artisan permissions:sync` calls exactly this.
 *
 * Three jobs:
 *
 *  1. The system templates ("Site Supervisor", "Procurement", …). Created if
 *     missing, never overwritten, so a customer's edits survive a deploy.
 *  2. The ability lists of the `manager` and `employee` roles. Seeded only
 *     while a role has no abilities at all — again, never clobbering edits.
 *     `admin` gets none and needs none: it is allowed everything before these
 *     rows are read.
 *  3. Backfill: the project manager and the job-site supervisor become real
 *     memberships instead of reporting labels.
 *
 * None of it has any effect until an area is marked `swept` in
 * config/permissions.php — see the legacy bridge, docs/permissions-module-plan.md §9.1.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Held back from BOTH seeded roles.
     *
     * The first block is what the `admin` middleware and `authorizeAdmin()`
     * hold back today — seeding these to a manager would be a widening, not a
     * reproduction. The second block is abilities with no counterpart in the
     * current code: they start admin-only and each module's pass decides where
     * they really belong.
     *
     * Note that the `sensitive` flag in the catalogue is deliberately NOT used
     * here. It is a hint for the permission matrix — show a warning next to
     * this toggle — not a statement about who holds the ability today. A
     * manager can create a document share link right now, and that has to
     * survive the seed.
     */
    protected const ADMIN_ONLY_ABILITIES = [
        // --- admin-only today: authorizeAdmin() on the delete actions -------
        'expenses.delete',
        'income.delete',
        'requisitions.delete',
        'quotations.delete',
        'vendors.delete',
        'vendors.merge',
        'documents.delete',
        'projects.delete',
        'project.archive',

        // --- admin-only today: admin-edits-a-paid-expense -------------------
        'expenses.edit_paid',

        // --- Added in M14: editing a daily report after it has closed. Was a
        //     hard-coded `is_admin` on the form.
        'daily-reports.edit_locked',

        // --- Added in M11: taking a contract payment back out. Undoing is
        //     narrower than doing, the same rule as change-orders.unapprove.
        'contracts.unpay',

        // --- §4b, added in M10: approving your own change order, and
        //     undoing an approval. Both held back from both seeded roles:
        //     the first is the same rule as the two above, the second pulls
        //     money back out of a live budget.
        'change-orders.approve_own',
        'change-orders.unapprove',

        // --- N3, added in M8: awarding proposals you keyed in yourself ------
        // Blocked for everybody by default, exactly like approve_own.
        'quotations.award_own',

        // --- N2, added in M7: approving your own requisition ----------------
        // Blocked for everybody by default. This is the tick that lifts it,
        // for a company small enough that the raiser and the reviewer are the
        // same person. Held back from BOTH seeded roles so that turning it on
        // is always somebody's decision.
        'requisitions.approve_own',

        // --- Added in M18: the company overview on the dashboard. Today the
        //     view renders it on `$role === 'admin'` and shows everybody else
        //     a placeholder, so this reproduces that exactly. Grant it and the
        //     person sees only the cards their other abilities already allow.
        'dashboard.overview',

        // --- admin-only today: only an admin deletes a custom article -------
        'documentation.delete',

        // --- admin-only today: MeetingSeriesIndex::delete() ------------------
        'meetings.delete',

        // --- admin-only today: Task::canDelete() was a hard-coded is_admin.
        //     Found at F2; without this line the pass would have handed task
        //     deletion to everybody.
        'tasks.delete',

        // --- admin-only today: Meeting::canRevise(). Correcting a minute that
        //     has been signed off and mailed out.
        'meetings.revise',

        // --- admin-only today: `admin` middleware on the routes -------------
        'users.view', 'users.create', 'users.edit', 'users.suspend',
        'cost-codes.view', 'cost-codes.create', 'cost-codes.edit', 'cost-codes.delete',
        'reports.view', 'reports.export',
        'reports.sales_tax', 'reports.accounts_payable', 'reports.company_financials',
        'reports.expenses', 'reports.payment_schedule', 'reports.payment_details',
        'settings.view', 'settings.edit', 'settings.manage_modules',

        // --- Collaboration (RFIs and approvals), new in this module ---------
        // Nothing is being reproduced here: neither area existed before, so no
        // grant is being widened or narrowed. These three follow the rules the
        // rest of the file already applies.
        //
        // The two deletes are the same rule as every other delete above.
        'rfis.delete',
        'approvals.delete',
        // Correcting an RFI after it has closed and been mailed out. Identical
        // in kind to meetings.revise, and held the same way.
        'rfis.revise',

        // --- new, no counterpart today: start closed ------------------------
        // The permission module itself — never granted by a seed.
        'access.view', 'access.manage',
        // Company-wide "may add anyone to any project". Project templates hand
        // out team.view / team.invite per project instead (M1).
        'team.view', 'team.invite', 'team.manage',
        // New in the procurement-assignment module. Naming the person every
        // approved requisition is handed to is a scheduling decision, and it
        // has no counterpart in the old rules — so these start closed at the
        // role level and reach people through the project templates below,
        // which is where "the manager of THIS project decides" belongs.
        'assignment-defaults.view', 'assignment-defaults.edit',
        // Handing one requisition to a buyer, and sharing out a round. Same
        // reasoning, and neither is implied by the grant beside it: approving
        // says the company will buy this, assigning says who goes and gets the
        // prices, and a procurement lead who may not approve spend still
        // shares the work out among their own people.
        'requisitions.assign',
        'quotations.assign',
        // New in this module; who may freeze a budget is decided in M6.
        'budget.lock',
        // New in this module; who may reverse a payment is decided in M11.
        'payments.refund',
    ];

    /**
     * Held back from `employee` only — admin **or manager** today:
     * `canReviewRequisitions()`, the award and the conversion, the document
     * repository's write side (`canManageDocuments()`, `canSeeInternalDocuments()`)
     * and the meeting series screen.
     */
    protected const MANAGER_ONLY_ABILITIES = [
        'requisitions.approve',
        'quotations.award',
        'quotations.convert',
        // New in M8. `convert_contract` reproduces today's behaviour, where
        // whoever could convert could convert to either target. Standalone
        // rounds are a TIGHTENING: an employee can raise one today, and after
        // M8 cannot — that is the point of N1. A manager keeps it, because a
        // manager can approve the requisition they would otherwise have
        // needed, so nothing is being walked around.
        'quotations.convert_contract',
        'quotations.create_standalone',
        // New in M10. A TIGHTENING: today anybody who can reach the change
        // orders screen can approve one, which is what moves the cost budget
        // (docs/permissions-notes.md §4b). A manager keeps it; an employee
        // raises the change and somebody else decides on it.
        'change-orders.approve',
        'documents.create',
        'documents.edit',
        'documents.share',
        'documents.see_internal',
        'meetings.manage_series',
        // Manager-or-above today: MeetingForm and MeetingAgenda both required
        // it to open at all, and MeetingIndex hid the New Meeting button.
        'meetings.create',
        'meetings.edit',
        // Publishing freezes the minute and mails it to every attendee. It had
        // no guard at all; held to the same people who may run the meeting.
        'meetings.freeze',
        'documentation.create',
        'documentation.edit',

        // New at F2. The second layer of the task guards, which the model
        // spelled `is_admin || is_manager` until the bridge came out. An
        // employee keeps every task of their own, and the ones they raised.
        'tasks.edit_any',

        // --- Collaboration (RFIs and approvals), new in this module ---------
        // Closing an RFI freezes its answer, the same weight meetings.freeze
        // carries. An employee raises and answers; somebody else closes.
        'rfis.close',
        // Recording the coded response is the decision that ends a revision
        // cycle — the same reasoning as change-orders.approve: an employee
        // submits the revision, somebody else decides on it.
        'approvals.respond',
        // Reads the orçamento line by line to pre-check what needs approving,
        // and creates records in bulk.
        'approvals.seed',
        // The same rule as meetings.manage_series.
        'approvals.manage_packages',
        // Both of these mail a document to people outside the company.
        'rfis.distribute',
        'approvals.distribute',
    ];

    /**
     * Deliberately granted to BOTH roles because they are ungated today, and
     * tightening them is a decision for the module's own pass rather than for
     * this seed. Listed here so the looseness is on the record and not an
     * oversight — see docs/permissions-notes.md §4b.
     *
     *   (change-orders.delete stays on both roles; approve, approve_own and
     *    unapprove were decided in M10 and are held back above)
     *   contracts.delete, budget.delete                 → left on both roles
     *                                                      (M11 and M6 decided
     *                                                      to reproduce)
     *   clients.delete, catalog.delete                  → M16
     *   estimates.delete, invoices.delete               → M15
     *   payments.pay, payments.batch                    → M11
     */

    public function run(bool $force = false): void
    {
        $this->syncSystemTemplates($force);
        $this->seedRoleAbilities($force);
        $this->grantAbilitiesOfNewAreas();
        $this->backfillMemberships();
    }

    /**
     * Hand a role the abilities of an area that did not exist when it was
     * seeded — the case that arrives on every deploy that adds a module to the
     * catalogue.
     *
     * An area is only ever offered **once**. What the role has been offered is
     * recorded on the role itself, because "holds nothing from this area" does
     * not mean "has never seen it": it also describes an area somebody
     * deliberately emptied, and handing those back on the next deploy would
     * quietly undo an administrator's decision. That happened in testing,
     * which is why the record exists.
     *
     * @return array<string, array<int, string>>  role name → abilities added
     */
    public function grantAbilitiesOfNewAreas(): array
    {
        $added = [];

        foreach (['manager' => $this->managerAbilities(), 'employee' => $this->employeeAbilities()] as $name => $seed) {
            $role = Role::where('name', $name)->first();

            if (! $role || ! $role->abilityRows()->exists()) {
                continue;   // never seeded at all: seedRoleAbilities() owns it
            }

            $offered = $role->seededAreas();

            $new = array_values(array_filter(
                $seed,
                fn ($ability) => ! in_array(AbilityCatalog::split($ability)[0], $offered, true),
            ));

            if ($new === []) {
                continue;
            }

            foreach ($new as $ability) {
                $role->abilityRows()->firstOrCreate(['ability' => $ability]);
            }

            $role->markAreasSeeded($this->areasOf($new));
            $role->unsetRelation('abilityRows');

            $added[$name] = $new;
        }

        return $added;
    }

    /**
     * The distinct areas a list of abilities belongs to.
     *
     * @return array<int, string>
     */
    protected function areasOf(array $abilities): array
    {
        return array_values(array_unique(array_map(
            fn ($ability) => AbilityCatalog::split($ability)[0],
            $abilities,
        )));
    }

    /*
    |---------------------------------------------------------------------------
    | 1. System templates
    |---------------------------------------------------------------------------
    */

    public function syncSystemTemplates(bool $force = false): int
    {
        $created = 0;

        foreach ($this->systemTemplates() as $key => $definition) {
            $template = PermissionTemplate::where('key', $key)->first();

            if ($template && ! $force) {
                continue;
            }

            $template ??= new PermissionTemplate(['key' => $key]);

            $template->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'level' => $definition['level'],
                'is_guest' => $definition['is_guest'] ?? false,
                'is_system' => true,
                'can_see_money' => $definition['can_see_money'] ?? true,
            ])->save();

            $template->syncAbilities(
                AbilityCatalog::filter($definition['abilities'], $definition['level'])
            );

            $created++;
        }

        return $created;
    }

    /**
     * The presets an install starts with. Abilities are filtered against the
     * catalogue on the way in, so a typo here cannot create a phantom grant.
     */
    protected function systemTemplates(): array
    {
        return [
            'project-manager' => [
                'name' => 'Project Manager',
                'description' => 'Runs the project: everything except the destructive actions and the permission screens.',
                'level' => 'project',
                'can_see_money' => true,
                'abilities' => [
                    'project.view', 'project.edit',
                    'team.view', 'team.invite',
                    'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.pay',
                    'income.view', 'income.create', 'income.edit', 'income.distribute',
                    'requisitions.view', 'requisitions.create', 'requisitions.edit',
                    'requisitions.submit', 'requisitions.approve', 'requisitions.duplicate',
                    'requisitions.assign',
                    'assignment-defaults.view', 'assignment-defaults.edit',
                    'quotations.view', 'quotations.create', 'quotations.edit',
                    'quotations.create_standalone',
                    'quotations.award', 'quotations.convert', 'quotations.convert_contract',
                    'quotations.assign',
                    'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.edit',
                    'purchase-orders.approve', 'purchase-orders.receive',
                    'change-orders.view', 'change-orders.create', 'change-orders.edit',
                    'change-orders.approve',
                    'contracts.view', 'contracts.create', 'contracts.edit',
                    'contracts.measure', 'contracts.pay',
                    'documents.view', 'documents.create', 'documents.edit',
                    'documents.share', 'documents.see_internal',
                    'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.close',
                    'meetings.view', 'meetings.create', 'meetings.edit', 'meetings.freeze',
                    'rfis.view', 'rfis.create', 'rfis.edit', 'rfis.answer',
                    'rfis.close', 'rfis.view_impact', 'rfis.export', 'rfis.distribute',
                    'approvals.view', 'approvals.create', 'approvals.edit',
                    'approvals.submit', 'approvals.respond', 'approvals.seed',
                    'approvals.manage_packages', 'approvals.export', 'approvals.distribute',
                    'daily-reports.view', 'daily-reports.create', 'daily-reports.edit',
                    'budget.view', 'budget.create', 'budget.edit',
                    'project-report.view', 'project-report.export',
                ],
            ],

            'procurement' => [
                'name' => 'Procurement',
                'description' => 'Buys: raises requisitions, runs quotation rounds and purchase orders. Does not approve them.',
                'level' => 'project',
                'can_see_money' => true,
                'abilities' => [
                    'project.view',
                    'requisitions.view', 'requisitions.create', 'requisitions.edit',
                    'requisitions.submit', 'requisitions.duplicate',
                    'requisitions.assign',
                    'assignment-defaults.view',
                    'quotations.view', 'quotations.create', 'quotations.edit',
                    'quotations.assign',
                    'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.edit',
                    'purchase-orders.receive',
                    'contracts.view',
                    'budget.view',
                    'documents.view', 'documents.create',
                    'tasks.view',
                ],
            ],

            'accounting' => [
                'name' => 'Accounting',
                'description' => 'Sees the money and settles it: pays expenses and contract instalments, reads the budget and the report.',
                'level' => 'project',
                'can_see_money' => true,
                'abilities' => [
                    'project.view',
                    'expenses.view', 'expenses.pay',
                    'income.view',
                    'purchase-orders.view',
                    'contracts.view', 'contracts.pay',
                    'budget.view',
                    'project-report.view', 'project-report.export',
                    'documents.view',
                ],
            ],

            'client-project' => [
                'name' => 'Client (read only)',
                'description' => 'An outsider following the project: documents, daily reports and tasks, with no monetary figures.',
                'level' => 'project',
                'is_guest' => true,
                'can_see_money' => false,
                'abilities' => [
                    'project.view',
                    'documents.view',
                    'daily-reports.view',
                    'tasks.view',
                ],
            ],

            // The projetista, the fornecedor, the fiscalização — the outside
            // party who answers the question or reviews the sample. In Brazil
            // this is normally who an RFI is addressed to, so the module is
            // not much use without this template.
            //
            // Note what is NOT in the list: `rfis.view_impact`. Whether a
            // question carries a cost or a delay is the company's business,
            // not the designer's, and leaving the ability out is what keeps it
            // that way — no view conditional to forget in a later refactor.
            // `can_see_money => false` covers the figures; this covers the fact.
            'projetista-project' => [
                'name' => 'Projetista (external)',
                'description' => 'An outside designer or supplier: answers RFIs and reviews approvals on one project, with no monetary figures and no cost or schedule impact.',
                'level' => 'project',
                'is_guest' => true,
                'can_see_money' => false,
                'abilities' => [
                    'project.view',
                    'rfis.view', 'rfis.answer',
                    'approvals.view', 'approvals.respond',
                    'documents.view',
                ],
            ],

            'site-supervisor' => [
                'name' => 'Site Supervisor',
                'description' => 'Runs one job site: daily reports, expenses, requisitions and site documents. No monetary totals.',
                'level' => 'job_site',
                'can_see_money' => false,
                'abilities' => [
                    'project.view',
                    'team.view',
                    'expenses.view', 'expenses.create', 'expenses.edit',
                    'requisitions.view', 'requisitions.create',
                    'requisitions.submit', 'requisitions.duplicate',
                    'daily-reports.view', 'daily-reports.create', 'daily-reports.edit',
                    'documents.view', 'documents.create',
                    'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.close',
                    'meetings.view',
                    // Raises the questions and submits the samples; does not
                    // close an RFI or record a response on either.
                    'rfis.view', 'rfis.create', 'rfis.view_impact',
                    'approvals.view', 'approvals.submit',
                ],
            ],

            'site-team' => [
                'name' => 'Site Team',
                'description' => 'Works on one job site: files the daily report and keys in expenses. Sees nothing else.',
                'level' => 'job_site',
                'can_see_money' => false,
                'abilities' => [
                    'project.view',
                    'expenses.view', 'expenses.create',
                    'daily-reports.view', 'daily-reports.create',
                    'documents.view',
                    'tasks.view', 'tasks.edit',
                ],
            ],

            'client-job-site' => [
                'name' => 'Client (read only)',
                'description' => 'An outsider following one job site: documents and daily reports, with no monetary figures.',
                'level' => 'job_site',
                'is_guest' => true,
                'can_see_money' => false,
                'abilities' => [
                    'project.view',
                    'documents.view',
                    'daily-reports.view',
                ],
            ],
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | 2. Role abilities
    |---------------------------------------------------------------------------
    */

    public function seedRoleAbilities(bool $force = false): array
    {
        $seeded = [];

        foreach (['manager' => $this->managerAbilities(), 'employee' => $this->employeeAbilities()] as $name => $abilities) {
            $role = Role::where('name', $name)->first();

            if (! $role) {
                continue;
            }

            // Only seed a role nobody has customised yet.
            if ($role->abilityRows()->exists() && ! $force) {
                continue;
            }

            $role->syncAbilities($abilities);
            $role->markAreasSeeded($this->areasOf($abilities));
            $seeded[$name] = count($abilities);
        }

        return $seeded;
    }

    /**
     * Manager: everything except what is admin-only today, plus the
     * company-wide money visibility that everybody has right now.
     */
    protected function managerAbilities(): array
    {
        $abilities = array_diff(
            AbilityCatalog::abilities(),
            self::ADMIN_ONLY_ABILITIES,
        );

        $abilities[] = AbilityCatalog::financeAbility();

        return array_values($abilities);
    }

    /**
     * Employee: the manager's list, less the reviews and the document
     * repository's write side, which are admin-or-manager today.
     */
    protected function employeeAbilities(): array
    {
        return array_values(array_diff(
            $this->managerAbilities(),
            self::MANAGER_ONLY_ABILITIES,
        ));
    }

    /*
    |---------------------------------------------------------------------------
    | 3. Backfill — the two reporting labels become real access
    |---------------------------------------------------------------------------
    */

    public function backfillMemberships(): array
    {
        $counts = ['projects' => 0, 'job_sites' => 0];

        $pmTemplate = PermissionTemplate::where('key', 'project-manager')->first();
        $supervisorTemplate = PermissionTemplate::where('key', 'site-supervisor')->first();

        Project::whereNotNull('project_manager_id')
            ->get(['id', 'project_manager_id'])
            ->each(function (Project $project) use ($pmTemplate, &$counts) {
                if ($this->ensureMembership($project, $project->project_manager_id, $pmTemplate)) {
                    $counts['projects']++;
                }
            });

        JobSite::whereNotNull('supervisor_id')
            ->get(['id', 'supervisor_id'])
            ->each(function (JobSite $jobSite) use ($supervisorTemplate, &$counts) {
                if ($this->ensureMembership($jobSite, $jobSite->supervisor_id, $supervisorTemplate)) {
                    $counts['job_sites']++;
                }
            });

        return $counts;
    }

    /**
     * Create the membership if this person does not already have one here.
     * Never touches an existing membership — somebody may have tuned it.
     */
    protected function ensureMembership($scopeable, int $userId, ?PermissionTemplate $template): bool
    {
        $exists = Membership::where('user_id', $userId)
            ->where('scopeable_type', $scopeable::class)
            ->where('scopeable_id', $scopeable->getKey())
            ->exists();

        if ($exists) {
            return false;
        }

        $membership = Membership::create([
            'user_id' => $userId,
            'scopeable_type' => $scopeable::class,
            'scopeable_id' => $scopeable->getKey(),
            'permission_template_id' => $template?->id,
            'can_see_money' => $template?->can_see_money ?? true,
            'status' => MembershipStatus::ACTIVE,
            'accepted_at' => now(),
        ]);

        if ($template) {
            $membership->syncAbilities($template->abilities());
        }

        return true;
    }
}
