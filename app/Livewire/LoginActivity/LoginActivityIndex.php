<?php

namespace App\Livewire\LoginActivity;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\UserAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Company → Login Activity (docs/login-activity.md): every sign-in, failed
 * attempt, logout, lockout and two-factor step, filterable and exportable.
 * Kept LoginEvent::RETENTION_DAYS days. Read-only: nothing here changes a record.
 */
class LoginActivityIndex extends Component
{
    use AuthorizesAbility;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'user', except: '')]
    public string $userFilter = '';

    /** '' every event, 'problems' failed + lockout + 2FA failed, or one event key. */
    #[Url(as: 'event', except: '')]
    public string $eventFilter = '';

    #[Url(as: 'new', except: false)]
    public bool $newDevicesOnly = false;

    #[Url(except: '')]
    public string $fromDate = '';

    #[Url(except: '')]
    public string $toDate = '';

    public function mount(): void
    {
        $this->authorizeAbility('login-activity.view');

        if ($this->fromDate === '' && $this->toDate === '') {
            $this->fromDate = now()->subDays(6)->toDateString();
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'userFilter', 'eventFilter', 'newDevicesOnly', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'userFilter', 'eventFilter', 'newDevicesOnly', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->userFilter !== '' || $this->eventFilter !== ''
            || $this->newDevicesOnly || $this->fromDate !== '' || $this->toDate !== '';
    }

    /** Shortcut chips above the list. */
    public function showProblems(): void
    {
        $this->eventFilter = $this->eventFilter === 'problems' ? '' : 'problems';
        $this->resetPage();
    }

    /** Every filter on the screen; the list, the cards and the CSV read the same set. */
    protected function filteredQuery(): Builder
    {
        return LoginEvent::query()
            ->when($this->userFilter !== '', fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->eventFilter === 'problems', fn ($q) => $q->problems())
            ->when(in_array($this->eventFilter, LoginEvent::EVENTS, true), fn ($q) => $q->where('event', $this->eventFilter))
            ->when($this->newDevicesOnly, fn ($q) => $q->where('is_new_device', true))
            ->when($this->fromDate !== '', fn ($q) => $q->where('created_at', '>=', $this->fromDate.' 00:00:00'))
            ->when($this->toDate !== '', fn ($q) => $q->where('created_at', '<=', $this->toDate.' 23:59:59'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('email', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)));
            });
    }

    /** The period's story at a glance, over the same filters. */
    protected function summary(): array
    {
        $counts = $this->filteredQuery()
            ->selectRaw('event, COUNT(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event');

        return [
            'logins' => (int) ($counts[LoginEvent::LOGIN] ?? 0),
            'failed' => (int) ($counts[LoginEvent::FAILED] ?? 0) + (int) ($counts[LoginEvent::TWO_FACTOR_FAILED] ?? 0),
            'lockouts' => (int) ($counts[LoginEvent::LOCKOUT] ?? 0),
            'new_devices' => $this->filteredQuery()->where('is_new_device', true)->count(),
            'people' => $this->filteredQuery()->where('event', LoginEvent::LOGIN)->distinct()->count('user_id'),
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | Signed in now (stage 4)
    |---------------------------------------------------------------------------
    | Sessions live in the `sessions` table (database driver). A session is
    | referred to by a hash of its id: the id itself never reaches the browser.
    */

    /** Every live session of a signed-in person, newest activity first. */
    protected function activeSessions(): Collection
    {
        $cutoff = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
        $current = session()->getId();

        $rows = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $cutoff)
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $users = User::whereIn('id', $rows->pluck('user_id')->unique())->get(['id', 'name', 'email'])->keyBy('id');

        return $rows->map(function ($row) use ($users, $current) {
            $agent = UserAgent::parse($row->user_agent);

            return (object) [
                'handle' => hash('sha256', $row->id),
                'user' => $users->get($row->user_id),
                'ip_address' => $row->ip_address,
                'device' => $agent['browser'] || $agent['platform']
                    ? __(':browser on :platform', ['browser' => $agent['browser'] ?: __('Unknown browser'), 'platform' => $agent['platform'] ?: __('unknown system')])
                    : __('Unknown device'),
                'last_activity' => Carbon::createFromTimestamp($row->last_activity),
                'is_current' => $row->id === $current,
            ];
        })->filter(fn ($s) => $s->user !== null)->values();
    }

    /** End one session. The person is signed out on their next click. */
    public function endSession(string $handle): void
    {
        $this->authorizeAbility('login-activity.end_sessions');

        $row = DB::table('sessions')->whereNotNull('user_id')->get(['id', 'user_id', 'ip_address', 'user_agent'])
            ->first(fn ($s) => hash_equals(hash('sha256', $s->id), $handle));

        abort_unless($row, 404);
        abort_if($row->id === session()->getId(), 422, __('That is your own session — use Log out instead.'));

        DB::table('sessions')->where('id', $row->id)->delete();
        $this->afterSessionsEnded(User::findOrFail($row->user_id), $row->ip_address, $row->user_agent);

        session()->flash('message', __('Session ended.'));
    }

    /** End every session of one person (all but your own, if it is you). */
    public function endAllSessions(int $userId): void
    {
        $this->authorizeAbility('login-activity.end_sessions');

        $user = User::findOrFail($userId);

        $ended = DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();

        $this->afterSessionsEnded($user, null, null);

        session()->flash('message', trans_choice(':count session ended.|:count sessions ended.', $ended, ['count' => $ended]));
    }

    /**
     * A "remember me" cookie would sign them straight back in: rotating the
     * token makes it worthless. Then the log says who did it.
     */
    protected function afterSessionsEnded(User $user, ?string $ip, ?string $agent): void
    {
        // Your own too: a "remember me" cookie on the browser you signed out
        // would sign it straight back in. This session carries on regardless.
        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();

        $parsed = UserAgent::parse($agent);

        LoginEvent::create([
            'user_id' => $user->id,
            'actor_id' => auth()->id(),
            'email' => $user->email,
            'event' => LoginEvent::SESSION_ENDED,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'device' => $parsed['device'],
        ]);
    }

    public function exportCsv()
    {
        $this->authorizeAbility('login-activity.view');

        $events = $this->filteredQuery()->with('user:id,name,email')->latest('created_at')->latest('id')->get();

        return response()->streamDownload(function () use ($events) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Date'), __('Time'), __('Event'), __('Person'), __('Email'), __('IP address'), __('Browser'), __('System'), __('Device'), __('New device'), __('Remembered'), __('User agent')]);

            foreach ($events as $e) {
                fputcsv($out, array_map(self::csvSafe(...), [
                    $e->created_at->format('Y-m-d'),
                    $e->created_at->format('H:i:s'),
                    $e->getEventLabel(),
                    $e->user?->name ?? '',
                    $e->email ?? '',
                    $e->ip_address ?? '',
                    $e->browser ?? '',
                    $e->platform ?? '',
                    $e->device ? __(ucfirst($e->device)) : '',
                    $e->is_new_device ? __('Yes') : '',
                    $e->via_remember ? __('Yes') : '',
                    $e->user_agent ?? '',
                ]));
            }

            fclose($out);
        }, 'login-activity-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A cell a spreadsheet would run as a formula — a typed email or a browser
     * string is attacker-controlled on a failed sign-in — is kept as text.
     */
    protected static function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    public function render()
    {
        // Every request, not only mount(): a filter change or a page click on a
        // screen left open after the grant was taken away must not keep reading the log.
        $this->authorizeAbility('login-activity.view');

        return view('livewire.login-activity.login-activity-index', [
            'events' => $this->filteredQuery()->with(['user:id,name,email', 'actor:id,name'])->latest('created_at')->latest('id')->paginate(50),
            'summary' => $this->summary(),
            'people' => User::whereIn('id', LoginEvent::whereNotNull('user_id')->select('user_id'))->orderBy('name')->get(['id', 'name']),
            'sessions' => $this->activeSessions(),
        ])->layout('components.layouts.app');
    }
}
