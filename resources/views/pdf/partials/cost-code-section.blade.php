{{--
    Budget versus actual by cost code, print version.
    Expects: $costCodes, $showLocation
--}}
@php
    $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $th = 'border: 1px solid #ddd; padding: 4px 6px; background: #f4f5f7; font-size: 7pt; text-transform: uppercase; color: #666;';
    $td = 'border: 1px solid #eee; padding: 4px 6px; font-size: 8pt;';
@endphp

<div style="margin-bottom: 14px;">
    <div style="font-size: 10pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #3F5189; padding-bottom: 3px; margin-bottom: 6px;">
        {{ __('Budget by Cost Code') }}
    </div>
    <div style="font-size: 7pt; color: #666; margin-bottom: 6px;">
        {{ __('Lifetime figures: the whole budget against everything committed and spent, whatever the report dates say elsewhere.') }}
    </div>

    @if(empty($costCodes['budgets']))
        <div style="font-size: 8pt; color: #666;">{{ __('No budget yet') }}</div>
    @else
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="{{ $th }} text-align: left;">{{ __('Cost Code') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Original') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Changes') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Revised') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Committed') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Actual') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($costCodes['budgets'] as $entry)
                    @php $grid = $entry['grid']; @endphp

                    @if($showLocation)
                        <tr>
                            <td colspan="7" style="{{ $td }} background: #eef1f8; font-weight: bold;">{{ $entry['budget']->location_name }}</td>
                        </tr>
                    @endif

                    @foreach($grid['sections'] as $section)
                        @foreach($section['rows'] as $row)
                            @continue((float) $row['revised'] == 0.0 && (float) $row['projected'] == 0.0)
                            <tr>
                                <td style="{{ $td }} {{ $row['is_parent'] ? 'font-weight: bold;' : 'padding-left: 16px;' }}">{{ $row['code'] }} — {{ $row['name'] }}</td>
                                <td style="{{ $td }} text-align: right;">{{ $fmt($row['original']) }}</td>
                                <td style="{{ $td }} text-align: right; {{ (float) $row['changes'] < 0 ? 'color: #c0392b;' : '' }}">{{ (float) $row['changes'] == 0.0 ? '—' : $signed($row['changes']) }}</td>
                                <td style="{{ $td }} text-align: right; font-weight: bold;">{{ $fmt($row['revised']) }}</td>
                                <td style="{{ $td }} text-align: right;">{{ $fmt($row['committed']) }}</td>
                                <td style="{{ $td }} text-align: right;">{{ $fmt($row['actual']) }}</td>
                                <td style="{{ $td }} text-align: right; {{ (float) $row['remaining'] < 0 ? 'color: #c0392b; font-weight: bold;' : '' }}">{{ $fmt($row['remaining']) }}</td>
                            </tr>
                        @endforeach
                    @endforeach

                    @if($grid['unassigned'])
                        <tr>
                            <td style="{{ $td }} font-style: italic;">{{ $grid['unassigned']['name'] }}</td>
                            <td style="{{ $td }} text-align: right;">{{ $fmt($grid['unassigned']['original']) }}</td>
                            <td style="{{ $td }} text-align: right;">{{ (float) $grid['unassigned']['changes'] == 0.0 ? '—' : $signed($grid['unassigned']['changes']) }}</td>
                            <td style="{{ $td }} text-align: right;">{{ $fmt($grid['unassigned']['revised']) }}</td>
                            <td style="{{ $td }} text-align: right;">{{ $fmt($grid['unassigned']['committed']) }}</td>
                            <td style="{{ $td }} text-align: right;">{{ $fmt($grid['unassigned']['actual']) }}</td>
                            <td style="{{ $td }} text-align: right;">{{ $fmt($grid['unassigned']['remaining']) }}</td>
                        </tr>
                    @endif

                    @if($showLocation)
                        <tr>
                            <td style="{{ $td }} background: #f9f9f9; font-weight: bold;">{{ __('Sub-Total') }}</td>
                            <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">{{ $fmt($grid['totals']['original']) }}</td>
                            <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">{{ (float) $grid['totals']['changes'] == 0.0 ? '—' : $signed($grid['totals']['changes']) }}</td>
                            <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">{{ $fmt($grid['totals']['revised']) }}</td>
                            <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">{{ $fmt($grid['totals']['committed']) }}</td>
                            <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">{{ $fmt($grid['totals']['actual']) }}</td>
                            <td style="{{ $td }} background: #f9f9f9; text-align: right; font-weight: bold;">{{ $fmt($grid['totals']['remaining']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            @if($costCodes['totals'])
                <tfoot>
                    <tr>
                        <td style="{{ $td }} background: #3F5189; color: #fff; font-weight: bold;">{{ __('Total') }}</td>
                        <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">{{ $fmt($costCodes['totals']['original']) }}</td>
                        <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">{{ (float) $costCodes['totals']['changes'] == 0.0 ? '—' : $signed($costCodes['totals']['changes']) }}</td>
                        <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">{{ $fmt($costCodes['totals']['revised']) }}</td>
                        <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">{{ $fmt($costCodes['totals']['committed']) }}</td>
                        <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">{{ $fmt($costCodes['totals']['actual']) }}</td>
                        <td style="{{ $td }} background: #3F5189; color: #fff; text-align: right; font-weight: bold;">{{ $fmt($costCodes['totals']['remaining']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif
</div>
