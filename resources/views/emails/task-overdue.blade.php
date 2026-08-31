
<x-email-shell :heading="__('Past due')" accent="#dc2626">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ trans_choice(
            'This task passed its due date :count day ago and is still open.|This task passed its due date :count days ago and is still open.',
            $task->daysOverdue(), ['count' => $task->daysOverdue()]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef2f2; border-radius: 6px; border: 1px solid #fecaca; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $task->code() }}</strong> — {{ $task->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ $task->getScopeLabel() }}<br>
                    {{ __('Due Date') }}: <strong style="color: #dc2626;">{{ $task->due_date?->appDate() }}</strong>
                    · {{ $task->progress }}% · {{ $task->getStatusLabel() }}
                </div>
                @if($lastNote)
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #fecaca; color: #555; font-size: 13px;">
                        <em>{{ __('Last note') }}:</em> {{ \Illuminate\Support\Str::limit($lastNote->body, 200) }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 16px; font-size: 13px; color: #777;">
        {{ __('If the date has moved, change it on the task and this stops chasing you.') }}
    </p>

    <p style="margin: 0; text-align: center;">
        <a href="{{ route('tasks.mine') }}" style="display: inline-block; background-color: #dc2626; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open My Tasks') }}
        </a>
    </p>
</x-email-shell>
