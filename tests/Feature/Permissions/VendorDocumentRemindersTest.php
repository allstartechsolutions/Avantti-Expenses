<?php

namespace Tests\Feature\Permissions;

use App\Livewire\SystemSettings\NotificationSettings;
use App\Mail\VendorDocumentExpiryMail;
use App\Models\DocumentType;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorDocumentNotifier;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5 of docs/vendor-document-expiry-plan.md: the reminder e-mails.
 *
 * Four fixed stages, each once per document; one mail per person per
 * morning; renewing or archiving ends the sequence; the recipients are a
 * setting with a fallback. And a double run mails nobody twice.
 */
class VendorDocumentRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /** The one chosen recipient, so the stage tests count mails deterministically. */
    protected User $keeper;

    protected DocumentType $insurance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        Mail::fake();

        $this->admin = $this->user('admin', ['name' => 'The Admin']);
        $this->insurance = DocumentType::create(['name' => 'General Liability Insurance', 'requires_expiration' => true, 'sort_order' => 1]);

        $this->keeper = $this->roleWith(['vendors.view', 'vendors.renew_documents'], ['name' => 'The Keeper']);
        $this->chooseRecipients([$this->keeper->id]);
    }

    protected function chooseRecipients(array $ids): void
    {
        NotificationSetting::updateOrCreate(
            ['key' => NotificationSetting::VENDOR_DOCUMENT_EXPIRY],
            ['is_enabled' => true, 'options' => ['recipients' => $ids]],
        );
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

    protected function roleWith(array $abilities, array $attributes = []): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
    }

    protected function makeSubcontractor(?string $name = null): Subcontractor
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => $name ?? 'Sub '.str()->random(5),
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return Subcontractor::findOrFail($vendor->id);
    }

    protected function document(Subcontractor $sub, int $daysFromToday, array $attributes = []): SubcontractorDocument
    {
        return SubcontractorDocument::create(array_merge([
            'subcontractor_id' => $sub->id,
            'document_type_id' => $this->insurance->id,
            'file_path' => "subcontractor-documents/{$sub->id}/".str()->random(6).'.pdf',
            'file_name' => 'coi.pdf',
            'file_size' => 3,
            'expiration_date' => now()->addDays($daysFromToday)->toDateString(),
            'uploaded_by' => $this->admin->id,
        ], $attributes));
    }

    protected function remind(): array
    {
        return app(VendorDocumentNotifier::class)->sendExpiryReminders();
    }

    /*
    |---------------------------------------------------------------------------
    | Stages
    |---------------------------------------------------------------------------
    */

    public function test_each_stage_fires_once_and_a_double_run_mails_nobody_twice(): void
    {
        $sub = $this->makeSubcontractor('Acme Roofing');
        $document = $this->document($sub, 45);
        $keeper = $this->keeper;

        // Day 45: outside every window.
        $this->assertSame(0, $this->remind()['sent']);
        Mail::assertNothingSent();

        // Day 30: the first stage.
        $this->travel(15)->days();
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertSame(0, $this->remind()['sent'], 'A second run the same morning sends nothing.');
        $this->assertNotNull($document->fresh()->notified_30_at);
        $this->assertNull($document->fresh()->notified_15_at);
        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($keeper) && $mail->expiring->count() === 1 && $mail->expired->isEmpty());

        // Day 20: between stages, quiet.
        $this->travel(10)->days();
        $this->assertSame(0, $this->remind()['sent']);

        // Day 15 and day 7.
        $this->travel(5)->days();
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($document->fresh()->notified_15_at);

        $this->travel(8)->days();
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($document->fresh()->notified_7_at);

        // The day it expires: nothing — the last stage is the day after.
        $this->travel(7)->days();
        $this->assertSame(0, $this->remind()['sent']);

        $this->travel(1)->days();
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($document->fresh()->notified_expired_at);
        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->expired->count() === 1 && $mail->expiring->isEmpty());

        // And never again.
        $this->travel(30)->days();
        $this->assertSame(0, $this->remind()['sent']);

        $this->assertSame(4, NotificationLogEntry::where('type', NotificationSetting::VENDOR_DOCUMENT_EXPIRY)->whereNotNull('sent_at')->count());
    }

    public function test_a_document_inside_several_windows_is_listed_once_and_every_stage_is_stamped(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->document($sub, 10);

        $this->assertSame(1, $this->remind()['sent']);

        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->expiring->count() === 1 && $mail->expiring->first()['stage'] === 15);

        $document->refresh();
        $this->assertNotNull($document->notified_30_at);
        $this->assertNotNull($document->notified_15_at);
        $this->assertNull($document->notified_7_at);

        $this->travel(3)->days();
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($document->fresh()->notified_7_at);
    }

    public function test_a_missed_morning_is_caught_the_next_one(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->document($sub, 30);

        // The scheduler did not run on day 30 or day 29.
        $this->travel(2)->days();
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($document->fresh()->notified_30_at);

        $document->forceFill(['expiration_date' => now()->subDays(4)->toDateString(), 'notified_expired_at' => null])->save();
        $this->travel(1)->days();
        $this->assertSame(1, $this->remind()['sent'], 'An expired document nobody was told about is reported however late.');
    }

    public function test_renewing_or_archiving_ends_the_sequence(): void
    {
        $sub = $this->makeSubcontractor();
        $renewed = $this->document($sub, 20);
        $archived = $this->document($sub, 20);
        $untouched = $this->document($sub, 20);

        $renewal = $this->document($sub, 380);
        $renewed->supersedeWith($renewal);
        $archived->archive($this->admin, 'No longer needed');

        $this->assertSame(1, $this->remind()['sent']);

        Mail::assertSent(VendorDocumentExpiryMail::class, function ($mail) use ($untouched) {
            return $mail->expiring->count() === 1
                && $mail->expiring->first()['document']->is($untouched);
        });

        $this->assertNull($renewed->fresh()->notified_30_at);
        $this->assertNull($archived->fresh()->notified_30_at);
    }

    public function test_a_type_that_does_not_require_a_date_is_never_reported(): void
    {
        $sub = $this->makeSubcontractor();
        $w9 = DocumentType::create(['name' => 'W9', 'requires_expiration' => false, 'sort_order' => 2]);
        $this->document($sub, 5, ['document_type_id' => $w9->id]);

        $this->assertSame(0, $this->remind()['documents']);
        Mail::assertNothingSent();
    }

    /*
    |---------------------------------------------------------------------------
    | Recipients
    |---------------------------------------------------------------------------
    */

    public function test_the_fallback_is_everyone_who_may_renew_and_nobody_else(): void
    {
        $sub = $this->makeSubcontractor();
        $this->document($sub, 7);
        $this->chooseRecipients([]);

        $keeper = $this->keeper;
        $reader = $this->roleWith(['vendors.view']);
        $inactive = $this->roleWith(['vendors.view', 'vendors.renew_documents'], ['status' => 'inactive']);

        $result = $this->remind();

        // The seeded manager and employee roles hold renew_documents too, and
        // the admin bypasses everything, so the fallback reaches them as well.
        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($keeper));
        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($this->admin));
        Mail::assertNotSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($reader));
        Mail::assertNotSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($inactive));
        $this->assertSame($result['recipients'], $result['sent']);
    }

    public function test_chosen_recipients_replace_the_fallback(): void
    {
        $sub = $this->makeSubcontractor();
        $this->document($sub, 7);

        $keeper = $this->keeper;
        $chosen = $this->roleWith(['projects.view'], ['name' => 'The Chosen']);

        $this->chooseRecipients([$chosen->id]);

        $this->remind();

        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($chosen));
        Mail::assertNotSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($keeper));
        Mail::assertNotSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($this->admin));
    }

    public function test_the_company_switch_and_the_personal_opt_out_both_stop_it(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->document($sub, 7);
        $optedOut = $this->roleWith(['vendors.view', 'vendors.renew_documents'], [
            'notification_preferences' => [NotificationSetting::VENDOR_DOCUMENT_EXPIRY => false],
        ]);
        $this->chooseRecipients([$optedOut->id, $this->admin->id]);

        $this->remind();
        Mail::assertNotSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($optedOut));
        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->recipient->is($this->admin));
        $this->assertNotNull($document->fresh()->notified_7_at, 'Stamped even for the person who opted out — the stage went out.');

        NotificationSetting::updateOrCreate(['key' => NotificationSetting::VENDOR_DOCUMENT_EXPIRY], ['is_enabled' => false]);
        $this->document($sub, 7);

        Mail::fake();
        $this->assertSame(0, $this->remind()['documents']);
        Mail::assertNothingSent();
    }

    public function test_the_command_runs_the_notifier(): void
    {
        $sub = $this->makeSubcontractor();
        $this->document($sub, 15);

        Artisan::call('vendors:notify-document-expiry');

        $this->assertStringContainsString('1 document(s) at a reminder stage', Artisan::output());
        Mail::assertSent(VendorDocumentExpiryMail::class);
    }

    /*
    |---------------------------------------------------------------------------
    | The settings screen
    |---------------------------------------------------------------------------
    */

    public function test_choosing_recipients_needs_settings_edit_and_refuses_a_guest_or_inactive_person(): void
    {
        $staff = $this->user('employee', ['name' => 'Staff Member']);
        $guest = $this->user('employee', ['is_guest' => true]);
        $inactive = $this->user('employee', ['status' => 'inactive']);

        Livewire::actingAs($this->roleWith(['projects.view']))
            ->test(NotificationSettings::class)
            ->assertForbidden();

        $component = Livewire::actingAs($this->admin)
            ->test(NotificationSettings::class)
            ->assertSee('Vendor E-mails')
            ->assertSee('Who receives the reminders')
            ->assertSee('Staff Member')
            ->assertDontSee($guest->name);

        $component->set('vendorDocumentRecipients', [$guest->id])
            ->call('saveVendorDocumentRecipients')
            ->assertHasErrors(['vendorDocumentRecipients.0']);

        $component->set('vendorDocumentRecipients', [$inactive->id])
            ->call('saveVendorDocumentRecipients')
            ->assertHasErrors(['vendorDocumentRecipients.0']);

        $component->set('vendorDocumentRecipients', [(string) $staff->id, (string) $staff->id])
            ->call('saveVendorDocumentRecipients')
            ->assertHasNoErrors()
            ->assertSet('vendorDocumentRecipients', [$staff->id]);

        $this->assertSame([$staff->id], NotificationSetting::vendorDocumentRecipientIds());

        $component->set('vendorDocumentRecipients', [])
            ->call('saveVendorDocumentRecipients')
            ->assertHasNoErrors()
            ->assertSee('Nobody is chosen');

        $this->assertSame([], NotificationSetting::vendorDocumentRecipientIds());
    }

    public function test_a_person_can_opt_out_on_their_own_profile(): void
    {
        $this->actingAs($this->admin)
            ->get(route('notifications.edit'))
            ->assertOk()
            ->assertSee('A vendor document is expiring or has expired');
    }

    public function test_the_mail_renders_in_both_locales_with_every_section(): void
    {
        $sub = $this->makeSubcontractor('Acme Roofing');
        $expiring = $this->document($sub, 6, ['file_name' => 'coi-2027.pdf']);
        $expired = $this->document($sub, -2, ['file_name' => 'licence.pdf']);

        $mail = new VendorDocumentExpiryMail(
            $this->keeper,
            collect([['document' => $expiring, 'days' => 6, 'stage' => 7]]),
            collect([['document' => $expired, 'days' => -2]]),
        );

        $html = $mail->render();

        $this->assertStringContainsString('Acme Roofing', $html);
        $this->assertStringContainsString('coi-2027.pdf', $html);
        $this->assertStringContainsString('licence.pdf', $html);
        $this->assertStringContainsString('Expires in 6 days', $html);
        $this->assertStringContainsString('Expired 2 days ago', $html);
        $this->assertStringContainsString(route('subcontractors.show', $sub->id), $html);
        $this->assertStringContainsString('documents=expired', $html);
        $this->assertSame('2 vendor documents need attention', $mail->envelope()->subject);

        app()->setLocale('pt_BR');
        $pt = $mail->render();
        $this->assertStringContainsString('Vence em 6 dias', $pt);
        $this->assertStringContainsString('Vencido há 2 dias', $pt);
        $this->assertStringContainsString('Documentos vencidos', $pt);
        $this->assertSame('2 documentos de fornecedores precisam de atenção', $mail->envelope()->subject);
        app()->setLocale('en');
    }

    /*
    |---------------------------------------------------------------------------
    | Review findings, 2 Sep 2026
    |---------------------------------------------------------------------------
    */

    public function test_a_vendor_that_lost_its_subcontractor_flag_neither_crashes_the_digest_nor_stops_it(): void
    {
        $former = $this->makeSubcontractor('Former Sub');
        $this->document($former, 7);
        Vendor::whereKey($former->id)->update(['is_subcontractor' => false, 'is_supplier' => true]);

        $still = $this->makeSubcontractor('Still A Sub');
        $stillDoc = $this->document($still, 7);

        $result = $this->remind();

        $this->assertSame(1, $result['documents']);
        $this->assertSame(1, $result['sent']);
        Mail::assertSent(VendorDocumentExpiryMail::class, fn ($mail) => $mail->expiring->count() === 1
            && $mail->expiring->first()['document']->is($stillDoc));
        $this->assertNotNull($stillDoc->fresh()->notified_7_at);
    }

    public function test_a_delivery_outage_does_not_swallow_the_stage(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->document($sub, 7);

        $down = \Mockery::mock(\Illuminate\Mail\MailManager::class);
        $down->shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));
        Mail::swap($down);

        $result = $this->remind();

        $this->assertSame(0, $result['sent']);
        $this->assertNull($document->fresh()->notified_7_at, 'Nothing was delivered, so the stage is still owed.');
        $this->assertSame(1, NotificationLogEntry::whereNotNull('error')->count());

        // The next morning, with mail back, it goes out.
        Mail::swap(new \Illuminate\Support\Testing\Fakes\MailFake(new \Illuminate\Mail\MailManager(app())));
        $this->travel(1)->days();

        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($document->fresh()->notified_7_at);
    }

    public function test_a_stage_nobody_wants_is_still_stamped_so_it_does_not_retry_forever(): void
    {
        $sub = $this->makeSubcontractor();
        $document = $this->document($sub, 7);
        $optedOut = $this->roleWith(['vendors.view'], ['notification_preferences' => [NotificationSetting::VENDOR_DOCUMENT_EXPIRY => false]]);
        $this->chooseRecipients([$optedOut->id]);

        $result = $this->remind();

        $this->assertSame(0, $result['sent']);
        $this->assertNotNull($document->fresh()->notified_7_at);
        Mail::assertNothingSent();
    }

    public function test_the_company_switches_flip_and_the_page_shows_their_state(): void
    {
        $component = Livewire::actingAs($this->admin)->test(NotificationSettings::class);

        foreach ([NotificationSetting::VENDOR_DOCUMENT_EXPIRY, NotificationSetting::TASK_OVERDUE] as $key) {
            $component->assertSeeHtml('wire:click="toggle(\''.$key.'\')"');

            $component->call('toggle', $key);
            $this->assertFalse(NotificationSetting::enabled($key), "$key should be off after one flip.");
            $component->assertSeeHtml('wire:key="setting-'.$key.'-off"');

            $component->call('toggle', $key);
            $this->assertTrue(NotificationSetting::enabled($key), "$key should be on again.");
            $component->assertSeeHtml('wire:key="setting-'.$key.'-on"');
        }

        // The rendered input carries its state, which is what the switch paints from.
        $html = $component->html();
        $this->assertMatchesRegularExpression('/<input[^>]*checked[^>]*wire:click="toggle\(\'vendor_document_expiry\'\)"/s', $html);
    }
}
