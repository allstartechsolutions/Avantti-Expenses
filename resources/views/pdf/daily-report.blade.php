<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Daily Report - {{ $dailyReport->report_date->format('m/d/Y') }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #333; margin: 0; padding: 20px;">

    <!-- Header -->
    <table style="width: 100%; border: none; margin-bottom: 15px; border-bottom: 2px solid #3F5189; padding-bottom: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                @if($company)
                    <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
                    <div style="font-size: 8pt; color: #666;">
                        {{ $company->full_address ?? '' }}<br>
                        @if($company->phone)P: {{ $company->phone }}@endif
                        @if($company->fax) | F: {{ $company->fax }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right; border: none; padding: 0;">
                <div style="font-size: 11pt; font-weight: bold; color: #3F5189;">Project: {{ $dailyReport->jobSite->project->project_name }}</div>
                <div style="font-size: 8pt; color: #666;">
                    <strong>Job Site:</strong> {{ $dailyReport->jobSite->job_site_name }}<br>
                    {{ $dailyReport->jobSite->full_address ?? '' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Report Title -->
    <div style="font-size: 16pt; font-weight: bold; color: #333; margin-bottom: 15px; padding-top: 10px; border-top: 3px solid #3F5189;">
        Daily Log: {{ $dailyReport->report_date->format('l n/j/Y') }}
    </div>

    <!-- Status Box -->
    <div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 10px 15px; margin-bottom: 20px;">
        <div style="font-weight: bold; color: #2e7d32; margin-bottom: 3px;">Daily Log Completed</div>
        <div style="font-size: 8pt; color: #555;">
            Prepared by {{ $dailyReport->preparedBy->name }} on {{ $dailyReport->created_at->format('D, M d, Y \a\t h:i A T') }}
        </div>
    </div>

    <!-- Weather Report -->
    @if($dailyReport->weather)
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        WEATHER REPORT
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <th colspan="3" style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Temperature</th>
            <th colspan="3" style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Precipitation Since</th>
            <th colspan="4" style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Humidity</th>
            <th colspan="3" style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Windspeed</th>
        </tr>
        <tr>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Low</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">High</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Avg</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Midnight</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">2 Days</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">3 Days</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Low</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Avg</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">High</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Dew</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Avg</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Max</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Gust</th>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_temp_low }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_temp_high }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_temp_avg }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_precip_midnight }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_precip_2_days }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_precip_3_days }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->humidity_low ?? '-' }}%</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->humidity_avg ?? '-' }}%</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->humidity_high ?? '-' }}%</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_dew_point }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_wind_avg }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_wind_max }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $dailyReport->weather->formatted_wind_gust }}</td>
        </tr>
    </table>

    <!-- Daily Snapshot -->
    @if($dailyReport->weather->snapshots && count($dailyReport->weather->snapshots) > 0)
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        DAILY SNAPSHOT
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            @foreach($dailyReport->weather->formatted_snapshots as $snapshot)
            <td style="border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #fafafa;">
                <div style="font-size: 8pt; color: #666; margin-bottom: 3px;">{{ $snapshot['time'] }}</div>
                <div style="font-size: 8pt; color: #555;">{{ $snapshot['condition'] }}</div>
                <div style="font-size: 11pt; font-weight: bold; color: #333;">{{ $snapshot['formatted_temp'] }}</div>
            </td>
            @endforeach
        </tr>
    </table>
    @endif
    @endif

    <!-- Observed Weather Conditions -->
    @if($dailyReport->weatherObservations && $dailyReport->weatherObservations->count() > 0)
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        OBSERVED WEATHER CONDITIONS
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555; width: 30px;">No.</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Time</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Delay</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Sky</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Temp</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Precip</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Wind</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555;">Notes</th>
        </tr>
        @foreach($dailyReport->weatherObservations as $index => $obs)
        <tr>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $index + 1 }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt;">{{ $obs->formatted_time }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">
                @if($obs->weather_delay)
                    <span style="background-color: #ffebee; color: #c62828; padding: 2px 6px; font-size: 7pt; font-weight: bold;">Yes</span>
                @else
                    <span style="background-color: #e8f5e9; color: #2e7d32; padding: 2px 6px; font-size: 7pt; font-weight: bold;">No</span>
                @endif
            </td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt;">{{ $obs->sky_condition_label }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $obs->formatted_temperature }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt;">{{ $obs->precipitation_label }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt;">{{ $obs->wind_condition_label }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt;">{{ $obs->notes ?? '-' }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <!-- Manpower Log -->
    @if($dailyReport->manpowerLogs && $dailyReport->manpowerLogs->count() > 0)
    @php
        $totalWorkers = $dailyReport->manpowerLogs->sum('workers');
        $totalManHours = $dailyReport->manpowerLogs->sum(function($log) {
            return $log->workers * $log->hours;
        });
    @endphp
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        MANPOWER LOG
        <span style="float: right; font-size: 10pt; color: #3F5189;">{{ $totalWorkers }} Workers | {{ number_format($totalManHours, 1) }} Man Hours</span>
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555; width: 30px;">No.</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: left; font-size: 8pt; font-weight: bold; color: #555;">Contact/Company</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555; width: 60px;">Workers</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555; width: 60px;"># Hours</th>
            <th style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 5px 6px; text-align: center; font-size: 8pt; font-weight: bold; color: #555; width: 70px;">Man Hours</th>
        </tr>
        @foreach($dailyReport->manpowerLogs as $index => $log)
        <tr>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $index + 1 }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt;">
                <strong>{{ $log->contact_company }}</strong>
                @if($log->works)
                    <br><span style="font-size: 7pt; color: #666; font-style: italic;">Works: {{ $log->works }}</span>
                @endif
                @if($log->comments)
                    <br><span style="font-size: 7pt; color: #666; font-style: italic;">Comments: {{ $log->comments }}</span>
                @endif
            </td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ $log->workers }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ number_format($log->hours, 1) }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center;">{{ number_format($log->workers * $log->hours, 1) }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="2" style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: right; background-color: #f0f0f0; font-weight: bold;">Total:</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center; background-color: #f0f0f0; font-weight: bold;">{{ $totalWorkers }}</td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; background-color: #f0f0f0;"></td>
            <td style="border: 1px solid #ddd; padding: 5px 6px; font-size: 8pt; text-align: center; background-color: #f0f0f0; font-weight: bold;">{{ number_format($totalManHours, 1) }}</td>
        </tr>
    </table>
    @endif

    <!-- Tasks -->
    @if($dailyReport->tasks && $dailyReport->tasks->count() > 0)
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        TASKS
    </div>
    @foreach($dailyReport->tasks as $index => $task)
    <div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background-color: #fafafa;">
        <span style="display: inline-block; background-color: #3F5189; color: white; width: 18px; height: 18px; text-align: center; line-height: 18px; font-size: 8pt; font-weight: bold; margin-right: 8px;">{{ $index + 1 }}</span>
        <span style="font-weight: bold; font-size: 9pt;">Task {{ $index + 1 }}</span>
        <div style="font-size: 8pt; color: #555; margin-top: 8px; line-height: 1.5;">{!! strip_tags($task->description, '<br><p><ul><li><ol>') !!}</div>
    </div>
    @endforeach
    @endif

    <!-- Signature Section -->
    <table style="width: 100%; border: none; margin-top: 40px;">
        <tr>
            <td style="width: 33%; padding: 0 10px; border: none; vertical-align: top;">
                <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 8pt; margin-top: 30px;">By</div>
            </td>
            <td style="width: 33%; padding: 0 10px; border: none; vertical-align: top;">
                <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 8pt; margin-top: 30px;">Date</div>
            </td>
            <td style="width: 33%; padding: 0 10px; border: none; vertical-align: top;">
                <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 8pt; margin-top: 30px;">Copies To</div>
            </td>
        </tr>
    </table>

    <!-- Attachments Section -->
    @php
        $hasManpowerImages = $dailyReport->manpowerLogs && $dailyReport->manpowerLogs->contains(function($log) {
            return $log->images && $log->images->count() > 0;
        });
        $hasTaskImages = $dailyReport->tasks && $dailyReport->tasks->contains(function($task) {
            return $task->images && $task->images->count() > 0;
        });
    @endphp

    @if($hasManpowerImages || $hasTaskImages)
    <div style="page-break-before: always;"></div>

    <!-- Manpower Attachments -->
    @if($hasManpowerImages)
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        Manpower Log Attachments
    </div>
    @foreach($dailyReport->manpowerLogs as $index => $log)
        @if($log->images && $log->images->count() > 0)
        <div style="font-size: 9pt; font-weight: bold; margin: 15px 0 10px 0; color: #555;">
            {{ $index + 1 }}. {{ $log->contact_company }}
        </div>
        <table style="width: 100%; border: none;">
            @foreach($log->images->chunk(2) as $imageRow)
            <tr>
                @foreach($imageRow as $image)
                <td style="width: 50%; padding: 5px; border: none; vertical-align: top;">
                    <div style="border: 1px solid #ddd; padding: 5px; text-align: center;">
                        @php
                            $imagePath = \Illuminate\Support\Facades\Storage::path($image->file_path);
                            $imageData = '';
                            if (file_exists($imagePath)) {
                                $imageContent = file_get_contents($imagePath);
                                $imageType = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                                $mimeType = $imageType === 'png' ? 'image/png' : 'image/jpeg';
                                $imageData = 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);
                            }
                        @endphp
                        @if($imageData)
                            <img src="{{ $imageData }}" style="max-width: 100%; max-height: 200px;">
                        @else
                            <div style="color: #999; font-size: 8pt;">[Image not found]</div>
                        @endif
                        <div style="font-size: 7pt; color: #666; margin-top: 5px;">{{ $image->file_name }}</div>
                    </div>
                </td>
                @endforeach
                @if($imageRow->count() == 1)
                <td style="width: 50%; padding: 5px; border: none;"></td>
                @endif
            </tr>
            @endforeach
        </table>
        @endif
    @endforeach
    @endif

    <!-- Task Attachments -->
    @if($hasTaskImages)
    <div style="font-size: 11pt; font-weight: bold; color: #333; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">
        Task Attachments
    </div>
    @foreach($dailyReport->tasks as $index => $task)
        @if($task->images && $task->images->count() > 0)
        <div style="font-size: 9pt; font-weight: bold; margin: 15px 0 10px 0; color: #555;">
            Task {{ $index + 1 }}
        </div>
        <table style="width: 100%; border: none;">
            @foreach($task->images->chunk(2) as $imageRow)
            <tr>
                @foreach($imageRow as $image)
                <td style="width: 50%; padding: 5px; border: none; vertical-align: top;">
                    <div style="border: 1px solid #ddd; padding: 5px; text-align: center;">
                        @php
                            $imagePath = \Illuminate\Support\Facades\Storage::path($image->file_path);
                            $imageData = '';
                            if (file_exists($imagePath)) {
                                $imageContent = file_get_contents($imagePath);
                                $imageType = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                                $mimeType = $imageType === 'png' ? 'image/png' : 'image/jpeg';
                                $imageData = 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);
                            }
                        @endphp
                        @if($imageData)
                            <img src="{{ $imageData }}" style="max-width: 100%; max-height: 200px;">
                        @else
                            <div style="color: #999; font-size: 8pt;">[Image not found]</div>
                        @endif
                        <div style="font-size: 7pt; color: #666; margin-top: 5px;">{{ $image->file_name }}</div>
                    </div>
                </td>
                @endforeach
                @if($imageRow->count() == 1)
                <td style="width: 50%; padding: 5px; border: none;"></td>
                @endif
            </tr>
            @endforeach
        </table>
        @endif
    @endforeach
    @endif

    <!-- Signature Section on attachments page -->
    <table style="width: 100%; border: none; margin-top: 40px;">
        <tr>
            <td style="width: 33%; padding: 0 10px; border: none; vertical-align: top;">
                <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 8pt; margin-top: 30px;">By</div>
            </td>
            <td style="width: 33%; padding: 0 10px; border: none; vertical-align: top;">
                <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 8pt; margin-top: 30px;">Date</div>
            </td>
            <td style="width: 33%; padding: 0 10px; border: none; vertical-align: top;">
                <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 8pt; margin-top: 30px;">Copies To</div>
            </td>
        </tr>
    </table>
    @endif

    <!-- Footer -->
    <div style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8pt; color: #666;">
        <span>{{ $company->name ?? config('app.name') }}</span>
        <span style="float: right;">Printed On: {{ now()->format('M d, Y \a\t h:i A T') }}</span>
    </div>

</body>
</html>
