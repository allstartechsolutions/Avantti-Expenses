<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Cost Code Grid') }} — {{ $budget->location_name }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.4; color: #333; margin: 0; padding: 14px;">
@php
    $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $th = 'border: 1px solid #ddd; padding: 4px 5px; background: #f4f5f7; font-size: 6.5pt; text-transform: uppercase; color: #666;';
    $td = 'border: 1px solid #eee; padding: 3px 5px; font-size: 7.5pt;';
    $totals = $grid['totals'];

    $logoData = '';
    if ($company?->logo) {
        $logoPath = storage_path('app/public/' . $company->logo);
        if (file_exists($logoPath)) {
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = match($ext) { 'png' => 'image/png', 'svg' => 'image/svg+xml', 'gif' => 'image/gif', default => 'image/jpeg' };
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        }
    }
@endphp

{{-- Header --}}
<table style="width: 100%; border: none; margin-bottom: 10px; border-bottom: 2px solid #3F5189; padding-bottom: 6px;">
    <tr>
        <td style="width: 55%; vertical-align: top; border: none; padding: 0;">
            @if($logoData)
                <img src="{{ $logoData }}" style="max-height: 34px; max-width: 140px; margin-bottom: 3px;">
            @endif
            @if($company)
                <div style="font-size: 11pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
            @endif
            <div style="font-size: 7pt; color: #666;">{{ $budget->project->project_name }} · {{ $budget->location_name }}</div>
        </td>
        <td style="width: 45%; vertical-align: top; border: none; padding: 0; text-align: right;">
            <div style="font-size: 13pt; font-weight: bold; color: #3F5189;">{{ __('Cost Code Grid') }}</div>
            <div style="font-size: 7pt; color: #666;">{{ $budget->name }}</div>
            <div style="font-size: 7pt; color: #888;">{{ __('Generated') }}: {{ $generatedAt->translatedFormat('d M Y H:i') }}</div>
        </td>
    </tr>
</table>

{{-- Headline figures --}}
<table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
    <tr>
        @foreach([
            [__('Original Budget'), $fmt($totals['original']), '#333'],
            [__('Approved Changes'), (float) $totals['changes'] == 0.0 ? $fmt(0) : $signed($totals['changes']), (float) $totals['changes'] < 0 ? '#c0392b' : '#2c6fb7'],
            [__('Revised Budget'), $fmt($totals['revised']), '#333'],
            [__('Committed'), $fmt($totals['committed']), '#8e44ad'],
            [__('Actual'), $fmt($totals['actual']), '#27ae60'],
            [__('Remaining'), $fmt($totals['remaining']), (float) $totals['remaining'] < 0 ? '#c0392b' : '#333'],
        ] as [$label, $value, $color])
            <td style="width: 16.6%; border: 1px solid #ddd; padding: 6px; text-align: center; background: #f9fafb;">
                <div style="font-size: 6.5pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ $label }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: {{ $color }};">{{ $value }}</div>
            </td>
        @endforeach
    </tr>
</table>

@if($totals['over_budget'])
    <div style="border: 1px solid #f5c6cb; background: #fdf3f4; color: #a02c39; padding: 5px 8px; font-size: 7.5pt; margin-bottom: 8px;">
        {{ __('Committed and actual costs exceed the revised budget by :amount.', ['amount' => $fmt(abs($totals['remaining']))]) }}
    </div>
@endif

