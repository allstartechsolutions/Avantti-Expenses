@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    $invited = $quotation->quotationVendors->whereNotIn('status', ['declined']);
@endphp

<x-email-shell :heading="__('Quotation')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ __(':actor cancelled this quotation round. Stop chasing it.', ['actor' => $actor->name]) }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $quotation->quotation_number }}</strong> — {{ $quotation->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $quotation->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $quotation->jobSite?->job_site_name ?? __('Project Level') }}<br>
                    @if($quotation->requisition)
                        {{ __('Requisition') }}: {{ $quotation->requisition->requisition_number }}<br>
                    @endif
                    {{ __('Responses Due') }}: {{ $quotation->responses_due_at?->format($dateFormat) ?? __('No date given') }}<br>
                    {{ __('Proposals') }}: {{ $quotation->respondedCount() }} / {{ $quotation->invitedCount() }}
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

    {{-- The vendors are the reason this mail exists: the system will not tell
         them anything, so the person who invited them has to. --}}
    @if($invited->isNotEmpty())
        <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #555;">
            {{ __('Vendors you asked, who have not been told') }}
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px; font-size: 13px;">
            @foreach($invited->take(10) as $row)
                <tr>
                    <td style="padding: 5px 0; border-bottom: 1px solid #f1f3f5; color: #555;">{{ $row->vendor?->name ?? __('Unknown') }}</td>
                    <td style="padding: 5px 0; border-bottom: 1px solid #f1f3f5; color: #777; text-align: right; white-space: nowrap;">{{ $row->getStatusLabel() }}</td>
                </tr>
            @endforeach
        </table>
        <p style="margin: 0 0 18px; font-size: 13px; color: #777;">
            {{ __('Nothing has been sent to them about this. Let them know the round is off.') }}
        </p>
    @endif

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Round') }}
        </a>
    </p>
</x-email-shell>
