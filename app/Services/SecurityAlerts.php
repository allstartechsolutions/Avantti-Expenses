<?php

namespace App\Services;

use App\Mail\SecurityAlertMail;
use App\Models\LoginEvent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Suspicious sign-ins, mailed to whoever may read the login log
 * (docs/login-activity.md, stage 5). Called by RecordLoginActivity once the
 * event is written; every alert is throttled so a burst is one e-mail.
 */
class SecurityAlerts
{
    /** Different accounts one address must fail on, inside the window, to count as spraying. */
    public const SPRAY_ACCOUNTS = 5;

    public const SPRAY_WINDOW_MINUTES = 15;

    /** One alert per account or address per this long. */
    public const THROTTLE_MINUTES = 60;

    public function __construct(protected BuyerDirectory $directory) {}

    public function lockout(LoginEvent $event): void
    {
        $this->alert('lockout', $event, 'lockout:'.mb_strtolower((string) $event->email));
    }

    public function newDevice(LoginEvent $event): void
    {
        if ($event->is_new_device) {
            $this->alert('new_device', $event, 'new-device:'.$event->user_id.':'.$event->fingerprint);
        }
    }

    /** A failed attempt: alert when its address has now failed on many different accounts. */
    public function failed(LoginEvent $event): void
    {
        if (! $event->ip_address) {
            return;
        }

        $accounts = LoginEvent::query()
            ->where('event', LoginEvent::FAILED)
            ->where('ip_address', $event->ip_address)
            ->where('created_at', '>=', now()->subMinutes(self::SPRAY_WINDOW_MINUTES))
            ->distinct()
            ->count('email');

        if ($accounts >= self::SPRAY_ACCOUNTS) {
            $this->alert('spraying', $event, 'spraying:'.$event->ip_address, $accounts);
        }
    }

    /** @return Collection<int, User> */
    public function recipients(): Collection
    {
        return $this->directory->holdersOf('login-activity.view', null);
    }

    protected function alert(string $kind, LoginEvent $event, string $throttleKey, int $accounts = 0): void
    {
        if (! NotificationSetting::enabled(NotificationSetting::SECURITY_ALERT)) {
            return;
        }

        if (! Cache::add('security-alert:'.sha1($throttleKey), true, now()->addMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        foreach ($this->recipients() as $user) {
            $this->send($user, new SecurityAlertMail($user, $kind, $event, $accounts), $kind, $event);
        }
    }

    protected function send(User $user, SecurityAlertMail $mail, string $kind, LoginEvent $event): void
    {
        $key = NotificationSetting::SECURITY_ALERT;

        if (! $user->email || ! $user->isActive() || ! $user->wantsNotification($key)) {
            return;
        }

        $record = NotificationLogEntry::create([
            'user_id' => $user->id,
            'type' => $key,
            'email' => $user->email,
            'meta' => ['kind' => $kind, 'login_event_id' => $event->id],
        ]);

        try {
            Mail::to($user->email)->send($mail);
            $record->update(['sent_at' => now()]);
        } catch (Throwable $e) {
            $record->update(['error' => substr($e->getMessage(), 0, 500)]);
            Log::warning('Security alert could not be sent', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
