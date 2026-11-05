<?php

namespace App\Listeners;

use App\Models\LoginEvent;
use App\Models\User;
use App\Services\SecurityAlerts;
use App\Services\UserAgent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Writes the login activity log (docs/login-activity.md). Discovered by
 * Laravel from the type of each handle* method.
 *
 * Recording must never stand between a person and their sign-in: any failure
 * here is reported and swallowed.
 */
class RecordLoginActivity
{
    public function __construct(protected Request $request) {}

    public function handleLogin(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        // `$event->remember` only says the box was ticked; the guard knows whether
        // the cookie is what signed them in.
        $viaRemember = (bool) $this->safely(fn () => auth()->guard($event->guard)->viaRemember());
        $recorded = $this->record(LoginEvent::LOGIN, $event->user, viaRemember: $viaRemember, newDeviceCheck: true);
        $this->alertLater(fn (SecurityAlerts $alerts) => $alerts->newDevice($recorded), $recorded);

        $this->safely(fn () => $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->saveQuietly());
    }

    public function handleFailed(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $recorded = $this->record(LoginEvent::FAILED, $event->user instanceof User ? $event->user : null, email: $event->credentials[Fortify::username()] ?? null);
        $this->alertLater(fn (SecurityAlerts $alerts) => $alerts->failed($recorded), $recorded);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $this->record(LoginEvent::LOGOUT, $event->user);
    }

    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input(Fortify::username());

        // Every blocked click fires this; one lockout per email and address per minute is the story.
        if (! Cache::add('login-lockout:'.sha1(mb_strtolower((string) $email).'|'.$this->request->ip()), true, 60)) {
            return;
        }

        $recorded = $this->record(LoginEvent::LOCKOUT, $email ? User::where('email', $email)->first() : null, email: $email);
        $this->alertLater(fn (SecurityAlerts $alerts) => $alerts->lockout($recorded), $recorded);
    }

    public function handleTwoFactorChallenged(TwoFactorAuthenticationChallenged $event): void
    {
        $this->record(LoginEvent::TWO_FACTOR_CHALLENGED, $event->user);
    }

    public function handleTwoFactorPassed(ValidTwoFactorAuthenticationCodeProvided $event): void
    {
        $this->record(LoginEvent::TWO_FACTOR_PASSED, $event->user);
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->record(LoginEvent::TWO_FACTOR_FAILED, $event->user);
    }

    protected function record(string $event, mixed $user, ?string $email = null, bool $viaRemember = false, bool $newDeviceCheck = false): ?LoginEvent
    {
        return $this->safely(function () use ($event, $user, $email, $viaRemember, $newDeviceCheck) {
            $user = $user instanceof User ? $user : null;
            $agent = $this->request->userAgent();
            $parsed = UserAgent::parse($agent);
            $ip = $this->request->ip();
            $fingerprint = UserAgent::fingerprint($parsed['browser'], $parsed['platform'], $ip);

            // A first sign-in ever is not "a new device" — there is nothing to compare with.
            $isNewDevice = $newDeviceCheck && $user
                && LoginEvent::where('user_id', $user->id)->where('event', LoginEvent::LOGIN)->exists()
                && ! LoginEvent::where('user_id', $user->id)->where('event', LoginEvent::LOGIN)->where('fingerprint', $fingerprint)->exists();

            return LoginEvent::create([
                'user_id' => $user?->id,
                'email' => $email !== null ? mb_substr(trim($email), 0, 255) : $user?->email,
                'event' => $event,
                'ip_address' => $ip,
                'user_agent' => $agent ? mb_substr($agent, 0, 1000) : null,
                'browser' => $parsed['browser'],
                'platform' => $parsed['platform'],
                'device' => $parsed['device'],
                'fingerprint' => $fingerprint,
                'via_remember' => $viaRemember,
                'is_new_device' => $isNewDevice,
            ]);
        });
    }

    /**
     * Suspicious-activity alerts (stage 5) go out after the response, so a
     * sign-in is never slowed by mail — and never broken by it.
     */
    protected function alertLater(callable $check, ?LoginEvent $recorded): void
    {
        if (! $recorded) {
            return;
        }

        // defer(): once, after the response, and forgotten with the request —
        // app()->terminating() callbacks pile up in a long-lived process.
        // always(): a lockout ends in a 429, and deferred work skips error responses by default.
        defer(fn () => $this->safely(fn () => $check(app(SecurityAlerts::class))))->always();
    }

    protected function safely(callable $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
