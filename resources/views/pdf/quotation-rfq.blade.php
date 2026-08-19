<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Quotation Request') }} {{ $quotation->quotation_number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 20px;">

    <!-- Header -->
    <table style="width: 100%; border: none; margin-bottom: 15px; border-bottom: 2px solid #3F5189; padding-bottom: 10px;">
        <tr>
            <td style="width: 55%; vertical-align: top; border: none; padding: 0;">
                @if($company)
                    @if($company->logo)
                        @php
                            $logoPath = storage_path('app/public/' . $company->logo);
                            $logoData = '';
                            if (file_exists($logoPath)) {
                                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                                $mime = match($ext) {
                                    'png' => 'image/png',
                                    'svg' => 'image/svg+xml',
                                    'gif' => 'image/gif',
                                    default => 'image/jpeg',
                                };
                                $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
                            }
                        @endphp
                        @if($logoData)
                            <img src="{{ $logoData }}" style="max-height: 50px; max-width: 180px; margin-bottom: 5px;">
                        @endif
                    @endif
                    <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
                    <div style="font-size: 8pt; color: #666;">
                        {{ $company->full_address ?? '' }}<br>
                        @if($company->phone)P: {{ $company->phone }}@endif
                        @if($company->email) | {{ $company->email }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top; text-align: right; border: none; padding: 0;">
                <div style="font-size: 16pt; font-weight: bold; color: #3F5189;">{{ __('QUOTATION REQUEST') }}</div>
                <div style="font-size: 10pt; color: #555;">{{ $quotation->quotation_number }}</div>
                <div style="font-size: 8pt; color: #666;">{{ now()->format('M d, Y') }}</div>
            </td>
        </tr>
    </table>

    <!-- Who it is for, and the terms of the round -->
    <table style="width: 100%; border: none; margin-bottom: 18px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                <div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 5px;">{{ __('To') }}</div>
                <div style="font-size: 9pt;">
                    <strong>{{ $vendor?->name ?? '—' }}</strong><br>
                    @if($vendor?->contact_name){{ $vendor->contact_name }}<br>@endif
                    @if($quotationVendor?->bestEmail()){{ $quotationVendor->bestEmail() }}<br>@endif
                    @if($vendor?->phone){{ $vendor->phone }}@endif
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                <div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 5px;">{{ __('Request Details') }}</div>
                <table style="width: 100%; border: none; font-size: 9pt;">
                    <tr>
                        <td style="border: none; padding: 1px 0; color: #666;">{{ __('Type') }}</td>
                        <td style="border: none; padding: 1px 0; text-align: right;">{{ $quotation->getTypeLabel() }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 1px 0; color: #666;">{{ __('Responses Due') }}</td>
                        <td style="border: none; padding: 1px 0; text-align: right;">
                            {{ $quotation->responses_due_at?->format('M d, Y') ?? __('As soon as possible') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 1px 0; color: #666;">{{ __('Needed On Site') }}</td>
                        <td style="border: none; padding: 1px 0; text-align: right;">
                            {{ $quotation->needed_by?->format('M d, Y') ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 1px 0; color: #666;">{{ __('Delivery Location') }}</td>
                        <td style="border: none; padding: 1px 0; text-align: right;">{{ $deliveryLocation }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="font-size: 11pt; font-weight: bold; color: #3F5189; margin-bottom: 6px;">{{ $quotation->title }}</div>

    @if($quotation->description)
        <div style="font-size: 9pt; margin-bottom: 14px; white-space: pre-line;">{{ $quotation->description }}</div>
    @endif

    <!-- The scope, with empty price columns for the vendor to fill in -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        <thead>
            <tr style="background-color: #3F5189; color: #fff;">
                <th style="padding: 6px; text-align: left; font-size: 8pt; border: 1px solid #3F5189;">#</th>
                <th style="padding: 6px; text-align: left; font-size: 8pt; border: 1px solid #3F5189;">{{ __('Item') }}</th>
                <th style="padding: 6px; text-align: right; font-size: 8pt; border: 1px solid #3F5189;">{{ __('Qty') }}</th>
                <th style="padding: 6px; text-align: left; font-size: 8pt; border: 1px solid #3F5189;">{{ __('Unit') }}</th>
                <th style="padding: 6px; text-align: right; font-size: 8pt; border: 1px solid #3F5189; width: 70px;">{{ __('Unit Price') }}</th>
                <th style="padding: 6px; text-align: right; font-size: 8pt; border: 1px solid #3F5189; width: 70px;">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
                <tr>
                    <td style="padding: 5px 6px; font-size: 8pt; border: 1px solid #ddd; vertical-align: top;">{{ $loop->iteration }}</td>
                    <td style="padding: 5px 6px; font-size: 8pt; border: 1px solid #ddd;">
                        <strong>{{ $item->item_name }}</strong>
                        @if($item->description)
                            <div style="color: #666; font-size: 7.5pt; white-space: pre-line;">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td style="padding: 5px 6px; font-size: 8pt; border: 1px solid #ddd; text-align: right;">
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}
                    </td>
                    <td style="padding: 5px 6px; font-size: 8pt; border: 1px solid #ddd;">{{ $item->unit ?? '—' }}</td>
                    <td style="padding: 5px 6px; border: 1px solid #ddd;">&nbsp;</td>
                    <td style="padding: 5px 6px; border: 1px solid #ddd;">&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- What the proposal must state, so the offers can be compared fairly -->
    <div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 5px;">{{ __('Please state in your proposal') }}</div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        <tr>
            <td style="width: 33%; padding: 6px; border: 1px solid #ddd; font-size: 8pt;">
                {{ __('Freight: CIF or FOB, and the amount') }}<br><br>
            </td>
            <td style="width: 33%; padding: 6px; border: 1px solid #ddd; font-size: 8pt;">
                {{ __('Taxes included, and the tax regime') }}<br><br>
            </td>
            <td style="width: 34%; padding: 6px; border: 1px solid #ddd; font-size: 8pt;">
                {{ __('Lead time in days') }}<br><br>
            </td>
        </tr>
        <tr>
            <td style="padding: 6px; border: 1px solid #ddd; font-size: 8pt;">
                {{ __('Payment terms') }}<br><br>
            </td>
            <td style="padding: 6px; border: 1px solid #ddd; font-size: 8pt;">
                {{ __('How long the proposal stands') }}<br><br>
            </td>
            <td style="padding: 6px; border: 1px solid #ddd; font-size: 8pt;">
                {{ __('Any substitute brand or spec offered') }}<br><br>
            </td>
        </tr>
    </table>

    <div style="font-size: 8pt; color: #666; border-top: 1px solid #ddd; padding-top: 8px;">
        {{ __('Reply to this e-mail with your proposal attached. Prices will be compared line by line, equalized for freight, taxes and lead time.') }}
        @if($replyTo)
            <br>{{ __('Questions: :email', ['email' => $replyTo]) }}
        @endif
    </div>

</body>
</html>
