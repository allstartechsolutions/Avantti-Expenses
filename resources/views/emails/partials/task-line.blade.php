{{-- One task in a list inside an e-mail. Expects: $task, $recipient --}}
@php
    $late = $task->isOverdue();
@endphp
<tr>
    <td style="padding: 9px 14px; border-bottom: 1px solid #f1f3f5; font-size: 13px;">
        <strong style="color: #3F5189;">{{ $task->code() }}</strong>
        {{ $task->title }}
        <div style="color: #777; font-size: 12px; margin-top: 2px;">
            {{ $task->getScopeLabel() }}
            @if($task->due_date)
                ·
                <span style="{{ $late ? 'color: #dc2626; font-weight: bold;' : '' }}">
                    {{ $task->due_date->appDate() }}@if($late) · {{ trans_choice(':count day late|:count days late', $task->daysOverdue(), ['count' => $task->daysOverdue()]) }}@endif
                </span>
            @else
                · {{ __('No due date') }}
            @endif
            · {{ $task->progress }}%
            @if($task->owner_id !== $recipient->id) · {{ $task->owner?->name }} @endif
        </div>
    </td>
</tr>
