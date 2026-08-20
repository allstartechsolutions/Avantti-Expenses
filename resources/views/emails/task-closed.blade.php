@php $stampFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A'; @endphp

<x-email-shell :heading="$cancelled ? __('Task cancelled') : __('Task completed')" :accent="$cancelled ? '#64748b' : '#16a34a'">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ $cancelled
            ? __(':actor cancelled this task.', ['actor' => $actor->name])
            : __(':actor confirmed this task as done.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $task->code() }}</strong> — {{ $task->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ $task->getScopeLabel() }}<br>
                    {{ __('Owner') }}: {{ $task->owner?->name }}<br>
                    {{ $cancelled
                        ? __('Cancelled on :date', ['date' => $task->cancelled_at?->format($stampFormat)])
                        : __('Completed on :date', ['date' => $task->completed_at?->format($stampFormat)]) }}
                </div>
                @if($cancelled && $task->cancel_reason)
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e9ecef; color: #555; font-size: 13px;">
                        {{ $task->cancel_reason }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <p style="margin: 0; text-align: center;">
        <a href="{{ route('tasks.mine') }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open My Tasks') }}
        </a>
    </p>
</x-email-shell>
