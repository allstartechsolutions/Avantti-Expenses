@php
    $currency = config('app.currency');
    $locale = config('app.locale');

    $money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, $currency, $locale);

    $statusColors = [
        'draft' => '#95a5a6',
        'partially_approved' => '#e67e22',
        'approved' => '#27ae60',
        'cancelled' => '#e74c3c',
        'pending' => '#e67e22',
        'rejected' => '#e74c3c',
    ];

    $th = 'background-color: #3F5189; color: #fff; border: 1px solid #3F5189; padding: 5px 4px; font-size: 7pt; font-weight: bold;';
    $td = 'border: 1px solid #ddd; padding: 4px; font-size: 7pt;';
    $label = 'font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase;';

    $filters = array_filter([
        $batch->client ? __('Client') . ': ' . $batch->client->company_name : null,
        $batch->project ? __('Project') . ': ' . $batch->project->project_name : null,
        $batch->subcontractor ? __('Subcontractor') . ': ' . $batch->subcontractor->company_name : null,
        $batch->projectManager ? __('Project Manager') . ': ' . $batch->projectManager->name : null,
        $batch->supervisor ? __('Supervisor') . ': ' . $batch->supervisor->name : null,
        $batch->contract_status_filter ? __('Status') . ': ' . __(ucwords(str_replace('_', ' ', $batch->contract_status_filter))) : null,
        $batch->show_zero_balance ? __('Including Paid/Cancelled') : null,
    ]);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Payment Batch') }} — {{ $batch->name }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.4; color: #333; margin: 0; padding: 15px;">

    <!-- Header -->
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
                        @if($company->phone) | {{ $company->phone }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right; border: none; padding: 0;">
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('PAYMENT BATCH') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #333;">{{ $batch->name }}</div>
                <div style="font-size: 8pt; margin-top: 2px;">
                    <span style="color: {{ $statusColors[$batch->status] ?? '#95a5a6' }}; font-weight: bold;">{{ __($batch->getStatusLabel()) }}</span>
                </div>
                <div style="font-size: 7pt; color: #888; margin-top: 2px;">{{ __('Generated') }}: {{ $generatedAt->appDateTime() }}</div>
            </td>
        </tr>
    </table>

    <!-- Batch details -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="{{ $td }} width: 20%;">
                <div style="{{ $label }}">{{ __('Payment Date') }}</div>
                <div>{{ $batch->payment_date->appDate() }}</div>
            </td>
            <td style="{{ $td }} width: 20%;">
                <div style="{{ $label }}">{{ __('Created By') }}</div>
                <div>{{ $batch->createdBy?->name ?? '—' }}</div>
            </td>
            <td style="{{ $td }} width: 20%;">
                <div style="{{ $label }}">{{ __('Created At') }}</div>
                <div>{{ $batch->created_at->appDateTime() }}</div>
            </td>
            <td style="{{ $td }} width: 20%;">
                <div style="{{ $label }}">{{ __('Approved By') }}</div>
                <div>{{ $batch->approvedBy?->name ?? '—' }}</div>
            </td>
            <td style="{{ $td }} width: 20%;">
                <div style="{{ $label }}">{{ __('Approved At') }}</div>
                <div>{{ $batch->approved_at?->appDateTime() ?? '—' }}</div>
            </td>
        </tr>
        @if($batch->notes || count($filters) > 0)
            <tr>
                <td colspan="5" style="{{ $td }}">
                    @if($batch->notes)
                        <div style="{{ $label }}">{{ __('Notes') }}</div>
                        <div style="margin-bottom: {{ count($filters) > 0 ? '4px' : '0' }};">{{ $batch->notes }}</div>
                    @endif
                    @if(count($filters) > 0)
                        <div style="{{ $label }}">{{ __('Contract Filters') }}</div>
                        <div style="color: #666;">{{ implode(' | ', $filters) }}</div>
                    @endif
                </td>
            </tr>
        @endif
    </table>

    <!-- Summary -->
    <table style="width: 100%; border: none; margin-bottom: 15px;">
        <tr>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="{{ $label }}">{{ __('Items') }}</div>
                <div style="font-size: 12pt; font-weight: bold; color: #333;">{{ $items->count() }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="{{ $label }}">{{ __('Total Amount') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #333;">{{ $money($summary['total_amount']) }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="{{ $label }}">{{ __('Approved') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #27ae60;">{{ $money($summary['approved_amount']) }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="{{ $label }}">{{ __('Pending') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #e67e22;">{{ $money($summary['pending_amount']) }}</div>
            </td>
            <td style="width: 20%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f9fafb;">
                <div style="{{ $label }}">{{ __('Rejected') }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #e74c3c;">{{ $money($summary['rejected_amount']) }}</div>
            </td>
        </tr>
    </table>

    <!-- Items -->
    @if($items->isEmpty())
        <div style="border: 1px solid #ddd; padding: 20px; text-align: center; color: #888;">
            {{ __("This batch doesn't have any items yet.") }}
        </div>
    @else
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <thead>
                <tr>
                    <th style="{{ $th }} text-align: left;">{{ __('Subcontractor') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Project') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Job Site') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Contract #') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Pays') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Method') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Phase') }}</th>
                    <th style="{{ $th }} text-align: left;">{{ __('Notes') }}</th>
                    <th style="{{ $th }} text-align: center;">{{ __('Status') }}</th>
                    <th style="{{ $th }} text-align: right;">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                    @php
                        $rejected = $item->status === 'rejected';
                        $muted = $rejected ? 'color: #999; text-decoration: line-through;' : '';
                    @endphp
                    <tr style="background-color: {{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                        <td style="{{ $td }} {{ $muted }}">{{ $item->contract->subcontractor?->company_name ?? '—' }}</td>
                        <td style="{{ $td }} {{ $muted }}">
                            {{ $item->contract->project->project_name }}
                            @if($item->contract->project->client)
                                <br><span style="font-size: 6pt; color: #888;">{{ $item->contract->project->client->company_name }}</span>
                            @endif
                        </td>
                        <td style="{{ $td }} {{ $muted }}">{{ $item->contract->jobSite?->job_site_name ?? __('Project General') }}</td>
                        <td style="{{ $td }} {{ $muted }} font-weight: bold;">{{ $item->contract->contract_number }}</td>
                        <td style="{{ $td }} {{ $muted }}">{{ $item->getPaysLabel() }}</td>
                        <td style="{{ $td }} {{ $muted }}">{{ __($item->getPaymentMethodLabel()) }}</td>
                        <td style="{{ $td }} {{ $muted }}">{{ $item->phase ?? '—' }}</td>
                        <td style="{{ $td }} {{ $muted }}">{{ $item->notes ?? '—' }}</td>
                        <td style="{{ $td }} text-align: center; font-weight: bold; color: {{ $statusColors[$item->status] ?? '#95a5a6' }};">{{ __($item->getStatusLabel()) }}</td>
                        <td style="{{ $td }} {{ $muted }} text-align: right; font-weight: bold;">{{ $money($item->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #eef1f8;">
                    <td colspan="9" style="{{ $td }} text-align: right; font-weight: bold;">{{ __('Approved') }}</td>
                    <td style="{{ $td }} text-align: right; font-weight: bold; color: #27ae60;">{{ $money($summary['approved_amount']) }}</td>
                </tr>
                <tr style="background-color: #eef1f8;">
                    <td colspan="9" style="{{ $td }} text-align: right; font-weight: bold;">{{ __('Pending') }}</td>
                    <td style="{{ $td }} text-align: right; font-weight: bold; color: #e67e22;">{{ $money($summary['pending_amount']) }}</td>
                </tr>
                <tr style="background-color: #eef1f8;">
                    <td colspan="9" style="{{ $td }} text-align: right; font-weight: bold;">{{ __('Rejected') }}</td>
                    <td style="{{ $td }} text-align: right; font-weight: bold; color: #e74c3c;">{{ $money($summary['rejected_amount']) }}</td>
                </tr>
                <tr style="background-color: #3F5189; color: #fff;">
                    <td colspan="9" style="border: 1px solid #3F5189; padding: 5px 4px; font-size: 8pt; text-align: right; font-weight: bold;">{{ __('Total') }}</td>
                    <td style="border: 1px solid #3F5189; padding: 5px 4px; font-size: 8pt; text-align: right; font-weight: bold;">{{ $money($summary['total_amount']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

</body>
</html>
