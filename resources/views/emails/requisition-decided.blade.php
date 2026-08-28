@php $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'; @endphp

<x-email-shell :heading="__('Requisition')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ $approved
            ? __(':actor approved the requisition you asked for.', ['actor' => $actor->name])
            : __(':actor rejected the requisition you asked for.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: {{ $approved ? '#f0fdf4' : '#fef2f2' }}; border-radius: 6px; border: 1px solid {{ $approved ? '#bbf7d0' : '#fecaca' }}; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $requisition->requisition_number }}</strong> — {{ $requisition->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $requisition->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $requisition->getLocationDisplay() }}<br>
                    {{ __('Needed By') }}: {{ $requisition->needed_by?->format($dateFormat) ?? __('No date given') }}<br>
                    {{ __('Items') }}: {{ trans_choice(':count item|:count items', $requisition->items->count(), ['count' => $requisition->items->count()]) }}
                </div>
            </td>
        </tr>
    </table>

    {{-- The reason is the whole point of a rejection: it is a required field,
         and this is where the person who has to act on it finally reads it. --}}
    @if($requisition->review_notes)
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #555;">
            {{ $approved ? __('Note from the approver') : __('Why it was rejected') }}
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
            <tr>
                <td style="padding: 12px 16px; font-size: 13px; color: #555; white-space: pre-line;">{{ $requisition->review_notes }}</td>
            </tr>
        </table>
    @elseif(! $approved)
        <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
            {{ __('No reason was recorded. Ask :name before raising it again.', ['name' => $actor->name]) }}
        </p>
    @endif

    <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
        @if($approved && $requisition->assignedBuyer)
            {{ __(':name is getting prices for it.', ['name' => $requisition->assignedBuyer->name]) }}
        @elseif($approved)
            {{ __('It is waiting for somebody to be given the job of getting prices.') }}
        @else
            {{ __('Nothing will be bought against it. Raise a new one if the need has not gone away.') }}
        @endif
    </p>

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Requisition') }}
        </a>
    </p>
</x-email-shell>
