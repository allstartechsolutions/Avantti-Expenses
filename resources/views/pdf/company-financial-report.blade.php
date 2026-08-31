<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Company Financials') }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 15px;">
@php
    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $sourceLabels = [
        'income' => __('Income'),
        'invoice' => __('Invoices'),
        'expense' => __('Expenses'),
        'contract' => __('Contracts'),
    ];
    $statusLabels = [
        'settled' => __('Settled'),
        'open' => __('Open'),
        'overdue' => __('Overdue'),
    ];
@endphp

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
            <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('COMPANY FINANCIALS') }}</div>
            <div style="font-size: 8pt; color: #555;">
                @if(!$client && !$project && !$jobSite)
                    {{ __('All clients, projects, and job sites') }}
                @endif
            </div>
            @if($fromDate || $toDate)
                <div style="font-size: 8pt; color: #555;">
                    {{ __('Period') }}: {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->appDate() : __('beginning') }} — {{ $toDate ? \Carbon\Carbon::parse($toDate)->appDate() : __('open-ended') }}
                </div>
            @endif
            <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->appDateTime() }}</div>
            @if($client)<div style="font-size: 7pt; color: #888;">{{ __('Client') }}: {{ $client->company_name }}</div>@endif
            @if($project)<div style="font-size: 7pt; color: #888;">{{ __('Project') }}: {{ $project->project_name }}</div>@endif
            @if($jobSite)<div style="font-size: 7pt; color: #888;">{{ __('Job Site') }}: {{ $jobSite->job_site_name }}</div>@endif
        </td>
    </tr>
</table>

{{-- Position --}}
<table style="width: 100%; border: none; margin-bottom: 10px;">
    <tr>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Received') }}</div>
            <div style="font-size: 11pt; font-weight: bold; color: #27ae60;">{{ $money($data['in']['settled']) }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Paid') }}</div>
            <div style="font-size: 11pt; font-weight: bold; color: #333;">{{ $money($data['out']['settled']) }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('To Receive') }}</div>
            <div style="font-size: 11pt; font-weight: bold; color: #2563eb;">{{ $money($data['net']['to_receive']) }}</div>
            <div style="font-size: 6.5pt; color: #888;">{{ __('Overdue') }}: {{ $money($data['in']['overdue']) }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('To Pay') }}</div>
            <div style="font-size: 11pt; font-weight: bold; color: #d97706;">{{ $money($data['net']['to_pay']) }}</div>
            <div style="font-size: 6.5pt; color: #e74c3c;">{{ __('Overdue') }}: {{ $money($data['out']['overdue']) }}</div>
        </td>
    </tr>
</table>

<table style="width: 100%; border: none; margin-bottom: 10px;">
    <tr>
        <td style="width: 50%; border: 1px solid #ddd; padding: 6px; text-align: center;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Net Cash (settled)') }}</div>
            <div style="font-size: 13pt; font-weight: bold; color: {{ $data['net']['cash'] < 0 ? '#e74c3c' : '#27ae60' }};">{{ $money($data['net']['cash']) }}</div>
        </td>
        <td style="width: 50%; border: 1px solid #ddd; padding: 6px; text-align: center;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Net Forecast (with open items)') }}</div>
            <div style="font-size: 13pt; font-weight: bold; color: {{ $data['net']['forecast'] < 0 ? '#e74c3c' : '#27ae60' }};">{{ $money($data['net']['forecast']) }}</div>
        </td>
    </tr>
</table>

{{-- By source --}}
<div style="font-size: 9pt; font-weight: bold; color: #3F5189; margin: 8px 0 4px 0;">{{ __('By Source') }}</div>
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 10px;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: left;">{{ __('Source') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: left;">{{ __('Direction') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('Settled') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('Open') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('Overdue') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['sources'] as $source)
            <tr>
                <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $source['label'] }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $source['direction'] === 'in' ? __('In') : __('Out') }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($source['settled']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($source['open']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; {{ $source['overdue'] > 0 ? 'color: #e74c3c;' : '' }}">{{ $money($source['overdue']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; font-weight: bold;">{{ $money($source['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Month by month --}}
<div style="font-size: 9pt; font-weight: bold; color: #3F5189; margin: 8px 0 4px 0;">{{ __('Month by Month') }}</div>
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 10px;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: left;">{{ __('Month') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('In') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('Out') }}</th>
            <th style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ __('Net') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['timeline']['months'] as $month)
            <tr>
                <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $month['label'] }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #27ae60;">{{ $money($month['in']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($month['out']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; font-weight: bold; color: {{ $month['net'] < 0 ? '#e74c3c' : '#27ae60' }};">{{ $money($month['net']) }}</td>
            </tr>
        @endforeach
        @if($data['timeline']['undated']['in'] > 0 || $data['timeline']['undated']['out'] > 0)
            <tr style="background-color: #f9fafb;">
                <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ __('No due date') }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #27ae60;">{{ $money($data['timeline']['undated']['in']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($data['timeline']['undated']['out']) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; font-weight: bold; color: {{ $data['timeline']['undated']['net'] < 0 ? '#e74c3c' : '#27ae60' }};">{{ $money($data['timeline']['undated']['net']) }}</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- Detail --}}
<div style="font-size: 9pt; font-weight: bold; color: #3F5189; margin: 8px 0 4px 0;">{{ __('Detail') }}</div>
@if($rows->isEmpty())
    <div style="font-size: 7.5pt; color: #888;">{{ __('Nothing matches the selected filters.') }}</div>
@else
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7pt;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Date') }}</th>
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Source') }}</th>
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Description') }}</th>
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Party') }}</th>
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: left;">{{ __('Project') }}</th>
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: center;">{{ __('Status') }}</th>
                <th style="border: 1px solid #ddd; padding: 4px 5px; text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['date']?->appDate() ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $sourceLabels[$row['source']] ?? $row['source'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['description'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['party'] ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 3px 5px;">{{ $row['project'] ?? '—' }}</td>
                    <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: center; {{ $row['status'] === 'overdue' ? 'color: #e74c3c;' : '' }}">{{ $statusLabels[$row['status']] ?? $row['status'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 3px 5px; text-align: right; {{ $row['direction'] === 'in' ? 'color: #27ae60;' : '' }}">
                        {{ $row['direction'] === 'in' ? '+' : '-' }}{{ $money($row['amount']) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Footer --}}
<div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 6.5pt; color: #999; text-align: center;">
    {{ $company?->name }} — {{ __('Company Financials') }} — {{ $generatedAt->appDate() }}
</div>
</body>
</html>
