{{--
    The signature block.

    In Brazil a shop drawing signed off without the responsible professional's
    CREA/CAU registration and the ART number is not worth much, so both print
    where they exist.

    A signature whose hash no longer matches what it signed says so, plainly.
    Printing it silently would let the sheet claim more than it can.
--}}
@if($signatures->isNotEmpty())
    <div style="margin-top: 16px; page-break-inside: avoid;">
        <div style="font-size: 9pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 6px;">
            {{ __('collaboration.label.signatures') }}
        </div>

        @foreach($signatures as $row)
            @php $signature = $row['signature']; @endphp
            <table style="width: 100%; border: none; margin-bottom: 10px;">
                <tr>
                    <td style="border: none; padding: 0; vertical-align: bottom;">
                        <div style="border-bottom: 1px solid #333; width: 260px; height: 22px;"></div>
                        <div style="font-size: 9pt; font-weight: bold; margin-top: 3px;">{{ $signature->signer_name }}</div>
                        @if($signature->signer_document)
                            <div style="font-size: 8pt; color: #444;">{{ $signature->signer_document }}</div>
                        @endif
                        @if($signature->art_number)
                            <div style="font-size: 8pt; color: #444;">{{ __('collaboration.pdf.art') }} {{ $signature->art_number }}</div>
                        @endif
                        @if($signature->user?->company_name)
                            <div style="font-size: 8pt; color: #666;">{{ $signature->user->company_name }}</div>
                        @endif
                    </td>
                    <td style="border: none; padding: 0; text-align: right; vertical-align: bottom; font-size: 7pt; color: #666;">
                        <div>{{ $signature->getMethodLabel() }}</div>
                        <div>{{ $signature->signed_at?->format(config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A') }}</div>
                        @unless($row['intact'])
                            <div style="color: #b91c1c; font-weight: bold; margin-top: 2px;">
                                {{ __('collaboration.help.document_changed_since_signed') }}
                            </div>
                        @endunless
                    </td>
                </tr>
            </table>
        @endforeach
    </div>
@endif
