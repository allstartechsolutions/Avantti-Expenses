<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Job Site Financial Report') }} — {{ $jobSite->job_site_name }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 15px;">

    {{-- Header --}}
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
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('JOB SITE FINANCIAL REPORT') }}</div>
                <div style="font-size: 9pt; color: #555;">{{ $jobSite->job_site_name }}</div>
                <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->appDateTime() }}</div>
            </td>
        </tr>
    </table>

    {{-- Meta --}}
    <table style="width: 100%; border: none; margin-bottom: 12px; font-size: 8pt;">
        <tr>
            <td style="width: 33%; border: none; padding: 2px 0;">
                <strong style="color: #555;">{{ __('Project') }}:</strong> {{ $jobSite->project->project_name }}
            </td>
            <td style="width: 33%; border: none; padding: 2px 0;">
                <strong style="color: #555;">{{ __('Client') }}:</strong> {{ $jobSite->project->client?->company_name ?? '—' }}
            </td>
            <td style="width: 34%; border: none; padding: 2px 0;">
                <strong style="color: #555;">{{ __('Supervisor') }}:</strong> {{ $jobSite->supervisor?->name ?? '—' }}
            </td>
        </tr>
    </table>

    {{-- Summary cards --}}
    @php
        $isProfit = $financials['profit'] >= 0;
        $profitColor = $isProfit ? '#27ae60' : '#e74c3c';
    @endphp
    <table style="width: 100%; border: none; margin-bottom: 15px;">
        <tr>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Contract Value') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #3F5189;">${{ number_format($financials['contract_value'], 2) }}</div>
                <div style="font-size: 6.5pt; color: #888; margin-top: 2px;">
                    {{ __('Base') }}: ${{ number_format($financials['base_contract_value'], 2) }}
                    @if($financials['change_orders_total'] != 0)
                        · {{ $financials['change_orders_total'] >= 0 ? '+' : '' }}${{ number_format($financials['change_orders_total'], 2) }} CO
                    @endif
                </div>
            </td>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Expenses') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #e74c3c;">${{ number_format($financials['total_expenses'], 2) }}</div>
                <div style="font-size: 6.5pt; color: #888; margin-top: 2px;">{{ trans_choice(':count item|:count items', $financials['expenses_count'], ['count' => $financials['expenses_count']]) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Contracts') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #8e44ad;">${{ number_format($financials['contracts_adjusted'], 2) }}</div>
                <div style="font-size: 6.5pt; color: #888; margin-top: 2px;">
                    {{ __('Paid') }}: ${{ number_format($financials['contracts_paid'], 2) }} ·
                    {{ __('Unpaid') }}: ${{ number_format($financials['contracts_unpaid'], 2) }}
                </div>
            </td>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ $isProfit ? __('Profit') : __('Loss') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: {{ $profitColor }};">
                    {{ $isProfit ? '' : '−' }}${{ number_format(abs($financials['profit']), 2) }}
                </div>
                <div style="font-size: 6.5pt; color: #888; margin-top: 2px;">{{ __('Contract Value − Expenses − Contracts') }}</div>
            </td>
        </tr>
    </table>

    {{-- Contract Value Breakdown --}}
    <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('Contract Value Breakdown') }}</div>
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 8pt; margin-bottom: 12px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 5px 8px; text-align: left;">{{ __('Item') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 8px; text-align: left;">{{ __('Date') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 8px; text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border: 1px solid #ddd; padding: 4px 8px;">{{ __('Job Amount') }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; color: #888;">—</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; text-align: right;">${{ number_format($financials['base_contract_value'], 2) }}</td>
            </tr>

            <tr><td colspan="3" style="border: 1px solid #ddd; padding: 4px 8px; background-color: #fafafa; font-weight: bold; color: #555;">{{ __('Change Orders') }}</td></tr>
            @forelse($changeOrders as $co)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 4px 8px;">{{ $co['title'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 8px; color: #666;">
                        {{ $co['date']?->appDate() }}
                        @if(($co['status'] ?? 'approved') !== 'approved')
                            · <span style="color: #b7791f;">{{ __('not approved') }}</span>
                        @endif
                        @if(($co['cost'] ?? 0.0) != 0.0)
                            · {{ __('cost') }} ${{ number_format($co['cost'], 2) }}
                            · {{ __('margin') }} ${{ number_format($co['amount'] - $co['cost'], 2) }}
                        @else
                            · <span style="color: #999;">{{ __('no cost breakdown') }}</span>
                        @endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px 8px; text-align: right; color: {{ $co['amount'] < 0 ? '#e74c3c' : '#27ae60' }};">
                        {{ $co['amount'] >= 0 ? '+' : '' }}${{ number_format($co['amount'], 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="border: 1px solid #ddd; padding: 6px 8px; text-align: center; color: #999; font-style: italic;">{{ __('No change orders.') }}</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="2" style="border: 1px solid #ddd; padding: 4px 8px; text-align: right; font-weight: bold;">{{ __('Subtotal') }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 8px; text-align: right; font-weight: bold; color: {{ $financials['change_orders_total'] < 0 ? '#e74c3c' : '#333' }};">
                    {{ $financials['change_orders_total'] >= 0 ? '+' : '' }}${{ number_format($financials['change_orders_total'], 2) }}
                </td>
            </tr>

            <tr style="background-color: #f3f4f6;">
                <td colspan="2" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; color: #3F5189;">{{ __('Net Contract Value') }}</td>
                <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: right; font-weight: bold; color: #3F5189;">${{ number_format($financials['contract_value'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Payment Schedule --}}
    @include('pdf.partials.cost-code-section', ['costCodes' => $costCodes, 'showLocation' => false])

    @include('pdf.partials.payment-schedule', ['paymentSchedule' => $paymentSchedule])

    {{-- Expenses --}}
    <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">
        {{ __('Expenses') }} <span style="font-size: 8pt; color: #888;">({{ $financials['expenses_count'] }})</span>
    </div>
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Date') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Item') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Supplier') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Status') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $expense)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $expense->expense_date?->appDate() }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $expense->item_name }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $expense->supplier?->name ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $expense->getStatusLabel() }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($expense->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">{{ __('No expenses recorded.') }}</td>
                </tr>
            @endforelse
            @if($expenses->count() > 0)
                <tr style="background-color: #f3f4f6; font-weight: bold;">
                    <td colspan="4" style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Total') }}</td>
                    <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">${{ number_format($financials['total_expenses'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Contracts --}}
    <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">
        {{ __('Contracts') }} <span style="font-size: 8pt; color: #888;">({{ $financials['contracts_count'] }})</span>
    </div>
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Contract #') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Subcontractor') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Status') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Amount') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Paid') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contracts as $contract)
                @php
                    $adjusted = $contract->getAdjustedAmount();
                    $paid = $contract->getAmountPaid();
                    $balance = round($adjusted - $paid, 2);
                    $coTotal = $contract->getChangeOrdersTotal();
                @endphp
                <tr>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">#{{ $contract->contract_number }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $contract->subcontractor?->company_name ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $contract->getStatusLabel() }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">
                        ${{ number_format($adjusted, 2) }}
                        @if($coTotal != 0)
                            <div style="font-size: 6.5pt; color: {{ $coTotal < 0 ? '#e74c3c' : '#888' }};">
                                {{ __('incl.') }} {{ $coTotal >= 0 ? '+' : '' }}${{ number_format($coTotal, 2) }} CO
                            </div>
                        @endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paid, 2) }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: {{ $balance > 0 ? '#e67e22' : '#333' }};">${{ number_format($balance, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">{{ __('No contracts for this job site.') }}</td>
                </tr>
            @endforelse
            @if($contracts->count() > 0)
                <tr style="background-color: #f3f4f6; font-weight: bold;">
                    <td colspan="3" style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Total') }}</td>
                    <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">${{ number_format($financials['contracts_adjusted'], 2) }}</td>
                    <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">${{ number_format($financials['contracts_paid'], 2) }}</td>
                    <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">${{ number_format($financials['contracts_unpaid'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
