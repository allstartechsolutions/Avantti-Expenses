<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Role;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;

/**
 * Brings the database in step with config/permissions.php.
 *
 * Run after every deploy of the permission module, next to `migrate`:
 *
 *     php artisan migrate --force && php artisan permissions:sync
 *
 * Safe to run repeatedly. It creates what is missing and reports what it
 * found; it does not overwrite a template or a role that somebody has edited
 * unless --force says so.
 */
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
                            {--force : Re-seed the system templates and the manager/employee roles, discarding local edits}
                            {--prune : Delete grants naming an ability that no longer exists in the catalogue}';

    protected $description = 'Sync the permission templates, role abilities and backfilled memberships with the catalogue';

    public function handle(PermissionSeeder $seeder): int
    {
        $force = (bool) $this->option('force');

        if ($force && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $templates = $seeder->syncSystemTemplates($force);
        $this->info($templates > 0
            ? "Templates: {$templates} written."
            : 'Templates: already present, nothing written.');

        $roles = $seeder->seedRoleAbilities($force);
        foreach ($roles as $role => $count) {
            $this->info("Role '{$role}': {$count} abilities seeded.");
        }
        if ($roles === []) {
            $this->info('Roles: already carry abilities, nothing written.');
        }

        foreach ($seeder->grantAbilitiesOfNewAreas() as $role => $abilities) {
            $this->info("Role '{$role}': ".count($abilities).' ability(ies) added for areas that did not exist before.');
        }

        $backfilled = $seeder->backfillMemberships();
        $this->info(sprintf(
            'Memberships backfilled: %d project manager(s), %d supervisor(s).',
            $backfilled['projects'],
            $backfilled['job_sites'],
        ));

        if ($this->option('prune')) {
            $this->prune();
        }

        $this->newLine();
        $this->report();

        return self::SUCCESS;
    }

    /**
     * Remove grants naming an ability the catalogue no longer declares — the
     * leftovers of a renamed or dropped area.
     */
    protected function prune(): void
    {
        $valid = AbilityCatalog::abilities();

        $roles = \App\Models\RoleAbility::whereNotIn('ability', $valid)->delete();
        $templates = \App\Models\PermissionTemplateAbility::whereNotIn('ability', $valid)->delete();
        $members = \App\Models\MembershipAbility::whereNotIn('ability', $valid)->delete();

        $this->warn("Pruned unknown abilities — roles: {$roles}, templates: {$templates}, memberships: {$members}.");
    }

    /**
     * Where the build is up to.
     *
     * `unswept` once meant "still runs on the old role checks"
     * (docs/permissions-module-plan.md §9.1). That bridge was deleted at F2 and
     * the set it described is permanently empty. What is left in it now is an
     * area declared for a module still being built: its abilities exist so the
     * screens can be written against them, and it flips when every action of it
     * is guarded and every list of it filtered. Nothing is reachable in the
     * meantime, so the wording says declared-not-enforced, not "on the bridge".
     */
    protected function report(): void
    {
        $areas = AbilityCatalog::areas();
        $unswept = AbilityCatalog::unsweptAreas();
        $swept = count($areas) - count($unswept);

        $this->line(sprintf(
            '<comment>Catalogue:</comment> %d areas, %d abilities. <comment>Swept:</comment> %d/%d.',
            count($areas),
            count(AbilityCatalog::abilities()),
            $swept,
            count($areas),
        ));

        if ($unswept !== []) {
            $this->line('<comment>Declared, not enforced yet:</comment> '.implode(', ', $unswept));
        }

        $this->line(sprintf(
            '<comment>Grants:</comment> %d role, %d template, %d membership.',
            \App\Models\RoleAbility::count(),
            \App\Models\PermissionTemplateAbility::count(),
            \App\Models\MembershipAbility::count(),
        ));

        $this->line(sprintf(
            '<comment>Records:</comment> %d role(s), %d template(s), %d membership(s).',
            Role::count(),
            PermissionTemplate::count(),
            Membership::count(),
        ));
    }

    protected function confirmToProceed(): bool
    {
        if (! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm('--force discards local edits to the system templates and the manager/employee roles. Continue?');
    }
}
