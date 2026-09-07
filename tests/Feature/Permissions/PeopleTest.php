<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\People\PeopleIndex;
use App\Livewire\People\PersonShow;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * People: one human across several subcontractors (docs/people-module.md).
 *
 * The employee rows stay under `vendors.*`; the person record — the link
 * between rows at different companies — answers to `people.view` for the
 * list and the page, and `people.link` for making, breaking and editing.
 */
class PeopleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeSubcontractor(string $name): Subcontractor
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => $name,
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return Subcontractor::findOrFail($vendor->id);
    }

    protected function makeEmployee(Subcontractor $company, array $attributes = []): SubcontractorEmployee
    {
        return SubcontractorEmployee::create(array_merge([
            'subcontractor_id' => $company->id,
            'name' => 'João Silva',
        ], $attributes));
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'People Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeContract(Project $project, SubcontractorEmployee $employee, float $amount = 1000.0): Contract
    {
        return Contract::create([
            'project_id' => $project->id,
            'subcontractor_id' => $employee->subcontractor_id,
            'subcontractor_employee_id' => $employee->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function confinedMemberOf(Project $project, array $abilities): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities($abilities);

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** Two rows at two companies, linked by the administrator. */
    protected function linkedPair(): array
    {
        $a = $this->makeSubcontractor('Company A');
        $b = $this->makeSubcontractor('Company B');
        $rowA = $this->makeEmployee($a, ['tax_id' => '123.456.789-09']);
        $rowB = $this->makeEmployee($b, ['tax_id' => '987.654.321-00']);

        $person = $rowA->linkWith($rowB, $this->admin, 'Same foreman');

        return [$a, $b, $rowA, $rowB, $person];
    }

    /*
    |---------------------------------------------------------------------------
    | Reproduced, then revocable
    |---------------------------------------------------------------------------
    */

    public function test_the_people_screens_answer_for_every_seeded_role(): void
    {
        [, , , , $person] = $this->linkedPair();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('people.index'))->assertOk();
            $this->actingAs($user)->get(route('people.show', $person))->assertOk();
        }
    }

    public function test_the_people_screens_can_be_taken_away(): void
    {
        [, , , , $person] = $this->linkedPair();

        $blind = $this->roleWith(['projects.view', 'project.view', 'vendors.view']);

        $this->actingAs($blind)->get(route('people.index'))->assertForbidden();
        $this->actingAs($blind)->get(route('people.show', $person))->assertForbidden();

        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'people.view']);

        $this->actingAs($reader)->get(route('people.index'))->assertOk();
        $this->actingAs($reader)->get(route('people.show', $person))->assertOk();
    }

    public function test_linking_is_held_apart_from_reading(): void
    {
        $a = $this->makeSubcontractor('Company A');
        $b = $this->makeSubcontractor('Company B');
        $rowA = $this->makeEmployee($a);
        $rowB = $this->makeEmployee($b);

        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.edit', 'people.view']);

        Livewire::actingAs($reader)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('startLink', $rowA->id)
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->set('linking_employee_id', $rowA->id)
            ->set('link_target_id', $rowB->id)
            ->call('linkEmployee')
            ->assertForbidden();

        $this->assertNull($rowA->fresh()->person_id);

        // The seeded employee reads but does not link; the manager does both.
        Livewire::actingAs($this->user('employee'))
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('startLink', $rowA->id)
            ->assertForbidden();

        Livewire::actingAs($this->user('manager'))
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('startLink', $rowA->id)
            ->set('link_target_id', $rowB->id)
            ->set('link_reason', 'Confirmed on site')
            ->call('linkEmployee')
            ->assertHasNoErrors();

        $this->assertNotNull($rowA->fresh()->person_id);
        $this->assertSame($rowA->fresh()->person_id, $rowB->fresh()->person_id);
        $this->assertSame('Confirmed on site', $rowB->fresh()->link_reason);
    }

    public function test_adding_an_employee_now_needs_the_vendor_edit_grant(): void
    {
        // Before this module the add-employee form had no guard of its own:
        // anybody who could open the vendor page could add a row. Deleting
        // already needed `vendors.edit`; adding and editing now match it.
        $a = $this->makeSubcontractor('Company A');

        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view']);

        Livewire::actingAs($reader)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->set('employee_name', 'Somebody')
            ->call('saveEmployee')
            ->assertForbidden();

        $this->assertSame(0, $a->employees()->count());

        $editor = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.edit']);

        Livewire::actingAs($editor)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('startEmployee')
            ->set('employee_name', 'Somebody')
            ->set('employee_tax_id', '111.222.333-44')
            ->set('employee_started_at', '2026-01-15')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $row = $a->employees()->first();
        $this->assertSame('Somebody', $row->name);
        $this->assertSame('111.222.333-44', $row->tax_id);
        $this->assertSame('2026-01-15', $row->started_at->toDateString());
    }

    /*
    |---------------------------------------------------------------------------
    | Scoped
    |---------------------------------------------------------------------------
    */

    public function test_an_employee_of_another_company_cannot_be_linked_through_this_page(): void
    {
        $a = $this->makeSubcontractor('Company A');
        $b = $this->makeSubcontractor('Company B');
        $c = $this->makeSubcontractor('Company C');
        $rowB = $this->makeEmployee($b);
        $rowC = $this->makeEmployee($c);

        // The id in `linking_employee_id` belongs to company B, but the page
        // is company A's: the row is not "own" and the call fails before
        // anything is written.
        try {
            Livewire::actingAs($this->admin)
                ->test(SubcontractorShow::class, ['subcontractor' => $a])
                ->set('linking_employee_id', $rowB->id)
                ->set('link_target_id', $rowC->id)
                ->call('linkEmployee');

            $this->fail('A row of another company was accepted.');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertNull($rowB->fresh()->person_id);
        $this->assertNull($rowC->fresh()->person_id);
        $this->assertSame(0, Person::count());

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('startLink', $rowB->id);
    }

    public function test_two_rows_at_the_same_company_are_never_linked(): void
    {
        $a = $this->makeSubcontractor('Company A');
        $one = $this->makeEmployee($a, ['name' => 'João Silva']);
        $two = $this->makeEmployee($a, ['name' => 'João Silva']);

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('startLink', $one->id)
            ->set('link_target_id', $two->id)
            ->call('linkEmployee')
            ->assertHasErrors(['link_target_id']);

        $this->assertNull($one->fresh()->person_id);
        $this->assertSame(0, Person::count());
    }

    public function test_the_person_page_shows_a_confined_member_only_their_own_projects_contracts(): void
    {
        [, , $rowA, $rowB, $person] = $this->linkedPair();

        $theirs = $this->makeProject('Theirs');
        $someoneElses = $this->makeProject('Someone else');

        $visible = $this->makeContract($theirs, $rowA);
        $hidden = $this->makeContract($someoneElses, $rowB);

        $member = $this->confinedMemberOf($theirs, ['project.view', 'contracts.view']);
        $member->role->syncAbilities(['people.view', 'vendors.view']);
        app(PermissionResolver::class)->flush();

        $this->actingAs($member)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee($visible->contract_number)
            ->assertDontSee($hidden->contract_number);

        // The administrator sees both.
        $this->actingAs($this->admin)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee($visible->contract_number)
            ->assertSee($hidden->contract_number);
    }

    /*
    |---------------------------------------------------------------------------
    | The link itself
    |---------------------------------------------------------------------------
    */

    public function test_linking_two_rows_creates_the_person_and_records_who_decided(): void
    {
        [, , $rowA, $rowB, $person] = $this->linkedPair();

        $this->assertSame(1, Person::count());
        $this->assertSame($person->id, $rowA->fresh()->person_id);
        $this->assertSame($person->id, $rowB->fresh()->person_id);
        $this->assertSame($this->admin->id, $rowA->fresh()->linked_by);
        $this->assertSame('Same foreman', $rowA->fresh()->link_reason);
        $this->assertNotNull($rowA->fresh()->linked_at);

        // Two tax ids, side by side — the fact the page exists to show.
        $this->assertCount(2, $person->fresh()->distinctTaxIds());

        // The name travels with the first row; each company keeps its own.
        $this->assertSame('João Silva', $person->name);
    }

    public function test_linking_a_third_row_joins_the_existing_person(): void
    {
        [, , $rowA, , $person] = $this->linkedPair();
        $c = $this->makeSubcontractor('Company C');
        $rowC = $this->makeEmployee($c, ['name' => 'Joao Silva']);

        $rowC->linkWith($rowA, $this->admin);

        $this->assertSame(1, Person::count());
        $this->assertSame($person->id, $rowC->fresh()->person_id);
        $this->assertSame(3, $person->employees()->count());
    }

    public function test_linking_rows_of_two_different_people_folds_them_into_one(): void
    {
        [, , $rowA, $rowB, $first] = $this->linkedPair();

        $c = $this->makeSubcontractor('Company C');
        $d = $this->makeSubcontractor('Company D');
        $rowC = $this->makeEmployee($c);
        $rowD = $this->makeEmployee($d);
        $second = $rowC->linkWith($rowD, $this->admin);

        $this->assertSame(2, Person::count());

        $rowA->fresh()->linkWith($rowC->fresh(), $this->admin, 'They are one');

        $this->assertSame(1, Person::count());
        $this->assertNull(Person::find($second->id));

        foreach ([$rowA, $rowB, $rowC, $rowD] as $row) {
            $this->assertSame($first->id, $row->fresh()->person_id);
        }
    }

    public function test_unlinking_down_to_one_row_dissolves_the_person(): void
    {
        [$a, , $rowA, $rowB, $person] = $this->linkedPair();

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('unlinkEmployee', $rowA->id);

        $this->assertNull($rowA->fresh()->person_id);
        $this->assertNull($rowB->fresh()->person_id);
        $this->assertNull(Person::find($person->id));

        // Both rows are still there — only the thread between them is gone.
        $this->assertSame(2, SubcontractorEmployee::count());
    }

    public function test_unlinking_one_of_three_keeps_the_other_two_together(): void
    {
        [, , $rowA, $rowB, $person] = $this->linkedPair();
        $c = $this->makeSubcontractor('Company C');
        $rowC = $this->makeEmployee($c);
        $rowC->linkWith($rowA, $this->admin);

        Livewire::actingAs($this->admin)
            ->test(PersonShow::class, ['person' => $person])
            ->call('unlinkEmployee', $rowC->id)
            ->assertHasNoErrors();

        $this->assertNull($rowC->fresh()->person_id);
        $this->assertSame($person->id, $rowA->fresh()->person_id);
        $this->assertSame($person->id, $rowB->fresh()->person_id);
        $this->assertNotNull(Person::find($person->id));
    }

    public function test_deleting_a_linked_employee_dissolves_a_person_left_alone(): void
    {
        [$a, , $rowA, $rowB, $person] = $this->linkedPair();

        Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $a])
            ->call('deleteEmployee', $rowA->id);

        $this->assertNull(SubcontractorEmployee::find($rowA->id));
        $this->assertNull($rowB->fresh()->person_id);
        $this->assertNull(Person::find($person->id));
    }

    public function test_unlinking_from_the_person_page_needs_the_link_grant(): void
    {
        [, , $rowA, , $person] = $this->linkedPair();

        $reader = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'people.view']);

        Livewire::actingAs($reader)
            ->test(PersonShow::class, ['person' => $person])
            ->call('unlinkEmployee', $rowA->id)
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(PersonShow::class, ['person' => $person])
            ->set('person_name', 'Renamed')
            ->call('savePerson')
            ->assertForbidden();

        $this->assertSame($person->id, $rowA->fresh()->person_id);
        $this->assertSame('João Silva', $person->fresh()->name);
    }

    /*
    |---------------------------------------------------------------------------
    | Suggestions
    |---------------------------------------------------------------------------
    */

    public function test_the_form_offers_a_lookalike_at_another_company_and_links_on_save(): void
    {
        $a = $this->makeSubcontractor('Company A');
        $b = $this->makeSubcontractor('Company B');
        $rowA = $this->makeEmployee($a, ['phone' => '(11) 99999-1234', 'tax_id' => '123.456.789-09']);

        $component = Livewire::actingAs($this->admin)
            ->test(SubcontractorShow::class, ['subcontractor' => $b])
            ->call('startEmployee')
            ->set('employee_name', 'J. Silva')
            ->set('employee_phone', '11999991234');

        $suggestions = $component->instance()->employeeSuggestions;

        $this->assertCount(1, $suggestions);
        $this->assertSame($rowA->id, $suggestions->first()['employee']->id);
        $this->assertSame(['phone'], $suggestions->first()['reasons']);

        $component
            ->call('chooseEmployeeLink', $rowA->id)
            ->set('employee_link_reason', 'Moved over in March')
            ->set('employee_tax_id', '999.888.777-66')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $rowB = $b->employees()->first();

        $this->assertNotNull($rowB->person_id);
        $this->assertSame($rowB->person_id, $rowA->fresh()->person_id);
        $this->assertSame('Moved over in March', $rowB->link_reason);
        $this->assertCount(2, $rowB->person->distinctTaxIds());
    }

    public function test_a_link_chosen_without_the_grant_is_ignored_on_save(): void
    {
        $a = $this->makeSubcontractor('Company A');
        $b = $this->makeSubcontractor('Company B');
        $rowA = $this->makeEmployee($a);

        $editor = $this->roleWith(['projects.view', 'project.view', 'vendors.view', 'vendors.edit']);

        Livewire::actingAs($editor)
            ->test(SubcontractorShow::class, ['subcontractor' => $b])
            ->call('startEmployee')
            ->set('employee_name', 'João Silva')
            ->set('employee_link_to', $rowA->id)
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertNull($b->employees()->first()->person_id);
        $this->assertNull($rowA->fresh()->person_id);
        $this->assertSame(0, Person::count());
    }

    public function test_the_people_page_suggests_rows_sharing_a_tax_id_and_links_them_together(): void
    {
        $a = $this->makeSubcontractor('Company A');
        $b = $this->makeSubcontractor('Company B');
        $c = $this->makeSubcontractor('Company C');
        $rowA = $this->makeEmployee($a, ['tax_id' => '123.456.789-09']);
        $rowB = $this->makeEmployee($b, ['name' => 'Joao da Silva', 'tax_id' => '12345678909']);
        $unrelated = $this->makeEmployee($c, ['name' => 'Maria', 'tax_id' => '000.000.000-00']);

        $component = Livewire::actingAs($this->admin)
            ->test(PeopleIndex::class)
            ->call('setActiveTab', 'suggestions');

        $suggestions = $component->viewData('suggestions');

        $this->assertCount(1, $suggestions);
        $this->assertSame('tax_id', $suggestions->first()['reason']);
        $this->assertEqualsCanonicalizing([$rowA->id, $rowB->id], $suggestions->first()['rows']->pluck('id')->all());

        $component->call('linkGroup', [$rowA->id, $rowB->id, $unrelated->id]);

        // The unrelated row was sent by the browser too; it sits at a third
        // company, so it is linked as well — the group is what was asked
        // for. What is never linked is a second row at the same company.
        $this->assertSame(1, Person::count());
        $this->assertSame($rowA->fresh()->person_id, $rowB->fresh()->person_id);

        // A seeded employee may see the suggestion but not act on it.
        Livewire::actingAs($this->user('employee'))
            ->test(PeopleIndex::class)
            ->call('linkGroup', [$rowA->id, $rowB->id])
            ->assertForbidden();
    }

    public function test_the_people_list_is_searchable_and_shows_the_tax_id_difference(): void
    {
        [, , , , $person] = $this->linkedPair();

        Livewire::actingAs($this->admin)
            ->test(PeopleIndex::class)
            ->assertSee($person->name)
            ->assertSee(__('2 different tax ids'))
            ->set('search', 'Company B')
            ->assertSee($person->name)
            ->set('search', 'nobody-here')
            ->assertDontSee(route('people.show', $person));
    }
}
