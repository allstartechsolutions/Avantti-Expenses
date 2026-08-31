<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Payment Schedule') }} — {{ $contract->contract_number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 18px;">
@php
    $currency = config('app.currency');
    $locale = config('app.locale');
    $money = fn ($v) => Number::currency((float) $v, $currency, $locale);
    $scheduled = $contract->getScheduledTotal();
    $settled = round($items->sum(fn ($i) => $i->getSettledAmount()), 2);
    $unscheduled = $contract->getUnscheduledAmount();
@endphp

{{-- Header --}}
<table style="width: 100%; border: none; margin-bottom: 12px; border-bottom: 2px solid #3F5189; padding-bottom: 8px;">
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
                    @if($logoData)<img src="{{ $logoData }}" style="max-height: 40px; max-width: 150px; margin-bottom: 4px;">@endif
                @endif
                <div style="font-size: 12pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
                <div style="font-size: 7pt; color: #666;">{{ $company->full_address ?? '' }}@if($company->phone) | P: {{ $company->phone }}@endif</div>
            @endif
        </td>
        <td style="width: 45%; vertical-align: top; text-align: right; border: none; padding: 0;">
            <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('PAYMENT SCHEDULE') }}</div>
            <div style="font-size: 10pt; font-weight: bold;">{{ $contract->contract_number }}</div>
            <div style="font-size: 8pt; color: #555;">{{ $contract->subcontractor?->company_name ?? '—' }}</div>
            <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->appDateTime() }}</div>
        </td>
    </tr>
</table>

{{-- Contract identification --}}
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 8pt; margin-bottom: 10px;">
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb; width: 18%;"><strong>{{ __('Project') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; width: 32%;">{{ $contract->project?->project_name ?? '—' }}</td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb; width: 18%;"><strong>{{ __('Job Site') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; width: 32%;">{{ $contract->jobSite?->job_site_name ?? __('Project (General)') }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb;"><strong>{{ __('Contract Value') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px;">{{ $money($contract->getAdjustedAmount()) }}</td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb;"><strong>{{ __('Balance Due') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px;">{{ $money($contract->getBalanceDue()) }}</td>
    </tr>
</table>

@if($items->isEmpty())
    <div style="font-size: 8pt; color: #888; padding: 10px 0;">{{ __('No installments scheduled yet.') }}</div>
@else
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 10px;">
        <thead>
            <tr style="background-color: #3F5189; color: #fff;">
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">#</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Description') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Trigger') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Scheduled') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Settled') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Balance') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: center;">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $i + 1 }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">
                        {{ $item->description }}
                        @if($item->budgetItem)<span style="color: #888;"> ({{ $item->budgetItem->code }})</span>@endif
                        @if($item->isDelayed())
                            <div style="color: #e74c3c; font-size: 6.5pt;">{{ __('Late by :days day(s)', ['days' => $item->getDelayDays()]) }}</div>
                        @endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px;">
                        {{ $item->trigger_type === 'date' ? __('Fixed date') : __('Milestone') }}
                        @if($item->due_date)<div style="color: #666; font-size: 6.5pt;">{{ $item->due_date->appDate() }}</div>@endif
                        @if($item->isReleased())
                            <div style="color: #27ae60; font-size: 6.5pt;">{{ __('Released') }} {{ $item->released_at->appDate() }}@if($item->releasedBy) · {{ $item->releasedBy->name }}@endif</div>
                        @endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">
                        {{ $money($item->getScheduledAmount()) }}
                        @if($item->isPercentBased())<div style="color: #888; font-size: 6.5pt;">{{ number_format((float) $item->percent, 2) }}%</div>@endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #27ae60;">{{ $money($item->getSettledAmount()) }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($item->getBalance()) }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: center;">{{ $item->getStatusLabel() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td colspan="3" style="border: 1px solid #ddd; padding: 5px 6px;">{{ __('Total Scheduled') }}</td>
                <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($scheduled) }}</td>
                <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($settled) }}</td>
                <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money(round($scheduled - $settled, 2)) }}</td>
                <td style="border: 1px solid #ddd; padding: 5px 6px;"></td>
            </tr>
            @if(abs($unscheduled) >= 0.01)
                <tr>
                    <td colspan="3" style="border: 1px solid #ddd; padding: 5px 6px; color: #b45309;">{{ __('Unscheduled Balance') }}</td>
                    <td colspan="4" style="border: 1px solid #ddd; padding: 5px 6px; text-align: right; color: #b45309;">{{ $money($unscheduled) }}</td>
                </tr>
            @endif
        </tfoot>
    </table>
@endif

@if($contract->hasRetention())
    <table style="width: 100%; border: none; margin-bottom: 10px;">
        <tr>
            <td style="width: 33%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Retention') }} ({{ rtrim(rtrim(number_format((float) $contract->retention_percent, 2, '.', ''), '0'), '.') }}%) — {{ __('Held') }}</div>
                <div style="font-size: 10pt; font-weight: bold;">{{ $money($contract->getRetentionHeld()) }}</div>
            </td>
            <td style="width: 33%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Released') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #27ae60;">{{ $money($contract->getRetentionReleased()) }}</div>
            </td>
            <td style="width: 34%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
                <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('To Release') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #d97706;">{{ $money($contract->getRetentionOutstanding()) }}</div>
            </td>
        </tr>
    </table>
@endif

<div style="margin-top: 18px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 6.5pt; color: #999; text-align: center;">
    {{ $company?->name }} — {{ __('Payment Schedule') }} — {{ $contract->contract_number }} — {{ $generatedAt->appDate() }}
</div>
</body>
</html>
