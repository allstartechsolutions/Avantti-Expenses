
<x-email-shell :heading="__('Task')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ $owns
            ? __(':actor made you the owner of this task. You are the only person who can say it is ready.', ['actor' => $actor->name])
            : __(':actor added you to this task.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $task->code() }}</strong> — {{ $task->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Where') }}: {{ $task->getScopeLabel() }}<br>
                    {{ __('Due Date') }}: <strong>{{ $task->due_date?->appDate() ?? __('No due date') }}</strong><br>
                    {{ __('Owner') }}: {{ $task->owner?->name }}
                    @if($task->priority !== 'normal') · {{ $task->getPriorityLabel() }} @endif
                </div>
                @if($task->description)
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e9ecef; color: #555; font-size: 13px; white-space: pre-line;">{{ \Illuminate\Support\Str::limit($task->description, 400) }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if($task->originMeeting)
        <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
            {{ __('Raised at :number.', ['number' => $task->originMeeting->number]) }}
        </p>
    @endif

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ route('tasks.mine') }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open My Tasks') }}
        </a>
    </p>
</x-email-shell>
