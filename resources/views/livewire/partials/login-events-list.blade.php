{{--
    A short list of login events (docs/login-activity.md): the user page and
    the person's own Sign-in activity settings page. Expects $events.
--}}
@php
    $dot = ['green' => 'bg-green-500', 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'slate' => 'bg-slate-400'];
@endphp
@if($events->isEmpty())
    <p class="px-6 py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No sign-in activity recorded yet.') }}</p>
@else
    <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
        @foreach($events as $e)
            <li wire:key="login-event-row-{{ $e->id }}" class="px-6 py-3 flex items-start justify-between gap-4">
                <div class="min-w-0 flex items-start gap-3">
                    <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full {{ $dot[$e->getEventColor()] }}"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ $e->getEventLabel() }}
                            @if($e->is_new_device)
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('New device') }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 break-words">{{ $e->getDeviceLabel() }} · <span class="font-mono">{{ $e->ip_address ?? '—' }}</span></p>
                    </div>
                </div>
                <p class="shrink-0 text-right text-xs text-slate-500 dark:text-slate-400" title="{{ $e->created_at->appDateTime() }}">
                    {{ $e->created_at->appDateTime() }}<br>{{ $e->created_at->diffForHumans() }}
                </p>
            </li>
        @endforeach
    </ul>
@endif
