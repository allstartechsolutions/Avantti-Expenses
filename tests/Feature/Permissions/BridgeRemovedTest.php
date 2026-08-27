<?php

namespace Tests\Feature\Permissions;

use App\Models\DocArticle;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F2 — the legacy bridge, and everything that propped it up, removed.
 *
 * The bridge was the thing that made this module deployable one screen at a
 * time: an area that had not had its permission pass still answered from the
 * old role checks, and a confined person was denied outright rather than
 * half-served. Every area has had its pass, so the branch is gone.
 *
 * With it went `AuthorizesAdmin`, the `@admin` Blade directive, the `admin`
 * route middleware, `EnsureUserIsAdmin` and the four `can…()` helpers on `User`
 * that asked what role somebody held.
 *
 * **The criterion is a grep, and this file is that grep.** It is written as a
 * test rather than a note because the failure mode is somebody adding a role
 * check back, and nobody re-runs a note.
 */
class BridgeRemovedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Nothing is left on the bridge, and the bridge is gone
    |---------------------------------------------------------------------------
    */

    public function test_every_area_decides_for_itself(): void
    {
        $this->assertSame(
            self::AREAS_UNDER_CONSTRUCTION,
            array_values(AbilityCatalog::unsweptAreas()),
        );

        // The branch that read this is deleted, so the flag now only feeds the
        // permission matrix's "not enforced yet" marker for a module added
        // later. Proved by reading the resolver: no reference to it survives.
        $this->assertStringNotContainsString(
            'isSwept',
            $this->sourceOf('app/Services/PermissionResolver.php'),
        );
    }

    public function test_the_pieces_the_bridge_leaned_on_are_deleted(): void
    {
        foreach ([
            'app/Livewire/Concerns/AuthorizesAdmin.php',
            'app/Http/Middleware/EnsureUserIsAdmin.php',
        ] as $path) {
            $this->assertFileDoesNotExist(base_path($path), $path);
        }

        // The Blade directive, and the middleware alias that named it.
        $this->assertStringNotContainsString(
            "Blade::if('admin'",
            $this->sourceOf('app/Providers/AppServiceProvider.php'),
        );
        $this->assertStringNotContainsString(
            "'admin' =>",
            $this->sourceOf('bootstrap/app.php'),
        );

        // And the helpers on User that asked what role somebody held.
        $source = $this->sourceOf('app/Models/User.php');

        foreach ([
            'function isManager',
            'function canReviewRequisitions',
            'function canManageDocuments',
            'function canDeleteDocuments',
            'function canSeeInternalDocuments',
        ] as $gone) {
            $this->assertStringNotContainsString($gone, $source, $gone);
        }
    }

    public function test_no_role_check_is_left_anywhere_but_the_admin_rule(): void
    {
        // `is_admin` survives, in exactly nine places and for exactly one
        // reason: **an administrator is allowed everything, is never confined
        // and is never capped.** That is the resolver's own step 3, applied
        // where the resolver cannot be asked — inside a query scope, or in a
        // screen explaining itself.
        //
        // Anything else — `is_manager`, `@admin`, `authorizeAdmin` — is a role
        // check by another name, and there are none left.
        $offenders = [];

        foreach ($this->applicationFiles() as $path) {
            $source = $this->sourceOf($path);

            foreach (['is_manager', 'authorizeAdmin', 'AuthorizesAdmin', '@admin', '@endadmin'] as $needle) {
                foreach ($this->codeLines($source) as $number => $line) {
                    if (str_contains($line, $needle)) {
                        $offenders[] = "{$path}:{$number} — {$needle}";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'A role check came back.');
    }

    public function test_the_admin_rule_has_not_spread(): void
    {
        $found = [];

        foreach ($this->applicationFiles() as $path) {
            if ($path === 'app/Services/PermissionResolver.php') {
                continue;   // where the rule belongs
            }

            foreach ($this->codeLines($this->sourceOf($path)) as $number => $line) {
                if (str_contains($line, 'is_admin')) {
                    $found[] = $path;
                }
            }
        }

        // Pinned, so a tenth place has to be a decision rather than a habit.
        //
        // Rfi.php and Approval.php joined at the collaboration module, and for
        // the reason the other three models are here: `visibleTo()` has to agree with the
        // guard. The resolver bypasses ability checks for an administrator, so
        // one who had been given `access_scope = assigned` could open any RFI
        // through a guard while the list refused to show it. A list and a guard
        // that disagree is worse than either answer on its own.
        $this->assertSame([
            'app/Livewire/Access/ApprovalAuthority.php',
            'app/Livewire/Dashboard/DashboardIndex.php',
            'app/Livewire/User/UserAccess.php',
            'app/Models/Approval.php',
            'app/Models/Document.php',
            'app/Models/JobSite.php',
            'app/Models/Project.php',
            'app/Models/Rfi.php',
            'app/Models/User.php',
        ], array_values(array_unique($found)));
    }

    /*
    |---------------------------------------------------------------------------
    | The last area swept: the documentation library
    |---------------------------------------------------------------------------
    */

    public function test_the_library_is_still_open_to_everybody_signed_in(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->user($role))->get(route('documentation.index'))->assertOk();
        }
    }

    public function test_writing_a_guide_is_still_manager_and_above(): void
    {
        $this->actingAs($this->user('manager'))->get(route('documentation.create'))->assertOk();
        $this->actingAs($this->user('employee'))->get(route('documentation.create'))->assertForbidden();
    }

    public function test_reading_the_library_can_now_be_taken_away(): void
    {
        // The point of sweeping it: an install that writes its own procedures
        // into the library can keep an outsider out of them.
        $role = Role::create(['name' => 'no-library-'.uniqid()]);
        $role->syncAbilities(['projects.view', 'project.view']);

        $shut = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($shut)->get(route('documentation.index'))->assertForbidden();
    }

    public function test_deleting_a_guide_is_still_administrator_only(): void
    {
        DocArticle::create([
            'title' => 'House rules',
            'slug' => 'house-rules',
            'category' => 'general',
            'body' => 'Be careful.',
            'is_published' => true,
            'created_by' => $this->user('admin')->id,
        ]);

        foreach (['manager', 'employee'] as $name) {
            $this->assertNotContains(
                'documentation.delete',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
                $name,
            );
        }
    }

    /*
    |---------------------------------------------------------------------------
    | The abilities F2 had to add so that nothing widened
    |---------------------------------------------------------------------------
    */

    public function test_the_new_grants_are_held_by_exactly_who_held_them_before(): void
    {
        $manager = Role::where('name', 'manager')->firstOrFail()->abilityRows()->pluck('ability')->all();
        $employee = Role::where('name', 'employee')->firstOrFail()->abilityRows()->pluck('ability')->all();

        // Deleting a task and correcting a published minute were hard-coded
        // `is_admin`. Without these two lines in the seed, converting them to
        // grants would have handed both to everybody.
        foreach (['tasks.delete', 'meetings.revise'] as $adminOnly) {
            $this->assertNotContains($adminOnly, $manager, $adminOnly);
            $this->assertNotContains($adminOnly, $employee, $adminOnly);
        }

        // `tasks.edit_any` is new: the second layer of the task guards, which
        // the model spelled `is_admin || is_manager`. A manager keeps it; an
        // employee keeps every task of their own and every one they raised.
        $this->assertContains('tasks.edit_any', $manager);
        $this->assertNotContains('tasks.edit_any', $employee);
    }

    /*
    |---------------------------------------------------------------------------
    | Helpers
    |---------------------------------------------------------------------------
    */

    /** @return array<int, string> repo-relative paths */
    protected function applicationFiles(): array
    {
        $paths = [];

        foreach ([base_path('app'), base_path('resources/views'), base_path('bootstrap')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (in_array($file->getExtension(), ['php'], true)) {
                    $paths[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        sort($paths);

        return $paths;
    }

    protected function sourceOf(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /**
     * The lines that are code rather than commentary, so the history left in
     * the comments does not read as a role check that survived.
     *
     * @return array<int, string>
     */
    protected function codeLines(string $source): array
    {
        $lines = [];

        foreach (explode("\n", $source) as $index => $line) {
            $trimmed = ltrim($line);

            if ($trimmed === ''
                || str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '/*')
                || str_starts_with($trimmed, '|')
                || str_starts_with($trimmed, '{{--')) {
                continue;
            }

            $lines[$index + 1] = $line;
        }

        return $lines;
    }
}
