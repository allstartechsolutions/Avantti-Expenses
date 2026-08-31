<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Comparison Map') }} {{ $quotation->quotation_number }}</title>
</head>
@php
    $columns = $comparison['columns'];
    $rows = $comparison['rows'];
    $summary = $comparison['summary'];
    $money = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $cellWidth = $columns->count() > 0 ? max(8, round(58 / $columns->count())) : 58;
@endphp
<body style="font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.35; color: #333; margin: 0; padding: 18px;">

    <!-- Header -->
    <table style="width: 100%; border: none; margin-bottom: 12px; border-bottom: 2px solid #3F5189; padding-bottom: 8px;">
        <tr>
            <td style="width: 60%; vertical-align: top; border: none; padding: 0;">
                @if($company)
                    <div style="font-size: 13pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
                    <div style="font-size: 7.5pt; color: #666;">{{ $company->full_address ?? '' }}</div>
                @endif
                <div style="font-size: 9pt; margin-top: 4px;"><strong>{{ $quotation->title }}</strong></div>
                <div style="font-size: 7.5pt; color: #666;">
                    {{ $quotation->getTypeLabel() }}
                    &middot; {{ $quotation->jobSite?->job_site_name ?? $quotation->project?->project_name }}
                    @if($quotation->requisition)
                        &middot; {{ $quotation->requisition->requisition_number }}
                    @endif
                </div>
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right; border: none; padding: 0;">
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('COMPARISON MAP') }}</div>
                <div style="font-size: 9pt; color: #555;">{{ $quotation->quotation_number }}</div>
                <div style="font-size: 7.5pt; color: #666;">{{ now()->appDateTime() }}</div>
            </td>
        </tr>
    </table>

    @if($summary['proposals'] === 0)
        <div style="padding: 20px; text-align: center; color: #666; font-size: 9pt;">
            {{ __('No proposal has been keyed in for this round yet.') }}
        </div>
    @else
        <!-- The map -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
            <thead>
                <tr style="background-color: #3F5189; color: #fff;">
                    <th style="padding: 5px; text-align: left; font-size: 7.5pt; border: 1px solid #3F5189; width: 26%;">{{ __('Item') }}</th>
                    <th style="padding: 5px; text-align: right; font-size: 7.5pt; border: 1px solid #3F5189; width: 8%;">{{ __('Qty') }}</th>
                    @foreach($columns as $column)
                        <th style="padding: 5px; text-align: right; font-size: 7.5pt; border: 1px solid #3F5189; width: {{ $cellWidth }}%;">
                            {{ $column['vendor_name'] }}
                            @if($column['is_awarded'])<br><span style="font-size: 6.5pt;">{{ __('Awarded') }}</span>@endif
                            @if($column['is_lowest'])<br><span style="font-size: 6.5pt;">{{ __('Lowest') }}</span>@endif
                            @if($column['expired'])<br><span style="font-size: 6.5pt;">{{ __('Expired') }}</span>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td style="padding: 4px 5px; border: 1px solid #ddd; vertical-align: top;">
                            <strong>{{ $row['item']->item_name }}</strong>
                            @if($row['item']->description)
                                <div style="color: #666; font-size: 7pt;">{{ $row['item']->description }}</div>
                            @endif
                        </td>
                        <td style="padding: 4px 5px; border: 1px solid #ddd; text-align: right; vertical-align: top;">
                            {{ rtrim(rtrim(number_format((float) $row['item']->quantity, 2, '.', ''), '0'), '.') }}
                            {{ $row['item']->unit }}
                        </td>
                        @foreach($row['cells'] as $cell)
                            <td style="padding: 4px 5px; border: 1px solid #ddd; text-align: right; vertical-align: top;
                                {{ $cell['is_best'] ? 'background-color: #e8f6ec;' : '' }}">
                                @if($cell['state'] === 'priced')
                                    <strong>{{ $money($cell['total']) }}</strong>
                                    <div style="color: #666; font-size: 7pt;">{{ $money($cell['unit_price']) }}</div>
                                    @if($cell['brand'] || $cell['spec'])
                                        <div style="color: #b45309; font-size: 6.5pt;">
                                            {{ $cell['brand'] ? __('substitute: :brand', ['brand' => $cell['brand']]) : __('substitute offered') }}
                                        </div>
                                    @endif
                                @elseif($cell['state'] === 'unavailable')
                                    <span style="color: #b91c1c; font-size: 7pt;">{{ __('Cannot supply') }}</span>
                                @else
                                    <span style="color: #999; font-size: 7pt;">{{ __('Not quoted') }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @foreach([
                    ['label' => __('Lines'), 'key' => 'subtotal'],
                    ['label' => __('Freight'), 'key' => 'freight'],
                    ['label' => __('Taxes'), 'key' => 'tax'],
                    ['label' => __('Discount'), 'key' => 'discount'],
                ] as $line)
                    <tr style="background-color: #f7f8fa;">
                        <td colspan="2" style="padding: 4px 5px; border: 1px solid #ddd; font-size: 7.5pt; color: #555;">{{ $line['label'] }}</td>
                        @foreach($columns as $column)
                            <td style="padding: 4px 5px; border: 1px solid #ddd; text-align: right;">
                                {{ $line['key'] === 'discount' ? '− ' : '' }}{{ $money($column[$line['key']]) }}
                                @if($line['key'] === 'freight' && $column['freight_type'])
                                    <span style="color: #666; font-size: 7pt;">{{ strtoupper($column['freight_type']) }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr style="background-color: #eef1f7;">
                    <td colspan="2" style="padding: 5px; border: 1px solid #ddd; font-weight: bold; color: #3F5189;">{{ __('Equalized Total') }}</td>
                    @foreach($columns as $column)
                        <td style="padding: 5px; border: 1px solid #ddd; text-align: right; font-weight: bold;
                            {{ $column['is_lowest'] ? 'background-color: #d7f0de;' : '' }}">
                            {{ $money($column['total']) }}
                            @if($column['delta_to_lowest'] > 0)
                                <div style="font-weight: normal; color: #666; font-size: 7pt;">+ {{ $money($column['delta_to_lowest']) }}</div>
                            @endif
                            @if($column['negotiated_rounds'] > 0)
                                <div style="font-weight: normal; color: #15803d; font-size: 7pt;">{{ __('was :amount', ['amount' => $money($column['opening_total'])]) }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td colspan="2" style="padding: 4px 5px; border: 1px solid #ddd; font-size: 7.5pt; color: #555;">{{ __('Terms') }}</td>
                    @foreach($columns as $column)
                        <td style="padding: 4px 5px; border: 1px solid #ddd; text-align: right; font-size: 7pt; color: #555;">
                            @if($column['lead_time_days'] !== null)
                                <div>{{ trans_choice(':count day|:count days', $column['lead_time_days'], ['count' => $column['lead_time_days']]) }}</div>
                            @endif
                            @if($column['payment_terms'])<div>{{ $column['payment_terms'] }}</div>@endif
                            @if($column['valid_until'])
                                <div>{{ $column['expired']
                                    ? __('Expired :date', ['date' => $column['valid_until']->appDate()])
                                    : __('Valid to :date', ['date' => $column['valid_until']->appDate()]) }}</div>
                            @endif
                            @if($column['unquoted'] > 0)<div>{{ __(':count not quoted', ['count' => $column['unquoted']]) }}</div>@endif
                            @if($column['unavailable'] > 0)<div>{{ __(':count not supplied', ['count' => $column['unavailable']]) }}</div>@endif
                            @if($column['substitutes'] > 0)<div>{{ __(':count substituted', ['count' => $column['substitutes']]) }}</div>@endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>

        <!-- What the round is worth -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <tr>
                <td style="width: 25%; padding: 6px; border: 1px solid #ddd; vertical-align: top;">
                    <div style="font-size: 7pt; color: #666; text-transform: uppercase;">{{ __('Lowest Equalized Offer') }}</div>
                    <div style="font-size: 11pt; font-weight: bold; color: #3F5189;">{{ $money($summary['lowest']) }}</div>
                    <div style="font-size: 7.5pt; color: #666;">{{ $summary['lowest_vendor'] ?? '—' }}</div>
                </td>
                <td style="width: 25%; padding: 6px; border: 1px solid #ddd; vertical-align: top;">
                    <div style="font-size: 7pt; color: #666; text-transform: uppercase;">{{ __('Saving vs the Highest') }}</div>
                    <div style="font-size: 11pt; font-weight: bold; color: #15803d;">{{ $money($summary['saving_vs_highest']) }}</div>
                    <div style="font-size: 7.5pt; color: #666;">
                        {{ $summary['comparable'] > 1
                            ? __('Highest comparable offer :amount', ['amount' => $money($summary['highest'])])
                            : __('Only one comparable offer — nothing to measure a saving against.') }}
                    </div>
                </td>
                <td style="width: 25%; padding: 6px; border: 1px solid #ddd; vertical-align: top;">
                    <div style="font-size: 7pt; color: #666; text-transform: uppercase;">{{ __('If Split Line by Line') }}</div>
                    <div style="font-size: 11pt; font-weight: bold;">{{ $money($summary['split_total']) }}</div>
                    @if(($summary['split_vendors'] ?? 0) > 1)
                        <div style="font-size: 7.5pt; color: #64748b;">{{ __('across :count vendors, each charging its own freight', ['count' => $summary['split_vendors']]) }}</div>
                    @endif
                    <div style="font-size: 7.5pt; color: #666;">
                        {{ $summary['split_saving'] > 0
                            ? __(':amount below the single winner', ['amount' => $money($summary['split_saving'])])
                            : __('No better than awarding one vendor') }}
                    </div>
                </td>
                <td style="width: 25%; padding: 6px; border: 1px solid #ddd; vertical-align: top;">
                    <div style="font-size: 7pt; color: #666; text-transform: uppercase;">{{ __('Against the Budget') }}</div>
                    @if($summary['budget_amount'] !== null)
                        <div style="font-size: 11pt; font-weight: bold; color: {{ $summary['budget_delta'] > 0 ? '#b91c1c' : '#15803d' }};">
                            {{ $summary['budget_delta'] > 0 ? '+' : '' }}{{ $money($summary['budget_delta']) }}
                        </div>
                        <div style="font-size: 7.5pt; color: #666;">{{ __('Budgeted :amount', ['amount' => $money($summary['budget_amount'])]) }}</div>
                    @else
                        <div style="font-size: 8pt; color: #666;">{{ __('No budget item linked to this round.') }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <div style="font-size: 7.5pt; color: #666; border-top: 1px solid #ddd; padding-top: 6px;">
            {{ __('Totals are equalized: lines plus freight and taxes, less discount. The benchmark is the cheapest proposal that covers the whole scope and has not expired.') }}
            @if($summary['negotiated_saving'] > 0)
                {{ __('Negotiation has taken :amount off the offers on this map.', ['amount' => $money($summary['negotiated_saving'])]) }}
            @endif
            @unless($summary['meets_norm'])
                {{ $summary['meets_minimum'] ? __('Two proposals — the Brazilian norm is three.') : __('Fewer than two proposals — an award will be blocked.') }}
            @endunless
            @if($comparison['awaiting']->count() > 0)
                {{ __('Still awaiting: :vendors.', ['vendors' => $comparison['awaiting']->map(fn ($r) => $r->vendor?->name)->filter()->implode(', ')]) }}
            @endif
            @if($comparison['declined']->count() > 0)
                {{ __('Declined: :vendors.', ['vendors' => $comparison['declined']->map(fn ($r) => $r->vendor?->name)->filter()->implode(', ')]) }}
            @endif
        </div>
    @endif

</body>
</html>