{{-- The grid --}}
<table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="{{ $th }} text-align: left;">{{ __('Cost Code') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Original') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Changes') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Revised') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Committed') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Actual') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Projected') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Remaining') }}</th>
            <th style="{{ $th }} text-align: right;">{{ __('Used') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($grid['sections'] as $section)
            @php $parentRow = $section['rows'][0]; @endphp
            <tr>
                <td style="{{ $td }} background: #eef1f8; font-weight: bold;">{{ $parentRow['code'] }} — {{ $parentRow['name'] }}</td>
                @foreach(['original', 'changes', 'revised', 'committed', 'actual', 'projected', 'remaining'] as $key)
                    <td style="{{ $td }} background: #eef1f8; text-align: right; font-weight: bold;">
                        {{ $key === 'changes' && (float) $parentRow['changes'] == 0.0 ? '—' : ($key === 'changes' ? $signed($parentRow[$key]) : $fmt($parentRow[$key])) }}
                    </td>
                @endforeach
                <td style="{{ $td }} background: #eef1f8; text-align: right; font-weight: bold;">
                    {{ $parentRow['percent_committed'] === null ? '—' : number_format($parentRow['percent_committed'], 0) . '%' }}
                </td>
            </tr>

            @foreach(array_slice($section['rows'], 1) as $row)
                <tr>
                    <td style="{{ $td }} padding-left: 16px;">{{ $row['code'] }} — {{ $row['name'] }}</td>
                    @foreach(['original', 'changes', 'revised', 'committed', 'actual', 'projected', 'remaining'] as $key)
                        <td style="{{ $td }} text-align: right; {{ $key === 'remaining' && (float) $row['remaining'] < 0 ? 'color: #c0392b; font-weight: bold;' : '' }}">
                            {{ $key === 'changes' && (float) $row['changes'] == 0.0 ? '—' : ($key === 'changes' ? $signed($row[$key]) : $fmt($row[$key])) }}
                        </td>
                    @endforeach
                    <td style="{{ $td }} text-align: right; {{ $row['over_budget'] ? 'color: #c0392b; font-weight: bold;' : '' }}">
                        {{ $row['percent_committed'] === null ? '—' : number_format($row['percent_committed'], 0) . '%' }}
                    </td>
                </tr>
            @endforeach

            <tr>
                <td style="{{ $td }} background: #f9f9f9; font-weight: bold;">
                    {{ __('Sub-Total') }}
                    @if($section['pct_of_budget'] !== null)
                        <span style="color: #888; font-weight: normal;">({{ number_format($section['pct_of_budget'], 1) }}% {{ __('of budget') }})</span>
                    @endif
                </td>
                @foreach(['original', 'changes', 'revised', 'committed', 'actual', 'projected', 'remaining'] as $key)
                    <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">
                        {{ $key === 'changes' && (float) $section['subtotal']['changes'] == 0.0 ? '—' : ($key === 'changes' ? $signed($section['subtotal'][$key]) : $fmt($section['subtotal'][$key])) }}
                    </td>
                @endforeach
                <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">
                    {{ $section['subtotal']['percent_committed'] === null ? '—' : number_format($section['subtotal']['percent_committed'], 0) . '%' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="{{ $td }} text-align: center; color: #999; font-style: italic;">{{ __('This budget has no cost codes yet.') }}</td>
            </tr>
        @endforelse

        @if($grid['unassigned'])
            <tr>
                <td style="{{ $td }} font-style: italic;">{{ $grid['unassigned']['name'] }}</td>
                @foreach(['original', 'changes', 'revised', 'committed', 'actual', 'projected', 'remaining'] as $key)
                    <td style="{{ $td }} text-align: right;">
                        {{ $key === 'changes' && (float) $grid['unassigned']['changes'] == 0.0 ? '—' : ($key === 'changes' ? $signed($grid['unassigned'][$key]) : $fmt($grid['unassigned'][$key])) }}
                    </td>
                @endforeach
                <td style="{{ $td }} text-align: right;">
                    {{ $grid['unassigned']['percent_committed'] === null ? '—' : number_format($grid['unassigned']['percent_committed'], 0) . '%' }}
                </td>
            </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td style="{{ $td }} background: #3F5189; color: #fff; font-weight: bold;">{{ __('Total') }}</td>
            @foreach(['original', 'changes', 'revised', 'committed', 'actual', 'projected', 'remaining'] as $key)
                <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">
                    {{ $key === 'changes' && (float) $totals['changes'] == 0.0 ? '—' : ($key === 'changes' ? $signed($totals[$key]) : $fmt($totals[$key])) }}
                </td>
            @endforeach
            <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">
                {{ $totals['percent_committed'] === null ? '—' : number_format($totals['percent_committed'], 0) . '%' }}
            </td>
        </tr>
    </tfoot>
</table>

<div style="margin-top: 8px; font-size: 6.5pt; color: #888; line-height: 1.5;">
    @if(($grid['hidden_count'] ?? 0) > 0)
        <div>{{ trans_choice('{1} :count cost code with no budget and no activity is not listed. The totals include it.|[2,*] :count cost codes with no budget and no activity are not listed. The totals include them.', $grid['hidden_count'], ['count' => $grid['hidden_count']]) }}</div>
    @endif
    <div><strong>{{ __('Changes') }}</strong> — {{ __('approved change orders only. A change order still in draft, pending or rejected does not move the budget.') }}</div>
    <div><strong>{{ __('Committed') }}</strong> — {{ __('subcontracts and their change orders, plus purchase orders awaiting approval. An approved purchase order has already become an expense, so it is counted as actual instead.') }}</div>
    <div><strong>{{ __('Projected') }}</strong> — {{ __('committed plus expenses. Contract payments are left out of this sum because they are already inside the contract value.') }}</div>
</div>

</body>
</html>
