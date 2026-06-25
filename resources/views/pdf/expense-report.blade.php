@php
    $currency = config('app.currency');
    $locale = config('app.locale');

    $money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, $currency, $locale);

    $viewLabels = [
        'project' => __('By Project / Job Site'),
        'vendor' => __('By Vendor'),
        'costcode' => __('By Cost Code'),
        'detail' => __('Detail'),
    ];

    $categoryLabels = ['product' => __('Product'), 'service' => __('Service'), 'rental' => __('Rental')];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Expense Report') }}</title>
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
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('EXPENSE REPORT') }}</div>
                <div style="font-size: 8pt; color: #555;">{{ $viewLabels[$view] }}</div>
                <div style="font-size: 8pt; color: #555;">
                    {{ \Carbon\Carbon::parse($fromDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('M d, Y') }}
                </div>
                <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->format('M d, Y - h:i A') }}</div>
                @if($client)
                    <div style="font-size: 7pt; color: #888;">{{ __('Client') }}: {{ $client->company_name }}</div>
                @endif
                @if($project)
                    <div style="font-size: 7pt; color: #888;">{{ __('Project') }}: {{ $project->project_name }}</div>
                @endif
                @if($jobSite)
                    <div style="font-size: 7pt; color: #888;">{{ __('Job Site') }}: {{ $jobSite->job_site_name }}</div>
                @endif
                @if($vendor)
                    <div style="font-size: 7pt; color: #888;">{{ __('Vendor') }}: {{ $vendor->name }}</div>
                @endif
                @if($categoryFilter)
                    <div style="font-size: 7pt; color: #888;">{{ __('Category') }}: {{ $categoryLabels[$categoryFilter] ?? ucfirst($categoryFilter) }}</div>
                @endif
                <div style="font-size: 7pt; color: #888;">{{ __('Status filter') }}: {{ ucfirst($statusFilter) }}</div>
            </td>
        </tr>
    </table>

    {{-- KPIs --}}
    <table style="width: 100%; border: none; margin-bottom: 15px;">
        <tr>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Total Expenses') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #333;">{{ $money($kpis['total']) }}</div>
                <div style="font-size: 6.5pt; color: #888;">{{ $kpis['count'] }} {{ Str::plural('expense', $kpis['count']) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Paid') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #27ae60;">{{ $money($kpis['paid']) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Outstanding') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #d68910;">{{ $money($kpis['outstanding']) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Overdue (today)') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #e74c3c;">{{ $money($kpis['overdue']) }}</div>
            </td>
        </tr>
    </table>

    {{-- By Project / Job Site --}}
    @if($view === 'project')
        <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('By Project / Job Site') }}</div>
        <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Project / Job Site') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Total') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Paid') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Outstanding') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Overdue') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byProject as $proj)
                    <tr style="background-color: #eef1f8; font-weight: bold;">
                        <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $proj['project'] ?? '—' }} ({{ $proj['count'] }})</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($proj['total']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #27ae60;">{{ $money($proj['paid']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #d68910;">{{ $money($proj['outstanding']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: {{ $proj['overdue'] > 0 ? '#e74c3c' : '#999' }};">{{ $money($proj['overdue']) }}</td>
                    </tr>
                    @foreach($proj['jobsites'] as $js)
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 4px 6px 4px 18px;">{{ $js['job_site'] ?? __('Project-level') }}</td>
                            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($js['total']) }}</td>
                            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #27ae60;">{{ $money($js['paid']) }}</td>
                            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #d68910;">{{ $money($js['outstanding']) }}</td>
                            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: {{ $js['overdue'] > 0 ? '#e74c3c' : '#999' }};">{{ $money($js['overdue']) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">{{ __('No expenses match the selected filters.') }}</td></tr>
                @endforelse
                @if($byProject->isNotEmpty())
                    <tr style="background-color: #f3f4f6; font-weight: bold;">
                        <td style="border: 1px solid #ddd; padding: 5px 6px;">{{ __('Total') }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['total']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['paid']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['outstanding']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['overdue']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    {{-- By Vendor --}}
    @if($view === 'vendor')
        <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('By Vendor') }}</div>
        <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Vendor') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Expenses') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Total') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Paid') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Outstanding') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Overdue') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byVendor as $v)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $v['vendor'] ?? __('No vendor') }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $v['count'] }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($v['total']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #27ae60;">{{ $money($v['paid']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #d68910;">{{ $money($v['outstanding']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: {{ $v['overdue'] > 0 ? '#e74c3c' : '#999' }};">{{ $money($v['overdue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">{{ __('No expenses match the selected filters.') }}</td></tr>
                @endforelse
                @if($byVendor->isNotEmpty())
                    <tr style="background-color: #f3f4f6; font-weight: bold;">
                        <td style="border: 1px solid #ddd; padding: 5px 6px;">{{ __('Total') }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $kpis['count'] }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['total']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['paid']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['outstanding']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($kpis['overdue']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    {{-- By Cost Code --}}
    @if($view === 'costcode')
        <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 2px 0;">{{ __('By Cost Code') }}</div>
        <div style="font-size: 7pt; color: #888; margin-bottom: 4px;">{{ __('Committed cost per cost code (line-item level). Total cost only.') }}</div>
        <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Cost Code') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Line Items') }}</th>
                    <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Total Cost') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byCostCode as $cc)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $cc['code'] }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $cc['count'] }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($cc['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">{{ __('No expenses match the selected filters.') }}</td></tr>
                @endforelse
                @if($byCostCode->isNotEmpty())
                    <tr style="background-color: #f3f4f6; font-weight: bold;">
                        <td style="border: 1px solid #ddd; padding: 5px 6px;">{{ __('Total') }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $byCostCode->sum('count') }}</td>
                        <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($byCostCode->sum('total')) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    {{-- Detail --}}
    @if($view === 'detail')
        <div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('Detail') }}</div>
        <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7pt;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Date') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Item') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Vendor') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Project') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Job Site') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: center;">{{ __('Inst.') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ __('Total') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ __('Paid') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ __('Outstanding') }}</th>
                    <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ __('Overdue') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($detail as $row)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['expense_date']?->format('M d, Y') }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['item'] }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['vendor'] ?? '—' }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['project'] ?? '—' }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['job_site'] ?? __('Project-level') }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: center;">{{ $row['payment_label'] }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: right;">{{ $money($row['total']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: right; color: #27ae60;">{{ $money($row['paid']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: right; color: #d68910;">{{ $money($row['outstanding']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: right; color: {{ $row['overdue'] > 0 ? '#e74c3c' : '#999' }};">{{ $money($row['overdue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="border: 1px solid #ddd; padding: 6px; text-align: center; color: #999; font-style: italic;">{{ __('No expenses match the selected filters.') }}</td></tr>
                @endforelse
                @if($detail->isNotEmpty())
                    <tr style="background-color: #f3f4f6; font-weight: bold;">
                        <td colspan="6" style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ __('Total') }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ $money($kpis['total']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ $money($kpis['paid']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ $money($kpis['outstanding']) }}</td>
                        <td style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ $money($kpis['overdue']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

</body>
</html>
