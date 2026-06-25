<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Accounts Payable Report') }}</title>
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
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('ACCOUNTS PAYABLE') }}</div>
                <div style="font-size: 8pt; color: #555;">
                    {{ \Carbon\Carbon::parse($fromDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('M d, Y') }}
                </div>
                <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->format('M d, Y - h:i A') }}</div>
                @if($project)
                    <div style="font-size: 7pt; color: #888;">{{ __('Project') }}: {{ $project->project_name }}</div>
                @endif
                @if($client)
                    <div style="font-size: 7pt; color: #888;">{{ __('Client') }}: {{ $client->company_name }}</div>
                @endif
                <div style="font-size: 7pt; color: #888;">{{ __('Status filter') }}: {{ ucfirst($statusFilter) }}</div>
            </td>
        </tr>
    </table>

    {{-- KPIs --}}
    <table style="width: 100%; border: none; margin-bottom: 15px;">
        <tr>
            <td style="width: 33%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Due in Period') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #333;">${{ number_format($kpis['total_due'], 2) }}</div>
                <div style="font-size: 6.5pt; color: #888;">{{ $kpis['count_due'] }} {{ Str::plural('payment', $kpis['count_due']) }}</div>
            </td>
            <td style="width: 33%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Overdue (today)') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #e74c3c;">${{ number_format($kpis['overdue_total'], 2) }}</div>
                <div style="font-size: 6.5pt; color: #888;">{{ __('Past due, regardless of filter') }}</div>
            </td>
            <td style="width: 34%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Paid in Period') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #27ae60;">${{ number_format($kpis['total_paid'], 2) }}</div>
                <div style="font-size: 6.5pt; color: #888;">{{ __('Cleared in selected dates') }}</div>
            </td>
        </tr>
    </table>

    {{-- Selected period detail --}}
    <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('Selected Period') }}</div>
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Due Date') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Vendor') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Item') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Project') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Job Site') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Status') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $row['due_date']?->format('M d, Y') }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $row['vendor'] ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $row['item'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $row['project'] ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $row['job_site'] ?? __('Project-level') }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ ucfirst($row['status']) }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">
                        {{ __('No expense payments due in the selected period.') }}
                    </td>
                </tr>
            @endforelse
            @if($rows->isNotEmpty())
                <tr style="background-color: #f3f4f6; font-weight: bold;">
                    <td colspan="6" style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Total') }}</td>
                    <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">${{ number_format($rows->sum('amount'), 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Projections --}}
    <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('Monthly Projections (next 12 months)') }}</div>
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Month') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Payments') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projections as $m)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $m['month'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $m['count'] > 0 ? $m['count'] : '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $m['amount'] > 0 ? '$' . number_format($m['amount'], 2) : '—' }}</td>
                </tr>
            @endforeach
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td style="border: 1px solid #ddd; padding: 5px 6px;">{{ __('Total') }}</td>
                <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ collect($projections)->sum('count') }}</td>
                <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">${{ number_format(collect($projections)->sum('amount'), 2) }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
