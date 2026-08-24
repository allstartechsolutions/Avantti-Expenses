<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 20px;">

    <!-- Header -->
    <table style="width: 100%; border: none; margin-bottom: 15px; border-bottom: 2px solid #3F5189; padding-bottom: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
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
            <td style="width: 50%; vertical-align: top; text-align: right; border: none; padding: 0;">
                <div style="font-size: 18pt; font-weight: bold; color: #3F5189;">{{ __('INVOICE') }}</div>
                <div style="font-size: 10pt; color: #555;">{{ $invoice->invoice_number }}</div>
            </td>
        </tr>
    </table>

    <!-- Client Info + Invoice Details -->
    <table style="width: 100%; border: none; margin-bottom: 20px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                <div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 5px;">{{ __('Bill To') }}</div>
                <div style="font-size: 9pt;">
                    <strong>{{ $invoice->client->company_name }}</strong><br>
                    @if($invoice->client->contact_name)
                        {{ $invoice->client->contact_name }}<br>
                    @endif
                    @if($invoice->client->full_address)
                        {{ $invoice->client->full_address }}<br>
                    @endif
                    @if($invoice->client->email)
                        {{ $invoice->client->email }}<br>
                    @endif
                    @if($invoice->client->phone)
                        {{ $invoice->client->phone }}
                    @endif
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="border: none; padding: 2px 5px; font-size: 8pt; font-weight: bold; color: #555; text-align: right;">{{ __('Invoice Date:') }}</td>
                        <td style="border: none; padding: 2px 5px; font-size: 9pt; text-align: right;">{{ $invoice->invoice_date->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 2px 5px; font-size: 8pt; font-weight: bold; color: #555; text-align: right;">{{ __('Terms:') }}</td>
                        <td style="border: none; padding: 2px 5px; font-size: 9pt; text-align: right;">{{ $invoice->terms_label }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 2px 5px; font-size: 8pt; font-weight: bold; color: #555; text-align: right;">{{ __('Due Date:') }}</td>
                        <td style="border: none; padding: 2px 5px; font-size: 9pt; text-align: right;">{{ $invoice->due_date->format('M d, Y') }}</td>
                    </tr>
                    @if($invoice->project)
                    <tr>
                        <td style="border: none; padding: 2px 5px; font-size: 8pt; font-weight: bold; color: #555; text-align: right;">{{ __('Project:') }}</td>
                        <td style="border: none; padding: 2px 5px; font-size: 9pt; text-align: right;">{{ $invoice->project->project_name }}</td>
                    </tr>
                    @endif
                    @if($invoice->jobSite)
                    <tr>
                        <td style="border: none; padding: 2px 5px; font-size: 8pt; font-weight: bold; color: #555; text-align: right;">{{ __('Job Site:') }}</td>
                        <td style="border: none; padding: 2px 5px; font-size: 9pt; text-align: right;">{{ $invoice->jobSite->job_site_name }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: left; font-size: 8pt; font-weight: bold;">{{ __('Item') }}</th>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: center; font-size: 8pt; font-weight: bold; width: 50px;">{{ __('Qty') }}</th>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: center; font-size: 8pt; font-weight: bold; width: 50px;">{{ __('Unit') }}</th>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: right; font-size: 8pt; font-weight: bold; width: 70px;">{{ __('Unit Price') }}</th>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: right; font-size: 8pt; font-weight: bold; width: 65px;">{{ __('Discount') }}</th>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: right; font-size: 8pt; font-weight: bold; width: 55px;">{{ __('Tax') }}</th>
            <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 6px 8px; text-align: right; font-size: 8pt; font-weight: bold; width: 70px;">{{ __('Total') }}</th>
        </tr>
        @foreach($invoice->items as $index => $item)
        <tr style="background-color: {{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt;">
                <strong>{{ $item->item_name }}</strong>
                @if($item->description)
                    <br><span style="font-size: 7pt; color: #666;">{{ $item->description }}</span>
                @endif
            </td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt; text-align: center;">{{ $item->quantity }}</td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt; text-align: center;">{{ $item->unit ?? '—' }}</td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt; text-align: right;">${{ number_format($item->unit_price, 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt; text-align: right;">
                @if($item->discount_amount > 0)
                    -${{ number_format($item->discount_amount, 2) }}
                @else
                    —
                @endif
            </td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt; text-align: right;">
                @if($item->is_taxable && $item->tax_amount > 0)
                    ${{ number_format($item->tax_amount, 2) }}
                @else
                    —
                @endif
            </td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; font-size: 8pt; text-align: right; font-weight: bold;">${{ number_format($item->total_amount, 2) }}</td>
        </tr>
        @endforeach
    </table>

    <!-- Totals Section -->
    <table style="width: 100%; border: none; margin-bottom: 25px;">
        <tr>
            <td style="width: 60%; border: none; padding: 0;"></td>
            <td style="width: 40%; border: none; padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right; color: #555;">{{ __('Subtotal') }}</td>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right;">${{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    @if($invoice->discount_amount > 0)
                    <tr>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right; color: #555;">
                            {{ __('Discount') }}
                            @if($invoice->discount_type === 'percentage')
                                ({{ number_format($invoice->discount_value, 2) }}%)
                            @endif
                        </td>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right; color: #c0392b;">-${{ number_format($invoice->discount_amount, 2) }}</td>
                    </tr>
                    @endif
                    @if($invoice->tax_total > 0)
                    <tr>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right; color: #555;">{{ __('Tax') }}</td>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right;">${{ number_format($invoice->tax_total, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="border-top: 2px solid #3F5189; padding: 8px 8px 4px; font-size: 11pt; font-weight: bold; text-align: right; color: #3F5189;">{{ __('Total') }}</td>
                        <td style="border-top: 2px solid #3F5189; padding: 8px 8px 4px; font-size: 11pt; font-weight: bold; text-align: right; color: #3F5189;">${{ number_format($invoice->total_amount, 2) }}</td>
                    </tr>
                    @if($invoice->payments->where('status', 'completed')->count() > 0)
                    <tr>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right; color: #27ae60;">{{ __('Amount Paid') }}</td>
                        <td style="border: none; padding: 4px 8px; font-size: 9pt; text-align: right; color: #27ae60;">-${{ number_format($invoice->getAmountPaid(), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #ddd; padding: 6px 8px 4px; font-size: 10pt; font-weight: bold; text-align: right; color: {{ $invoice->getBalanceDue() > 0 ? '#e67e22' : '#27ae60' }};">{{ __('Balance Due') }}</td>
                        <td style="border-top: 1px solid #ddd; padding: 6px 8px 4px; font-size: 10pt; font-weight: bold; text-align: right; color: {{ $invoice->getBalanceDue() > 0 ? '#e67e22' : '#27ae60' }};">${{ number_format($invoice->getBalanceDue(), 2) }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Payments Section -->
    @if($invoice->payments->where('status', 'completed')->count() > 0)
    <div style="margin-bottom: 20px;">
        <div style="font-size: 9pt; font-weight: bold; color: #3F5189; margin-bottom: 8px;">{{ __('Payments Received') }}</div>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <th style="background-color: #f0f0f0; border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #555;">#</th>
                <th style="background-color: #f0f0f0; border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #555;">{{ __('Date') }}</th>
                <th style="background-color: #f0f0f0; border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #555;">{{ __('Method') }}</th>
                <th style="background-color: #f0f0f0; border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #555;">{{ __('Reference') }}</th>
                <th style="background-color: #f0f0f0; border: 1px solid #ddd; padding: 5px 8px; text-align: right; font-size: 8pt; font-weight: bold; color: #555;">{{ __('Amount') }}</th>
            </tr>
            @foreach($invoice->payments->where('status', 'completed') as $payment)
            <tr>
                <td style="border: 1px solid #ddd; padding: 4px 8px; font-size: 8pt;">{{ $payment->payment_number }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; font-size: 8pt;">{{ $payment->payment_date->format('M d, Y') }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; font-size: 8pt;">{{ $payment->getPaymentMethodLabel() }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; font-size: 8pt;">{{ $payment->reference_number ?? '—' }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; font-size: 8pt; text-align: right;">${{ number_format($payment->amount, 2) }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <!-- Message Section -->
    @if($invoice->message_body)
    <div style="margin-bottom: 25px; border: 1px solid #ddd; padding: 15px; background-color: #fafafa;">
        <div style="font-size: 9pt; font-weight: bold; color: #3F5189; margin-bottom: 8px;">
            {{ $invoice->message_title ?? __('Message') }}
        </div>
        <div style="font-size: 8pt; color: #555; line-height: 1.5;">
            {!! strip_tags(App\Support\RichText::sanitize($invoice->message_body), '<br><p><ul><li><ol><strong><em>') !!}
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div style="margin-top: 40px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8pt; color: #666; text-align: center;">
        <div>{{ $company->name ?? config('app.name') }}</div>
        <div style="margin-top: 3px;">{{ __('Thank you for your business') }}</div>
    </div>

</body>
</html>
