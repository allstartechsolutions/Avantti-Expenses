<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Measurement') }} #{{ $measurement->measurement_number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 18px;">
@php
    $currency = config('app.currency');
    $locale = config('app.locale');
    $money = fn ($v) => Number::currency((float) $v, $currency, $locale);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . '%';
    $remaining = $measurement->getRemainingNet();
    $paid = $measurement->getAmountPaid();
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
        <td style="width: 45%; vertical-align: top; text-align: right; border: none; padding: 0;">
            <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('MEASUREMENT REPORT') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #333;">
                {{ __('No.') }} {{ str_pad($measurement->measurement_number, 2, '0', STR_PAD_LEFT) }}
            </div>
            <div style="font-size: 8pt; color: #555;">
                {{ __('Period') }}: {{ $measurement->period_start->format('d/m/Y') }} — {{ $measurement->period_end->format('d/m/Y') }}
            </div>
            <div style="font-size: 8pt; color: {{ $measurement->isApproved() ? '#27ae60' : ($measurement->isCancelled() ? '#e74c3c' : '#d97706') }}; font-weight: bold;">
                {{ $measurement->getStatusLabel() }}
            </div>
            <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->format('d/m/Y - H:i') }}</div>
        </td>
    </tr>
</table>

{{-- Contract identification --}}
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 8pt; margin-bottom: 10px;">
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb; width: 18%;"><strong>{{ __('Contract') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; width: 32%;">{{ $contract->contract_number }}</td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb; width: 18%;"><strong>{{ __('Subcontractor') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; width: 32%;">{{ $contract->subcontractor?->company_name ?? '—' }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb;"><strong>{{ __('Project') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px;">{{ $contract->project?->project_name ?? '—' }}</td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb;"><strong>{{ __('Job Site') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px;">{{ $contract->jobSite?->job_site_name ?? __('Project (General)') }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb;"><strong>{{ __('Contract Value') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px;">{{ $money($contract->getAdjustedAmount()) }}</td>
        <td style="border: 1px solid #ddd; padding: 5px 7px; background-color: #f9fafb;"><strong>{{ __('Installment') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px 7px;">{{ $measurement->scheduleItem?->description ?? '—' }}</td>
    </tr>
</table>

{{-- The boletim --}}
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 10px;">
    <thead>
        <tr style="background-color: #3F5189; color: #fff;">
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Cost Code') }}</th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Scheduled') }}</th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Previous %') }}</th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Current %') }}</th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Period Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($measurement->items as $item)
            <tr>
                <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ $item->cost_code_display }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($item->scheduled_amount) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; color: #777;">{{ $pct($item->previous_percent) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; font-weight: bold;">{{ $pct($item->current_percent) }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $money($item->period_amount) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background-color: #f3f4f6; font-weight: bold;">
            <td colspan="4" style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Gross') }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ $money($measurement->gross_amount) }}</td>
        </tr>
        <tr>
            <td colspan="4" style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">
                {{ __('Retention') }}
                @if($measurement->gross_amount > 0)
                    ({{ $pct(round($measurement->retention_amount / $measurement->gross_amount * 100, 2)) }})
                @endif
            </td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; text-align: right; color: #d97706;">− {{ $money($measurement->retention_amount) }}</td>
        </tr>
        <tr style="background-color: #eef2ff; font-weight: bold;">
            <td colspan="4" style="border: 1px solid #ddd; padding: 6px; text-align: right; font-size: 9pt;">{{ __('Net') }}</td>
            <td style="border: 1px solid #ddd; padding: 6px; text-align: right; font-size: 9pt; color: #3F5189;">{{ $money($measurement->net_amount) }}</td>
        </tr>
    </tfoot>
</table>

{{-- Payment status --}}
<table style="width: 100%; border: none; margin-bottom: 10px;">
    <tr>
        <td style="width: 33%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Net') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #333;">{{ $money($measurement->net_amount) }}</div>
        </td>
        <td style="width: 33%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Paid') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #27ae60;">{{ $money($paid) }}</div>
        </td>
        <td style="width: 34%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Balance') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: {{ $remaining > 0 ? '#d97706' : '#27ae60' }};">{{ $money($remaining) }}</div>
        </td>
    </tr>
</table>

@if($measurement->notes)
    <div style="border: 1px solid #ddd; padding: 6px 8px; font-size: 7.5pt; margin-bottom: 10px;">
        <strong>{{ __('Notes') }}:</strong> {{ $measurement->notes }}
    </div>
@endif

{{-- Trail --}}
<div style="font-size: 7pt; color: #777; margin-bottom: 22px;">
    {{ __('Created by') }}: {{ $measurement->createdBy?->name ?? '—' }} · {{ $measurement->created_at?->format('d/m/Y') }}
    @if($measurement->approvedBy)
        &nbsp;|&nbsp; {{ __('Approved') }}: {{ $measurement->approvedBy->name }} · {{ $measurement->approved_at?->format('d/m/Y H:i') }}
    @endif
</div>

{{-- Signatures — the reason this document is printed --}}
<table style="width: 100%; border: none; margin-top: 30px;">
    <tr>
        <td style="width: 45%; border: none; border-top: 1px solid #333; padding-top: 4px; text-align: center; font-size: 7.5pt;">
            {{ $company?->name ?? __('Contractor') }}<br>
            <span style="color: #888;">{{ __('Date') }}: ____/____/________</span>
        </td>
        <td style="width: 10%; border: none;"></td>
        <td style="width: 45%; border: none; border-top: 1px solid #333; padding-top: 4px; text-align: center; font-size: 7.5pt;">
            {{ $contract->subcontractor?->company_name ?? __('Subcontractor') }}<br>
            <span style="color: #888;">{{ __('Date') }}: ____/____/________</span>
        </td>
    </tr>
</table>

<div style="margin-top: 18px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 6.5pt; color: #999; text-align: center;">
    {{ $company?->name }} — {{ __('Measurement') }} #{{ $measurement->measurement_number }} — {{ $contract->contract_number }} — {{ $generatedAt->format('d/m/Y') }}
</div>
</body>
</html>
