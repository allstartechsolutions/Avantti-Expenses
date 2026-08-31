
<x-email-shell :heading="__('Requisition')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ trans_choice(
            'This requisition was handed to you :count day ago and still has no quotation round.|This requisition was handed to you :count days ago and still has no quotation round.',
            $daysWaiting,
            ['count' => $daysWaiting],
        ) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fffbeb; border-radius: 6px; border: 1px solid #fde68a; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $requisition->requisition_number }}</strong> — {{ $requisition->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $requisition->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $requisition->getLocationDisplay() }}<br>
                    {{ __('Needed By') }}:
                    <strong @if($requisition->isOverdue()) style="color: #b91c1c;" @endif>{{ $requisition->needed_by?->appDate() ?? __('No date given') }}</strong>
                    @if($requisition->isOverdue()) · {{ __('Overdue') }} @endif
                    <br>
                    {{ __('Priority') }}: {{ $requisition->getPriorityLabel() }}<br>
                    {{ __('Items') }}: {{ trans_choice(':count item|:count items', $requisition->items->count(), ['count' => $requisition->items->count()]) }}
                </div>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
        {{ __('If somebody else should be doing this, say so on the requisition and it will move off your list.') }}
    </p>

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Start the Quotation Round') }}
        </a>
    </p>
</x-email-shell>
