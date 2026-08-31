
<x-email-shell :heading="__('Your open tasks')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ __('Here is everything still open that you own or are helping with.') }}
    </p>

    @foreach([
        ['rows' => $overdue,  'label' => __('Overdue'),               'colour' => '#dc2626'],
        ['rows' => $awaiting, 'label' => __('Awaiting confirmation'), 'colour' => '#d97706'],
        ['rows' => $thisWeek, 'label' => __('Due this week'),         'colour' => '#3F5189'],
        ['rows' => $later,    'label' => __('Later'),                 'colour' => '#64748b'],
    ] as $group)
        @if($group['rows']->isNotEmpty())
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; color: {{ $group['colour'] }};">
                {{ $group['label'] }} ({{ $group['rows']->count() }})
            </p>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e9ecef; border-radius: 6px; margin-bottom: 16px;">
                @foreach($group['rows'] as $task)
                    @include('emails.partials.task-line', ['task' => $task, 'recipient' => $recipient])
                @endforeach
            </table>
        @endif
    @endforeach

    @if($company)
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; margin-bottom: 16px;">
            <tr>
                <td style="padding: 14px 16px; font-size: 13px; color: #555;">
                    <strong style="color: #3F5189;">{{ __('Across the company') }}</strong><br>
                    {{ __(':open open, :overdue overdue.', ['open' => $company['open'], 'overdue' => $company['overdue']]) }}
                    @if($company['oldest']->isNotEmpty())
                        <div style="margin-top: 8px;">
                            {{ __('Open the longest:') }}
                            @foreach($company['oldest'] as $old)
                                <div style="color: #777; margin-top: 3px;">
                                    {{ $old->code() }} — {{ \Illuminate\Support\Str::limit($old->title, 60) }}
                                    <span style="color: #999;">({{ $old->owner?->name }}, {{ (int) $old->created_at->diffInDays(now()) }} {{ __('days') }})</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <p style="margin: 0; text-align: center;">
        <a href="{{ route('tasks.mine') }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open My Tasks') }}
        </a>
    </p>
</x-email-shell>
