
<x-email-shell :heading="__('Requisition')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ $wasQuoting
            ? __(':actor cancelled this requisition. Stop getting prices for it — nothing will be bought against it.', ['actor' => $actor->name])
            : __(':actor cancelled the requisition you asked for.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $requisition->requisition_number }}</strong> — {{ $requisition->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $requisition->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $requisition->getLocationDisplay() }}<br>
                    {{ __('Asked for by') }}: {{ $requisition->getRequesterName() }}<br>
                    {{ __('Needed By') }}: {{ $requisition->needed_by?->appDate() ?? __('No date given') }}<br>
                    {{ __('Items') }}: {{ trans_choice(':count item|:count items', $requisition->items->count(), ['count' => $requisition->items->count()]) }}
                </div>
            </td>
        </tr>
    </table>

    @if($reason)
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #555;">{{ __('Why it was cancelled') }}</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
            <tr>
                <td style="padding: 12px 16px; font-size: 13px; color: #555; white-space: pre-line;">{{ $reason }}</td>
            </tr>
        </table>
    @endif

    <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
        {{ $wasQuoting
            ? __('If you have already asked vendors for prices, let them know it is off.')
            : __('Raise a new one if the need has not gone away.') }}
    </p>

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Requisition') }}
        </a>
    </p>
</x-email-shell>
