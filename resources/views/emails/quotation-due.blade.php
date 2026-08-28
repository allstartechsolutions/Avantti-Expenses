@php $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'; @endphp

<x-email-shell :heading="__('Quotation')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ $overdue
            ? __('The response date on this round has passed and it is still open. Chase whoever has not priced it, or close the round with what you have.')
            : __('Responses on this round are due shortly. Anybody who has not priced it yet needs chasing now.') }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: {{ $overdue ? '#fef2f2' : '#f8f9fa' }}; border-radius: 6px; border: 1px solid {{ $overdue ? '#fecaca' : '#e9ecef' }}; margin-bottom: 18px;">
        <tr>
            <td style="padding: 14px 16px; font-size: 14px;">
                <strong style="color: #3F5189;">{{ $quotation->quotation_number }}</strong> — {{ $quotation->title }}
                <div style="color: #777; font-size: 13px; margin-top: 6px;">
                    {{ __('Project') }}: {{ $quotation->project?->project_name }}<br>
                    {{ __('Where') }}: {{ $quotation->jobSite?->job_site_name ?? __('Project Level') }}<br>
                    {{ __('Responses Due') }}:
                    <strong @if($overdue) style="color: #b91c1c;" @endif>{{ $quotation->responses_due_at?->format($dateFormat) ?? __('No date given') }}</strong><br>
                    {{ __('Proposals') }}: {{ $quotation->respondedCount() }} / {{ $quotation->invitedCount() }}
                </div>
            </td>
        </tr>
    </table>

    @php $waiting = $quotation->quotationVendors->filter(fn ($row) => ! $row->hasResponded()); @endphp
    @if($waiting->isNotEmpty())
        <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #555;">{{ __('Still to price') }}</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px; font-size: 13px;">
            @foreach($waiting->take(10) as $row)
                <tr>
                    <td style="padding: 5px 0; border-bottom: 1px solid #f1f3f5; color: #555;">{{ $row->vendor?->name ?? __('Unknown') }}</td>
                    <td style="padding: 5px 0; border-bottom: 1px solid #f1f3f5; color: #777; text-align: right; white-space: nowrap;">{{ $row->getStatusLabel() }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ $link }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Round') }}
        </a>
    </p>
</x-email-shell>
