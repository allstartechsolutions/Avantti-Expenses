<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meeting->number }}</title>
</head>
@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
@endphp
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f5f7; color: #333;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; padding: 30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background-color: #3F5189; padding: 25px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: bold;">
                                {{ \App\Models\Company::first()?->name ?? config('app.name') }}
                            </h1>
                            <p style="margin: 6px 0 0; color: #dbe1f2; font-size: 13px;">{{ __('Meeting Minutes') }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 30px 10px;">
                            <p style="margin: 0 0 14px; font-size: 15px;">
                                {{ __('Hello :name,', ['name' => $attendee->displayName()]) }}
                            </p>
                            <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
                                {{ __('The minutes of :title, held on :date, are attached and recorded in the system.', [
                                    'title' => $meeting->title,
                                    'date' => $meeting->meeting_date->format($dateFormat),
                                ]) }}
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #555;">
                                        <strong style="color: #3F5189;">{{ $meeting->number }}</strong><br>
                                        {{ $meeting->meeting_date->format($dateFormat) }}
                                        @if($meeting->started_at) · {{ substr($meeting->started_at, 0, 5) }} @endif
                                        @if($meeting->location) · {{ $meeting->location }} @endif<br>
                                        {{ __('Chair: :chair', ['chair' => $meeting->chair?->name ?? '—']) }}
                                        @if($attendee->attendance !== 'present')
                                            <br><span style="color: #b45309;">{{ __('You were recorded as :status.', ['status' => $attendee->getAttendanceLabel()]) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if($theirs->isNotEmpty())
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: bold; color: #3F5189;">
                                    {{ __('What you are on the hook for') }}
                                </p>
                                <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e9ecef; border-radius: 6px; margin-bottom: 18px;">
                                    @foreach($theirs as $item)
                                        @php $snapshot = $item->status_at_meeting ?? []; @endphp
                                        <tr>
                                            <td style="padding: 10px 14px; border-bottom: 1px solid #f1f3f5; font-size: 13px;">
                                                <strong>{{ $item->task->code() }}</strong> — {{ $item->title }}<br>
                                                <span style="color: #777;">
                                                    {{ __('Due Date') }}:
                                                    <strong>{{ $item->task->due_date?->format($dateFormat) ?? '—' }}</strong>
                                                    · {{ $snapshot['progress'] ?? $item->task->progress }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if($actionItems->isNotEmpty())
                                <p style="margin: 0 0 8px; font-size: 13px; color: #777;">
                                    {{ trans_choice(
                                        'The minute carries :count action item in total.|The minute carries :count action items in total.',
                                        $actionItems->count(), ['count' => $actionItems->count()]) }}
                                </p>
                            @endif

                            @if($attendee->user_id)
                                <p style="margin: 18px 0 0; text-align: center;">
                                    <a href="{{ route('meetings.show', $meeting) }}"
                                       style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
                                        {{ __('Open the minute') }}
                                    </a>
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 30px 26px; text-align: center; font-size: 11px; color: #999;">
                            {{ __('This minute is a record. Corrections to it are logged and shown on the document.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
