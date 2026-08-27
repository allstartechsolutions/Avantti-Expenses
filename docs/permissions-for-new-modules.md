# Permissions for a new module

**Read this before you write the first screen of a new module, not after.**

The permission module is finished (`docs/permissions-module.md`). Retro-fitting permissions
onto eighteen modules that were built without them took a week and found forty-odd holes. A
module built *with* them costs about twenty minutes, and this file is those twenty minutes.

The short version:

> **A new module is not finished when its screens work. It is finished when its area is in
> the catalogue, every action guards itself, every list filters itself, and a test proves a
> stranger is refused.**

---

## 1. Declare the area — `config/permissions.php`

Nothing exists until the catalogue says so. `Gate::before` refuses any ability that is not
declared, so a typo is a denial rather than a hole.

```php
'deliveries' => [
    'name'   => 'Deliveries',
    'module' => 'projects',                        // config/modules.php key; the customer's switch
    'levels' => ['global', 'project', 'job_site'], // where it can be granted
    'money'  => true,                              // does this area put figures on screen?
    'swept'  => false,                             // ← see §8. Flip to true when the pass is done.
    'actions' => [
        'view', 'create', 'edit', 'delete',
        'sign_off' => ['name' => 'Sign off a delivery', 'sensitive' => true, 'limited' => true],
    ],
],
```

**Choosing `levels` is the decision that matters most.** Get it wrong and the rest fights you.

| If a record… | levels |
|---|---|
| belongs to a project or a job site | `['global', 'project', 'job_site']` |
| belongs to a project but never a site | `['global', 'project']` |
| belongs to the company — a client, an invoice, a catalog item | `['global']` |

`global` in the list means "grantable on a role"; `project` / `job_site` mean "grantable on a
membership". Almost every area that lives inside a project wants all three: the role is how a
company-wide person gets it everywhere, and the membership is how a confined person gets it on
one site.

**Action shorthand:** a bare string uses the shared label from `config/permissions.actions`.
Give an action its own array when it needs a real name, or one of:

- `sensitive => true` — the matrix shows an amber dot. Use it for anything that hands out
  access, sends something outside the company, or undoes a signed-off record.
- `limited => true` — the action obeys the approval ceiling. Use it for anything that
  commits or releases money, and then call `authorizeAbilityWithin()` (§3).

**Name actions after what the user does, not after the HTTP verb.** `sign_off`, `unpay`,
`edit_locked`, `approve_own` — each of those exists because the plain `edit` or `approve` did
not describe a narrower thing somebody needed to grant separately.

### Add the menu entry too

If the module has a sidebar entry or a project tab, declare it in the same file's `menu` /
`tabs` blocks. `Navigation` builds both from the catalogue, so an entry appears because its
ability is held and its module is on — never because a Blade file remembered to hide it.

A new tab needs two more things beyond its route and ability:

- **A `group`** — one of the four in `tab_groups` (`financial`, `procurement`,
  `collaboration`, `field`), or `null` to sit flat in the bar beside Overview and Team. Only
  a tab that belongs to no group at all stays flat; the bar is grouped precisely so that it
  does not grow a nineteenth flat tab.
- **Its label in `lang/en/navigation.php` and `lang/pt_BR/navigation.php`**, keyed by the tab
  key. Without the pt_BR line the tab silently falls back to English, which reads as
  translated and is not.

---

## 2. Seed it — `database/seeders/PermissionSeeder.php`

`permissions:sync` offers a **newly declared area** to the seeded roles once, so by default
`manager` and `employee` get every action of it. Decide whether that is right, and hold back
what is not:

- Add to **`ADMIN_ONLY_ABILITIES`** anything only an administrator should have.
- Add to **`MANAGER_ONLY_ABILITIES`** anything an employee should not have.

> **The one mistake to avoid.** If the code you are replacing had a hard-coded `is_admin`
> anywhere, the matching ability **must** be in `ADMIN_ONLY_ABILITIES`. F2 nearly shipped task
> deletion to every employee in the company for exactly this reason: `Task::canDelete()` was
> `is_admin`, and `tasks.delete` was not on the list.

Write a one-line comment next to each saying *why* it is held back. Every entry in those two
lists has one; they are the record of a decision, not a preference.

If the module should appear in a system template ("Site Supervisor", "Procurement"), add its
abilities to `systemTemplates()` in the same file.

