<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Payment Schedule') }}</title>
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
                <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('PAYMENT SCHEDULE') }}</div>
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
                @if($client)
                    <div style="font-size: 7pt; color: #888;">{{ __('Client') }}: {{ $client->company_name }}</div>
                @endif
                @if($project)
                    <div style="font-size: 7pt; color: #888;">{{ __('Project') }}: {{ $project->project_name }}</div>
                @endif
                @if($jobSite)
                    <div style="font-size: 7pt; color: #888;">{{ __('Job Site') }}: {{ $jobSite->job_site_name }}</div>
                @endif
            </td>
        </tr>
    </table>

    @include('pdf.partials.payment-schedule', ['paymentSchedule' => $paymentSchedule])

    {{-- Footer --}}
    <div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 6.5pt; color: #999; text-align: center;">
        {{ $company?->name }} — {{ __('Payment Schedule') }} — {{ $generatedAt->appDate() }}
    </div>
</body>
</html>
