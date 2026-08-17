{{-- Payment Schedule PDF partial — expects $paymentSchedule (PaymentScheduleService::build() payload) --}}
<div style="font-size: 10pt; font-weight: bold; color: #3F5189; margin: 12px 0 4px 0;">{{ __('Payment Schedule') }}</div>
<div style="font-size: 6.5pt; color: #888; margin-bottom: 4px;">
    {{ __('Expense payments and contract installments by due date. Overdue is derived from due dates as of today. Cancelled expenses and contracts excluded.') }}
</div>

{{-- Expense schedule tiles --}}
<table style="width: 100%; border: none; margin-bottom: 8px;">
    <tr>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Expense Commitments') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #3F5189;">${{ number_format($paymentSchedule['expenses']['total'], 2) }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Paid') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #27ae60;">${{ number_format($paymentSchedule['expenses']['paid'], 2) }}</div>
            <div style="font-size: 6.5pt; color: #888;">{{ $paymentSchedule['expenses']['paid_count'] }} {{ __('payments') }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Upcoming') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #d97706;">${{ number_format($paymentSchedule['expenses']['upcoming'], 2) }}</div>
            <div style="font-size: 6.5pt; color: #888;">{{ $paymentSchedule['expenses']['open_count'] - $paymentSchedule['expenses']['overdue_count'] }} {{ __('payments') }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #f9fafb;">
            <div style="font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;">{{ __('Overdue') }}</div>
            <div style="font-size: 10pt; font-weight: bold; color: #e74c3c;">${{ number_format($paymentSchedule['expenses']['overdue'], 2) }}</div>
            <div style="font-size: 6.5pt; color: #888;">{{ $paymentSchedule['expenses']['overdue_count'] }} {{ __('payments') }}</div>
        </td>
    </tr>
</table>

{{-- Combined summary --}}
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 8px;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;"></th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Committed') }}</th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Paid') }}</th>
            <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Outstanding') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ __('Expenses') }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['expenses']['total'], 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['expenses']['paid'], 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['expenses']['outstanding'], 2) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ __('Contracts') }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['contracts']['total'], 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['contracts']['paid'], 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['contracts']['outstanding'], 2) }}</td>
        </tr>
    </tbody>
    <tfoot>
        <tr style="background-color: #f3f4f6; font-weight: bold;">
            <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ __('Total') }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['committed'], 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['paid'], 2) }}</td>
            <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['combined']['outstanding'], 2) }}</td>
        </tr>
    </tfoot>
</table>
<div style="font-size: 6.5pt; color: #888; margin-bottom: 8px;">
    {{ __('Contract installments are due on their schedule date; whatever the schedule does not cover — and contracts without one — is due on the contract end date.') }}
</div>

{{-- Monthly projection --}}
<div style="font-size: 9pt; font-weight: bold; color: #3F5189; margin: 8px 0 4px 0;">{{ __('Upcoming Payments by Month') }}</div>
@if (empty($paymentSchedule['projection']['buckets']))
    <div style="font-size: 7.5pt; color: #888; margin-bottom: 12px;">{{ __('No open scheduled payments.') }}</div>
@else
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: left;">{{ __('Month') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Payments Due') }}</th>
                <th style="border: 1px solid #ddd; padding: 5px 6px; text-align: right;">{{ __('Amount Due') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($paymentSchedule['projection']['buckets'] as $bucket)
                @php
                    $isOverdue = $bucket['type'] === 'overdue';
                    $bucketLabel = match ($bucket['type']) {
                        'overdue' => __('Overdue (past due)'),
                        'undated' => __('No due date'),
                        default => $bucket['label'],
                    };
                @endphp
                <tr @if($isOverdue) style="background-color: #fdf2f2;" @endif>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; {{ $isOverdue ? 'color: #e74c3c; font-weight: bold;' : '' }}">{{ $bucketLabel }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; {{ $isOverdue ? 'color: #e74c3c;' : '' }}">{{ $bucket['count'] }}</td>
                    <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right; {{ $isOverdue ? 'color: #e74c3c; font-weight: bold;' : '' }}">${{ number_format($bucket['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td style="border: 1px solid #ddd; padding: 4px 6px;">{{ __('Total Open') }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">{{ $paymentSchedule['projection']['total_count'] }}</td>
                <td style="border: 1px solid #ddd; padding: 4px 6px; text-align: right;">${{ number_format($paymentSchedule['projection']['total_open'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endif
