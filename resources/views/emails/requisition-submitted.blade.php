
<x-email-shell :heading="__('Requisition')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ __(':actor submitted this requisition and it is waiting for a decision.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $requisition->requisition_number }}</strong> — {{ $requisition->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $requisition->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $requisition->getLocationDisplay() }}<br>
                    {{ __('Asked for by') }}: {{ $requisition->getRequesterName() }}<br>
                    {{ __('Needed By') }}:
                    <strong @if($requisition->isOverdue()) style="color: #b91c1c;" @endif>{{ $requisition->needed_by?->appDate() ?? __('No date given') }}</strong>
                    @if($requisition->isOverdue()) · {{ __('Overdue') }} @endif
                    <br>
                    {{ __('Priority') }}: {{ $requisition->getPriorityLabel() }}<br>
                    {{ __('Items') }}: {{ trans_choice(':count item|:count items', $requisition->items->count(), ['count' => $requisition->items->count()]) }}
                </div>

                @if($requisition->justification)
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e9ecef; color: #555; font-size: 13px; white-space: pre-line;">{{ \Illuminate\Support\Str::limit($requisition->justification, 400) }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if($requisition->items->isNotEmpty())
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px; font-size: 13px;">
            @foreach($requisition->items->take(10) as $item)
                <tr>
                    <td style="padding: 5px 0; border-bottom: 1px solid #f1f3f5; color: #555;">{{ $item->item_name }}</td>
                    <td style="padding: 5px 0; border-bottom: 1px solid #f1f3f5; color: #777; text-align: right; white-space: nowrap;">
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}
                    </td>
                </tr>
            @endforeach
            @if($requisition->items->count() > 10)
                <tr>
                    <td colspan="2" style="padding: 6px 0; color: #777;">
                        {{ trans_choice('and :count more item|and :count more items', $requisition->items->count() - 10, ['count' => $requisition->items->count() - 10]) }}
                    </td>
                </tr>
            @endif
        </table>
    @endif

    <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
        {{ __('Approving it also hands it to somebody to get prices for.') }}
    </p>

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Review the Requisition') }}
        </a>
    </p>
</x-email-shell>
