<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Contract Payments Report') }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.4; color: #333; margin: 0; padding: 15px;">

    <!-- Header -->
    <table style="width: 100%; border: none; margin-bottom: 12px; border-bottom: 2px solid #3F5189; padding-bottom: 8px;">
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
                            <img src="{{ $logoData }}" style="max-height: 40px; max-width: 150px; margin-bottom: 4px;">
                        @endif
                    @endif
                    <div style="font-size: 12pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
                    <div style="font-size: 7pt; color: #666;">
                        {{ $company->full_address ?? '' }}
                        @if($company->phone) | P: {{ $company->phone }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right; border: none; padding: 0;">
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('CONTRACT PAYMENTS') }}</div>
                <div style="font-size: 8pt; color: #555;">{{ __('Generated') }}: {{ $generatedAt->appDateTime() }}</div>
                @if(count($filters) > 0)
                    <div style="font-size: 7pt; color: #888; margin-top: 3px;">
                        {{ __('Filters') }}: {{ implode(' | ', $filters) }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Summary -->
    <table style="width: 100%; border: none; margin-bottom: 15px;">
        <tr>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Contracts') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #333;">{{ $summary['count'] }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Original Value') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #333;">${{ number_format($summary['total_value'], 2) }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Change Orders') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: {{ $summary['total_change_orders'] >= 0 ? '#27ae60' : '#e74c3c' }};">
                    {{ $summary['total_change_orders'] >= 0 ? '+' : '' }}${{ number_format($summary['total_change_orders'], 2) }}
                </div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Total Paid') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #27ae60;">${{ number_format($summary['total_paid'], 2) }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Balance Due') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: {{ $summary['total_balance'] > 0 ? '#e67e22' : '#27ae60' }};">${{ number_format($summary['total_balance'], 2) }}</div>
            </td>
        </tr>
    </table>

    <!-- Contracts Table -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: left; font-size: 7pt; font-weight: bold;">{{ __('Subcontractor') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: left; font-size: 7pt; font-weight: bold;">{{ __('Project') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: left; font-size: 7pt; font-weight: bold;">{{ __('Job Site') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: left; font-size: 7pt; font-weight: bold;">{{ __('Contract #') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: right; font-size: 7pt; font-weight: bold;">{{ __('Amount') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: right; font-size: 7pt; font-weight: bold;">{{ __('CO (+/-)') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: right; font-size: 7pt; font-weight: bold;">{{ __('Adjusted') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: right; font-size: 7pt; font-weight: bold;">{{ __('Paid') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: right; font-size: 7pt; font-weight: bold;">{{ __('Balance') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: center; font-size: 7pt; font-weight: bold;">{{ __('Status') }}</th>
                <th style="background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; text-align: left; font-size: 7pt; font-weight: bold;">{{ __('Last Payment') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contracts as $index => $contract)
                @php
                    $totalPaid = ($contract->total_paid_cents ?? 0) / 100;
                    $coTotal = ($contract->change_orders_total_cents ?? 0) / 100;
                    $adjusted = round($contract->amount + $coTotal, 2);
                    $balance = round($adjusted - $totalPaid, 2);
                    $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                    $isPaidOrCancelled = in_array($contract->status, ['paid', 'cancelled']);
                @endphp
                <tr style="background-color: {{ $bgColor }};">
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; {{ $isPaidOrCancelled ? 'color: #999;' : '' }}">
                        {{ $contract->subcontractor?->company_name ?? '—' }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; {{ $isPaidOrCancelled ? 'color: #999;' : '' }}">
                        {{ $contract->project->project_name }}
                        <br><span style="font-size: 6pt; color: #888;">{{ $contract->project->client?->company_name }}</span>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; {{ $isPaidOrCancelled ? 'color: #999;' : '' }}">
                        {{ $contract->jobSite?->job_site_name ?? __('Project General') }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; font-weight: bold; {{ $isPaidOrCancelled ? 'color: #999;' : '' }}">
                        {{ $contract->contract_number }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; text-align: right; {{ $isPaidOrCancelled ? 'color: #999;' : '' }}">
                        ${{ number_format($contract->amount, 2) }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; text-align: right; font-weight: bold; color: {{ $coTotal >= 0 ? '#27ae60' : '#e74c3c' }};">
                        @if($contract->changeOrders->count() > 0)
                            {{ $coTotal >= 0 ? '+' : '' }}${{ number_format($coTotal, 2) }}
                        @else
                            <span style="color: #ccc;">—</span>
                        @endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; text-align: right; font-weight: bold; {{ $isPaidOrCancelled ? 'color: #999;' : '' }}">
                        ${{ number_format($adjusted, 2) }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; text-align: right; color: #27ae60;">
                        ${{ number_format($totalPaid, 2) }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; text-align: right; font-weight: bold; color: {{ $balance > 0 ? '#e67e22' : '#27ae60' }};">
                        ${{ number_format($balance, 2) }}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt; text-align: center;">
                        @php
                            $statusColors = [
                                'draft' => '#95a5a6',
                                'active' => '#3498db',
                                'completed' => '#8e44ad',
                                'partially_paid' => '#e67e22',
                                'paid' => '#27ae60',
                                'cancelled' => '#e74c3c',
                            ];
                            $statusColor = $statusColors[$contract->status] ?? '#555';
                        @endphp
                        <span style="color: {{ $statusColor }}; font-weight: bold;">
                            {{ $contract->getStatusLabel() }}
                        </span>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px; font-size: 7pt;">
                        @if($contract->latestPayment)
                            {{ $contract->latestPayment->payment_date->appDate() }}
                            <br><span style="color: #27ae60;">${{ number_format($contract->latestPayment->amount, 2) }}</span>
                        @else
                            <span style="color: #ccc;">—</span>
                        @endif
                    </td>
                </tr>

                {{-- Change Order Detail Rows --}}
                @if($contract->changeOrders->count() > 0)
                    <tr style="background-color: {{ $bgColor }};">
                        <td colspan="11" style="border: 1px solid #ddd; border-top: none; padding: 2px 4px {{ $includePayments && $contract->payments->count() > 0 ? '2px' : '6px' }} 20px; font-size: 7pt;">
                            <table style="width: auto; border-collapse: collapse; margin-top: 0;">
                                <tr>
                                    <td style="border: none; padding: 1px 8px 1px 0; font-size: 6pt; font-weight: bold; color: #3F5189; text-transform: uppercase;" colspan="4">
                                        {{ __('Change Orders') }} ({{ $contract->changeOrders->count() }})
                                    </td>
                                </tr>
                                @foreach($contract->changeOrders as $co)
                                    <tr>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #666; white-space: nowrap;">{{ $co->date->appDate() }}</td>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #333; font-weight: bold;">{{ $co->title }}</td>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #888;">{{ \Illuminate\Support\Str::limit($co->description, 60) ?? '' }}</td>
                                        <td style="border: none; padding: 1px 0; font-size: 7pt; font-weight: bold; text-align: right; color: {{ $co->amount >= 0 ? '#27ae60' : '#e74c3c' }}; white-space: nowrap;">
                                            {{ $co->amount >= 0 ? '+' : '' }}${{ number_format($co->amount, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Payment History Rows --}}
                @if($includePayments && $contract->payments->count() > 0)
                    <tr style="background-color: {{ $bgColor }};">
                        <td colspan="11" style="border: 1px solid #ddd; border-top: none; padding: 2px 4px 6px 20px; font-size: 7pt;">
                            <table style="width: auto; border-collapse: collapse; margin-top: 0;">
                                <tr>
                                    <td style="border: none; padding: 1px 8px 1px 0; font-size: 6pt; font-weight: bold; color: #27ae60; text-transform: uppercase;" colspan="5">
                                        {{ __('Payments') }} ({{ $contract->payments->count() }})
                                    </td>
                                </tr>
                                @foreach($contract->payments as $payment)
                                    <tr>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #666; white-space: nowrap;">{{ $payment->payment_date->appDate() }}</td>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #333;">{{ $payment->payment_method ? $payment->getPaymentMethodLabel() : '—' }}</td>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #888;">{{ $payment->reference_number ?? '' }}</td>
                                        <td style="border: none; padding: 1px 8px 1px 0; font-size: 7pt; color: #888;">{{ $payment->notes ?? '' }}</td>
                                        <td style="border: none; padding: 1px 0; font-size: 7pt; font-weight: bold; text-align: right; color: #27ae60; white-space: nowrap;">
                                            ${{ number_format($payment->amount, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <!-- Footer -->
    <div style="margin-top: 20px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 7pt; color: #888; text-align: center;">
        {{ $company->name ?? config('app.name') }} — {{ __('Contract Payments Report') }} — {{ $generatedAt->appDate() }}
    </div>

</body>
</html>
