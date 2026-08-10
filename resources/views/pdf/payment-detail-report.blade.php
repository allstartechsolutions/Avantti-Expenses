@php
    $currency = config('app.currency');
    $locale = config('app.locale');

    $money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, $currency, $locale);

    $viewLabels = [
        'detail' => __('Detail'),
        'project' => __('By Project / Job Site'),
        'vendor' => __('By Vendor'),
    ];

    $typeLabels = [
        'all' => __('Expenses + Contracts'),
        'expenses' => __('Expenses only'),
        'contracts' => __('Contracts only'),
    ];

    $statusColors = ['paid' => '#16a34a', 'pending' => '#d97706', 'overdue' => '#dc2626'];

    $th = 'padding: 5px 6px; background: #3F5189; color: #fff; font-size: 7.5pt; text-transform: uppercase; text-align: left;';
    $thRight = $th . ' text-align: right;';
    $td = 'padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 8pt;';
    $tdRight = $td . ' text-align: right;';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Payment Details') }}</title>
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
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('PAYMENT DETAILS') }}</div>
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
                @if($subcontractor)
                    <div style="font-size: 7pt; color: #888;">{{ __('Subcontractor') }}: {{ $subcontractor->company_name }}</div>
                @endif
                <div style="font-size: 7pt; color: #888;">{{ __('Type') }}: {{ $typeLabels[$typeFilter] }}</div>
                <div style="font-size: 7pt; color: #888;">{{ __('Status filter') }}: {{ $statusFilter }}</div>
            </td>
        </tr>
    </table>

    {{-- KPIs --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 25%; padding: 8px; border: 1px solid #e2e8f0; background: #f8fafc;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Total in Period') }}</div>
                <div style="font-size: 11pt; font-weight: bold;">{{ $money($kpis['total']) }}</div>
                <div style="font-size: 7pt; color: #64748b;">{{ $kpis['count'] }} {{ __('payments') }}</div>
            </td>
            <td style="width: 25%; padding: 8px; border: 1px solid #e2e8f0; background: #f8fafc;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Paid') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #16a34a;">{{ $money($kpis['paid']) }}</div>
            </td>
            <td style="width: 25%; padding: 8px; border: 1px solid #e2e8f0; background: #f8fafc;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Pending') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #d97706;">{{ $money($kpis['pending']) }}</div>
            </td>
            <td style="width: 25%; padding: 8px; border: 1px solid #e2e8f0; background: #f8fafc;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Overdue (today)') }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #dc2626;">{{ $money($kpis['overdue']) }}</div>
            </td>
        </tr>
    </table>

    <div style="font-size: 7pt; color: #888; margin-bottom: 10px;">
        {{ __('Contracts have no payment schedule: open contract balances are placed on the contract end date (balances without an end date always appear, undated). Contract payments already made are shown on their payment date.') }}
    </div>

    @if ($view === 'detail')
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="{{ $th }}">{{ __('Date') }}</th>
                    <th style="{{ $th }}">{{ __('Vendor') }}</th>
                    <th style="{{ $th }}">{{ __('Item') }}</th>
                    <th style="{{ $th }}">{{ __('Project') }}</th>
                    <th style="{{ $th }}">{{ __('Job Site') }}</th>
                    <th style="{{ $th }}">{{ __('Installment') }}</th>
                    <th style="{{ $th }}">{{ __('Status') }}</th>
                    <th style="{{ $th }}">{{ __('Paid') }}</th>
                    <th style="{{ $thRight }}">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td style="{{ $td }}">{{ $r['date']?->format('M d, Y') ?? '—' }}</td>
                        <td style="{{ $td }}">{{ $r['vendor'] ?? '—' }}@if($r['type'] === 'contract') ({{ __('Contract') }})@endif</td>
                        <td style="{{ $td }}">{{ $r['item'] ?? '—' }}</td>
                        <td style="{{ $td }}">{{ $r['project'] ?? '—' }}</td>
                        <td style="{{ $td }}">{{ $r['job_site'] ?? __('Project-level') }}</td>
                        <td style="{{ $td }}">{{ $r['installment_label'] ?? '—' }}</td>
                        <td style="{{ $td }} color: {{ $statusColors[$r['status']] ?? '#333' }}; font-weight: bold;">{{ __(ucfirst($r['status'])) }}</td>
                        <td style="{{ $td }}">
                            {{ $r['paid_date']?->format('M d, Y') ?? '—' }}
                            @if($r['paid_by']) <span style="color: #888;">({{ $r['paid_by'] }})</span>@endif
                        </td>
                        <td style="{{ $tdRight }}">{{ $money($r['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="{{ $td }} text-align: center; color: #888;">{{ __('No payments match the selected filters.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" style="{{ $td }} font-weight: bold; background: #f1f5f9;">{{ __('Total') }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9;">{{ $money($kpis['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    @elseif ($view === 'project')
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="{{ $th }}">{{ __('Project / Job Site') }}</th>
                    <th style="{{ $thRight }}">{{ __('Payments') }}</th>
                    <th style="{{ $thRight }}">{{ __('Total') }}</th>
                    <th style="{{ $thRight }}">{{ __('Paid') }}</th>
                    <th style="{{ $thRight }}">{{ __('Pending') }}</th>
                    <th style="{{ $thRight }}">{{ __('Overdue') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byProject as $proj)
                    <tr>
                        <td style="{{ $td }} font-weight: bold; background: #f8fafc;">{{ $proj['project'] }}</td>
                        <td style="{{ $tdRight }} font-weight: bold; background: #f8fafc;">{{ $proj['count'] }}</td>
                        <td style="{{ $tdRight }} font-weight: bold; background: #f8fafc;">{{ $money($proj['total']) }}</td>
                        <td style="{{ $tdRight }} font-weight: bold; background: #f8fafc; color: #16a34a;">{{ $money($proj['paid']) }}</td>
                        <td style="{{ $tdRight }} font-weight: bold; background: #f8fafc; color: #d97706;">{{ $money($proj['pending']) }}</td>
                        <td style="{{ $tdRight }} font-weight: bold; background: #f8fafc; color: #dc2626;">{{ $money($proj['overdue']) }}</td>
                    </tr>
                    @foreach ($proj['jobsites'] as $js)
                        <tr>
                            <td style="{{ $td }} padding-left: 18px;">{{ $js['job_site'] ?? __('Project-level') }}</td>
                            <td style="{{ $tdRight }}">{{ $js['count'] }}</td>
                            <td style="{{ $tdRight }}">{{ $money($js['total']) }}</td>
                            <td style="{{ $tdRight }}">{{ $money($js['paid']) }}</td>
                            <td style="{{ $tdRight }}">{{ $money($js['pending']) }}</td>
                            <td style="{{ $tdRight }}">{{ $money($js['overdue']) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6" style="{{ $td }} text-align: center; color: #888;">{{ __('No payments match the selected filters.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td style="{{ $td }} font-weight: bold; background: #f1f5f9;">{{ __('Total') }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9;">{{ $kpis['count'] }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9;">{{ $money($kpis['total']) }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9; color: #16a34a;">{{ $money($kpis['paid']) }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9; color: #d97706;">{{ $money($kpis['pending']) }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9; color: #dc2626;">{{ $money($kpis['overdue']) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="{{ $th }}">{{ __('Vendor') }}</th>
                    <th style="{{ $thRight }}">{{ __('Payments') }}</th>
                    <th style="{{ $thRight }}">{{ __('Total') }}</th>
                    <th style="{{ $thRight }}">{{ __('Paid') }}</th>
                    <th style="{{ $thRight }}">{{ __('Pending') }}</th>
                    <th style="{{ $thRight }}">{{ __('Overdue') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byVendor as $v)
                    <tr>
                        <td style="{{ $td }}">{{ $v['vendor'] ?? __('No vendor') }}@if($v['type'] === 'contract') ({{ __('Contract') }})@endif</td>
                        <td style="{{ $tdRight }}">{{ $v['count'] }}</td>
                        <td style="{{ $tdRight }}">{{ $money($v['total']) }}</td>
                        <td style="{{ $tdRight }} color: #16a34a;">{{ $money($v['paid']) }}</td>
                        <td style="{{ $tdRight }} color: #d97706;">{{ $money($v['pending']) }}</td>
                        <td style="{{ $tdRight }} color: #dc2626;">{{ $money($v['overdue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }} text-align: center; color: #888;">{{ __('No payments match the selected filters.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td style="{{ $td }} font-weight: bold; background: #f1f5f9;">{{ __('Total') }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9;">{{ $kpis['count'] }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9;">{{ $money($kpis['total']) }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9; color: #16a34a;">{{ $money($kpis['paid']) }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9; color: #d97706;">{{ $money($kpis['pending']) }}</td>
                    <td style="{{ $tdRight }} font-weight: bold; background: #f1f5f9; color: #dc2626;">{{ $money($kpis['overdue']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

</body>
</html>
