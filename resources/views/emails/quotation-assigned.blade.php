@php $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'; @endphp

<x-email-shell :heading="__('Quotation')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ $owns
            ? __(':actor made you responsible for this quotation round. You are the person answerable for getting the prices in.', ['actor' => $actor->name])
            : __(':actor added you to this quotation round to help work it.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $quotation->quotation_number }}</strong> — {{ $quotation->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $quotation->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $quotation->jobSite?->job_site_name ?? __('Project Level') }}<br>
                    {{ __('Responses Due') }}:
                    <strong @if($quotation->responsesOverdue()) style="color: #b91c1c;" @endif>{{ $quotation->responses_due_at?->format($dateFormat) ?? __('No date given') }}</strong>
                    @if($quotation->responsesOverdue()) · {{ __('Overdue') }} @endif
                    <br>
                    {{ __('Needed On Site') }}: {{ $quotation->needed_by?->format($dateFormat) ?? __('No date given') }}<br>
                    {{ __('Items') }}: {{ trans_choice(':count item|:count items', $quotation->items->count(), ['count' => $quotation->items->count()]) }}<br>
                    {{ __('Vendors Invited') }}: {{ $quotation->quotationVendors->count() }}
                </div>

                @if(! $owns && $quotation->assignedTo)
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e9ecef; color: #555; font-size: 13px;">
                        {{ __('Owner') }}: {{ $quotation->assignedTo->name }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Round') }}
        </a>
    </p>
</x-email-shell>