---

## 3. Guard every action — the component

Use `AuthorizesAbility`. **Guard `mount()` and every method a `wire:click` can reach**, not
just the ones that look dangerous.

```php
use App\Livewire\Concerns\AuthorizesAbility;

class ProjectDeliveries extends Component
{
    use AuthorizesAbility;

    public function mount(Project $project): void
    {
        $this->authorizeAbility('deliveries.view', $project);
    }

    public function signOff(int $id): void
    {
        $delivery = Delivery::findOrFail($id);

        // NEVER act on an id from the browser without checking which project it
        // is on. `scopeOf()` walks any model to its governing project or site.
        $this->authorizeAbilityWithin(
            'deliveries.sign_off',
            $delivery->totalInCents(),
            app(PermissionResolver::class)->scopeOf($delivery),
        );

        // …
    }
}
```

| Method | Use it for |
|---|---|
| `authorizeAbility($ability, $scope)` | Everything. 403s if not allowed. |
| `authorizeAbilityWithin($ability, $cents, $scope)` | An action marked `limited` — checks the ability *and* the ceiling. |
| `authorizeAnyAbility([...], $scope)` | A screen two different grants can open. |
| `allowsAbility($ability, $scope)` | Deciding what to render. **Never a substitute for a guard.** |
| `allowsMoney($scope)` | Whether to show a roll-up. |

### Four traps, all of which have bitten

1. **Hiding a button is not protection.** The `wire:click` behind it is a public HTTP
   endpoint. The `@can` in the view is cosmetics; the guard in the method is the security.
2. **A nested component's `mount()` may only ask for what its parent already required**
   (P30). A panel embedded in a detail page that demands `deliveries.sign_off` will 403 the
   whole page for a reader who is entitled to it. Guard the `@can` around the
   `<livewire:…>` embed *and* the action, not the child's `mount()`.
3. **Guard the way in, not the way out.** If leaving a mode calls a reset method, guarding
   that method refuses somebody on the way out of a state they should never have been in.
   Split it: a guarded public method and an unguarded protected one.
4. **The id comes from the browser.** `findOrFail($id)` tells you the record exists, not that
   this person may touch it.

### Controllers and routes

A component with no `mount()` — or a plain controller — gets the route middleware instead:

```php
Route::get('deliveries', DeliveryIndex::class)
    ->middleware('ability:deliveries.view')->name('deliveries.index');

// Scoped to a route parameter:
Route::get('projects/{project}/deliveries', ProjectDeliveries::class)
    ->middleware('ability:deliveries.view,project');
```

**A PDF controller is a screen.** Every one of them needs the same grant its screen needs — a
PDF of a report somebody may not open is the same disclosure through another door. This was
the single most repeated finding in the whole module (P22).

**A file download is a screen too.** If the module stores files, add its directory to
`FileController::authorizeFile()`. Path-based access with no check is how eight directories
leaked before M12.

---

## 4. Filter every list — the model

A guard answers "may you open this record?". A list has to answer "which records may you
see?" — and a guard cannot do that.

Any query that crosses projects needs a `visibleTo` scope:

```php
public function scopeVisibleTo(Builder $query, ?User $user): Builder
{
    if (! $user || ! $user->isActive()) {
        return $query->whereRaw('1 = 0');
    }

    if ($user->is_admin || ! $user->isConfined()) {
        return $query;      // no filter — and no expensive whereIn over every project
    }

    return $query->whereIn('project_id', Project::visibleTo($user)->select('id'));
}
```

Returning the query untouched for a company-wide person matters: building a `whereIn` over
every project in the install is an expensive way of writing `1 = 1`.

**Aggregates count as lists.** A card that totals money across projects somebody cannot open
tells them something the project list would not. Narrow the sum, not just the rows.

---

## 5. Money on screen

Every monetary figure goes through the component, never `Number::currency()` directly:

```blade
{{-- A record's own amount: shown to anybody allowed on the screen --}}
<x-ui.money :amount="$delivery->total" />

{{-- A roll-up — a total, a budget, a margin: hidden where money is hidden --}}
<x-ui.money :amount="$project->total" :scope="$project" rollup />
```

**The rule, and it is the owner's:** `can_see_money` hides **roll-ups, not records**. What a
project adds up to is the company's financial picture; the amount on a delivery somebody
signed for is not a secret from them.

