<?php

namespace Tests\Feature\Collaboration;

use App\Models\Client;
use App\Models\Collaboration\ResponseCode;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Response codes are data, and the rule that makes that safe is that logic
 * reads `canonical` and never the letter.
 */
class ResponseCodeTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CollaborationResponseCodeSeeder::class);

        $owner = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $client = Client::create([
            'company_name' => 'Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $owner->id,
        ]);
        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $owner->id,
        ]);
    }

    public function test_both_market_sets_are_seeded(): void
    {
        $this->assertSame(5, ResponseCode::where('market', 'us')->count());
        $this->assertSame(5, ResponseCode::where('market', 'br')->count());
    }

    public function test_the_seeder_is_safe_to_run_again(): void
    {
        $this->seed(CollaborationResponseCodeSeeder::class);
        $this->seed(CollaborationResponseCodeSeeder::class);

        $this->assertSame(10, ResponseCode::count());
    }

    /**
     * The set on offer follows the install's country, and nothing else.
     * There is no per-project or per-tenant market to get wrong.
     */
    public function test_the_offered_set_follows_the_installs_country(): void
    {
        config(['app.country' => 'BR']);
        $br = ResponseCode::offered('approval');

        config(['app.country' => 'US']);
        $us = ResponseCode::offered('approval');

        $this->assertSame(5, $br->count());
        $this->assertSame(5, $us->count());

        // Same meanings, different wording — which is the whole design.
        $this->assertSame(
            $us->pluck('canonical')->all(),
            $br->pluck('canonical')->all(),
        );
        // The wording is the *locale's*, not the market's — the two are
        // separate settings, and a BR install reading in English is a real
        // configuration. Assert each in the locale it belongs to.
        $this->app->setLocale('pt_BR');
        $this->assertSame('Aprovado', __($br->firstWhere('canonical', ResponseCode::APPROVED)->label_key));

        $this->app->setLocale('en');
        $this->assertSame('Approved', __($us->firstWhere('canonical', ResponseCode::APPROVED)->label_key));
    }

    public function test_codes_come_back_in_their_sort_order(): void
    {
        $this->assertSame(
            ['A', 'B', 'C', 'D', 'E'],
            ResponseCode::offered('approval')->pluck('code')->all(),
        );
    }

    /**
     * The one behavioural flag. Revise-and-resubmit is the only code that does
     * not end the cycle — it opens the next revision.
     */
    public function test_only_revise_and_resubmit_keeps_the_cycle_open(): void
    {
        $open = ResponseCode::offered('approval')
            ->reject(fn (ResponseCode $c) => $c->closesCycle())
            ->pluck('canonical')
            ->all();

        $this->assertSame([ResponseCode::REVISE_RESUBMIT], $open);
        $this->assertTrue(
            ResponseCode::offered('approval')
                ->firstWhere('canonical', ResponseCode::REVISE_RESUBMIT)
                ->opensRevision(),
        );
    }

    /**
     * A project may rename a code. Renaming must not offer the reviewer the
     * same meaning twice, and must not change what the code does.
     */
    public function test_a_project_code_replaces_the_default_rather_than_joining_it(): void
    {
        ResponseCode::create([
            'project_id' => $this->project->id,
            'market' => ResponseCode::market(),
            'document_type' => 'approval',
            'code' => 'R',
            'label_key' => 'Response: Reapresentar',
            'canonical' => ResponseCode::REVISE_RESUBMIT,
            'closes_cycle' => false,
            'sort' => 30,
        ]);

        $offered = ResponseCode::offered('approval', $this->project->id);

        $this->assertSame(5, $offered->count());
        $this->assertSame('R', $offered->firstWhere('canonical', ResponseCode::REVISE_RESUBMIT)->code);
        $this->assertFalse($offered->firstWhere('canonical', ResponseCode::REVISE_RESUBMIT)->closesCycle());

        // And another project still sees the default.
        $this->assertSame('C', ResponseCode::offered('approval')->firstWhere('canonical', ResponseCode::REVISE_RESUBMIT)->code);
    }

    public function test_a_projects_codes_do_not_leak_to_another_project(): void
    {
        ResponseCode::create([
            'project_id' => $this->project->id,
            'market' => ResponseCode::market(),
            'document_type' => 'approval',
            'code' => 'Z',
            'label_key' => 'Response: Aprovado',
            'canonical' => ResponseCode::APPROVED,
            'closes_cycle' => true,
            'sort' => 10,
        ]);

        $other = Project::create([
            'project_name' => 'Obra Norte',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->project->created_by,
        ]);

        $this->assertSame('Z', ResponseCode::offered('approval', $this->project->id)->firstWhere('canonical', ResponseCode::APPROVED)->code);
        $this->assertSame('A', ResponseCode::offered('approval', $other->id)->firstWhere('canonical', ResponseCode::APPROVED)->code);
    }

    public function test_the_label_pairs_the_code_with_its_wording(): void
    {
        config(['app.country' => 'BR']);

        $this->assertSame(
            'C — Reapresentar',
            ResponseCode::offered('approval')->firstWhere('canonical', ResponseCode::REVISE_RESUBMIT)->getLabel(),
        );
    }

    /** RFIs are answered in prose; they have no coded responses. */
    public function test_rfis_have_no_response_codes(): void
    {
        $this->assertSame(0, ResponseCode::offered('rfi')->count());
    }
}