If a figure is a roll-up over several scopes at once — the dashboard is the only case so far —
work the answer out in the component and pass `:visible="$flag"`.

---

## 6. Write the test

One file, `tests/Feature/Permissions/DeliveryTest.php`. Copy the shape from any existing one;
`ExpensesTest` is the fullest. Four groups, and the first is the one people skip:

```php
// 1. REPRODUCED — every seeded role answers as it did before.
public function test_the_screens_answer_as_they_did_for_every_role(): void

// 2. REVOCABLE — a role without the grant is refused, which was never true before.
public function test_the_area_can_now_be_taken_away(): void

// 3. SCOPED — a member of one project cannot reach another's, by URL or by id.
public function test_a_delivery_on_another_project_is_not_reachable_by_its_id(): void

// 4. SEPARATE — each action is its own grant; holding one gives none of the others.
public function test_signing_off_is_held_apart_from_editing(): void
```

Also add the module's routes to whatever sweeps exist — `ConfinementTest` enumerates the
router itself, so a new project-scoped route is covered the moment it is written, and it will
**fail** until the route is guarded. That is the intended behaviour, not a nuisance.

### Two testing gotchas that cost hours

- **Livewire full-page components:** `Livewire::test(X::class, ['project' => $p, 'jobSite' => null])`
  breaks. An unpassed `?Model $x = null` gets container-resolved into a blank truthy model.
  Pass only the key you mean, and guard with `$x?->exists`.
- **MySQL-only SQL means untested.** `FIELD()`, `DATE_FORMAT()` and friends 500 on sqlite, so
  the test that would have caught the bug never renders the screen. Write portable SQL, or a
  `match (DB::connection()->getDriverName())` helper.

---

## 7. pt_BR, in the same change

Every `__()` string added gets its pt_BR line in `lang/pt_BR.json` **in the same commit**.
Append textually — rewriting the file through a JSON library has silently dropped duplicate
keys before.

---

## 8. Flip `swept` and run the sweep

`swept => false` means "declared but not enforced yet". The permission matrix marks the area
*not enforced yet* so nobody hands out a grant that does nothing.

Flip it to `true` only when §§3–6 are all done for that area. Then:

```bash
php artisan test tests/Feature/Permissions/
```

`LegacyBehaviourTest` and `SecurityStateTest` will fail until you add the area to their lists.
**That failure is the point** — it makes you state, in writing, that the new area answers the
way you meant it to.

---

## The checklist

Copy this into the module's plan document and tick it off.

- [ ] Area declared in `config/permissions.php` — `levels`, `money`, actions, `swept => false`
- [ ] Menu entry / project tab declared in the same file
- [ ] `ADMIN_ONLY_ABILITIES` / `MANAGER_ONLY_ABILITIES` updated, each with a reason
- [ ] Added to a system template if it belongs in one
- [ ] `mount()` guarded on every component
- [ ] **Every** action method guarded — not just the destructive ones
- [ ] `limited` actions use `authorizeAbilityWithin()`
- [ ] Records fetched by id checked against their own scope, never the screen's
- [ ] Routes without a `mount()` carry `ability:` middleware
- [ ] **Every PDF controller guarded with the same grant as its screen**
- [ ] File directories added to `FileController::authorizeFile()`
- [ ] `visibleTo()` on any model listed across projects; aggregates narrowed too
- [ ] Money through `<x-ui.money>`, `rollup` on totals
- [ ] Views use `@can` for cosmetics — and never *instead of* a guard
- [ ] `tests/Feature/Permissions/<Module>Test.php`: reproduced, revocable, scoped, separate
- [ ] pt_BR strings added in the same change
- [ ] `swept => true`, bookkeeping tests updated, full suite green

---

## Where things live

| | |
|---|---|
| The catalogue | `config/permissions.php` |
| The one decision-maker | `app/Services/PermissionResolver.php` |
| Component guards | `app/Livewire/Concerns/AuthorizesAbility.php` |
| Route middleware | `app/Http/Middleware/EnsureUserHasAbility.php` |
| Menu and tabs | `app/Services/Navigation.php` |
| Seeds and templates | `database/seeders/PermissionSeeder.php` |
| Money on screen | `resources/views/components/ui/money.blade.php` |
| What was built, and why | `docs/permissions-module.md`, `docs/permissions-module-plan.md` |
| Open notations | `docs/review-and-improvements.md` |
