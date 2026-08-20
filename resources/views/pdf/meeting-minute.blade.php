<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $meeting->number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.45; color: #333; margin: 0; padding: 18px;">
@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    $stampFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';

    // Editor output has to be stripped for print: dompdf will not lay out
    // arbitrary markup, and the minute is a record, not a web page.
    $plain = fn ($html) => trim(preg_replace("/\n{3,}/", "\n\n",
        html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", (string) $html)))));

    $actionItems = $items->filter(fn ($i) => $i->type === 'action');
@endphp

{{-- Header --}}
<table style="width: 100%; border: none; margin-bottom: 12px; border-bottom: 2px solid #3F5189; padding-bottom: 8px;">
    <tr>
        <td style="width: 55%; vertical-align: top; border: none; padding: 0;">
            @if($company)
                @if($logoData)
                    <img src="{{ $logoData }}" style="max-height: 40px; max-width: 150px; margin-bottom: 4px;">
                @endif
                <div style="font-size: 12pt; font-weight: bold; color: #3F5189;">{{ $company->name }}</div>
                <div style="font-size: 7pt; color: #666;">
                    {{ $company->full_address ?? '' }}
                    @if($company->phone) | P: {{ $company->phone }}@endif
                </div>
            @endif
        </td>
        <td style="width: 45%; vertical-align: top; text-align: right; border: none; padding: 0;">
            <div style="font-size: 14pt; font-weight: bold; color: #3F5189;">{{ __('MEETING MINUTES') }}</div>
            <div style="font-size: 11pt; font-weight: bold; margin-top: 2px;">{{ $meeting->number }}</div>
            @if($meeting->series)
                <div style="font-size: 8pt; color: #666;">{{ $meeting->series->name }}</div>
            @endif
            @if($meeting->isDraft())
                <div style="font-size: 8pt; color: #b45309; font-weight: bold; margin-top: 3px;">{{ __('DRAFT — NOT YET PUBLISHED') }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- When and where --}}
<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
        <td style="width: 25%; border: 1px solid #e2e8f0; padding: 5px 7px; background: #f8fafc;">
            <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Date') }}</div>
            <div style="font-weight: bold;">{{ $meeting->meeting_date->format($dateFormat) }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #e2e8f0; padding: 5px 7px; background: #f8fafc;">
            <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Time') }}</div>
            <div style="font-weight: bold;">
                @if($meeting->started_at){{ substr($meeting->started_at, 0, 5) }}@if($meeting->ended_at) – {{ substr($meeting->ended_at, 0, 5) }}@endif @else — @endif
            </div>
        </td>
        <td style="width: 25%; border: 1px solid #e2e8f0; padding: 5px 7px; background: #f8fafc;">
            <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Location') }}</div>
            <div style="font-weight: bold;">{{ $meeting->location ?: '—' }}</div>
        </td>
        <td style="width: 25%; border: 1px solid #e2e8f0; padding: 5px 7px; background: #f8fafc;">
            <div style="font-size: 7pt; color: #64748b; text-transform: uppercase;">{{ __('Chair') }}</div>
            <div style="font-weight: bold;">{{ $meeting->chair?->name ?? '—' }}</div>
        </td>
    </tr>
</table>

{{-- Attendance: the minute records who was absent as well as who was there --}}
<div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 4px;">{{ __('Attendance') }}</div>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8pt;">
    <thead>
        <tr style="background: #f1f5f9;">
            <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 38%;">{{ __('Name') }}</th>
            <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 27%;">{{ __('Company') }}</th>
            <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 20%;">{{ __('Role') }}</th>
            <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 15%;">{{ __('Attendance') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($meeting->attendees as $attendee)
            <tr>
                <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">{{ $attendee->displayName() }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">{{ $attendee->company ?: ($attendee->isExternal() ? '—' : $company?->name) }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">{{ $attendee->getRoleLabel() }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">{{ $attendee->getAttendanceLabel() }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="border: 1px solid #e2e8f0; padding: 6px 7px; color: #94a3b8;">{{ __('Nobody on the register.') }}</td></tr>
        @endforelse
    </tbody>
</table>

@if(filled($meeting->summary))
    <div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 4px;">{{ __('Notes') }}</div>
    <div style="border: 1px solid #e2e8f0; padding: 6px 8px; margin-bottom: 12px; white-space: pre-line;">{{ $plain($meeting->summary) }}</div>
@endif

{{-- The agenda, as taken --}}
<div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin-bottom: 4px;">{{ __('Agenda') }}</div>

@forelse($items as $item)
    @php $snapshot = $item->status_at_meeting ?? []; @endphp

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; {{ $item->parent_id ? 'margin-left: 18px; width: 96%;' : '' }}">
        <tr>
            <td style="border: 1px solid #e2e8f0; border-left: 3px solid {{ $item->type === 'decision' ? '#7c3aed' : ($item->type === 'action' ? '#3F5189' : '#cbd5e1') }}; padding: 6px 8px;">
                <div>
                    <span style="font-weight: bold;">{{ $item->number() }}.</span>
                    <span style="font-weight: bold;">{{ $item->title }}</span>
                    <span style="font-size: 7pt; color: #64748b;">
                        [{{ $item->getTypeLabel() }}]
                        @if($item->getScopeLabel() !== __('General')) · {{ $item->getScopeLabel() }} @endif
                        @if($item->isCarriedForward()) · {{ __('from :number', ['number' => $item->carriedFrom?->meeting?->number]) }} @endif
                        @unless($item->discussed) · {{ __('not discussed') }} @endunless
                    </span>
                </div>

                @if($item->discussion)
                    <div style="margin-top: 4px; white-space: pre-line;">{{ $item->discussion }}</div>
                @endif

                @if($item->decision)
                    <div style="margin-top: 5px; padding: 5px 7px; background: #f5f3ff; border-left: 2px solid #7c3aed;">
                        <span style="font-size: 7pt; font-weight: bold; color: #7c3aed; text-transform: uppercase;">{{ __('Decision') }}:</span>
                        <span style="white-space: pre-line;">{{ $item->decision }}</span>
                    </div>
                @endif

                @if($item->task)
                    <div style="margin-top: 4px; font-size: 7.5pt; color: #475569;">
                        {{ __('Task') }} {{ $item->task->code() }} ·
                        {{ __('Owner') }}: <strong>{{ $snapshot['owner_name'] ?? $item->task->owner?->name }}</strong> ·
                        {{ __('Due Date') }}: <strong>{{ ($snapshot['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($snapshot['due_date'])->format($dateFormat) : ($item->task->due_date?->format($dateFormat) ?? '—') }}</strong> ·
                        {{ __('Progress') }}: <strong>{{ $snapshot['progress'] ?? $item->task->progress }}%</strong>
                    </div>
                @endif
            </td>
        </tr>
    </table>
@empty
    <div style="border: 1px solid #e2e8f0; padding: 6px 8px; color: #94a3b8; margin-bottom: 12px;">{{ __('Nothing on the agenda') }}</div>
@endforelse

{{-- The table people actually act on --}}
@if($actionItems->isNotEmpty())
    <div style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase; margin: 14px 0 4px;">{{ __('Action Items') }}</div>
    <table style="width: 100%; border-collapse: collapse; font-size: 8pt;">
        <thead>
            <tr style="background: #f1f5f9;">
                <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 6%;">#</th>
                <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 42%;">{{ __('Task') }}</th>
                <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 20%;">{{ __('Owner') }}</th>
                <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 14%;">{{ __('Due Date') }}</th>
                <th style="border: 1px solid #e2e8f0; padding: 4px 7px; text-align: left; width: 18%;">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($actionItems as $item)
                @php
                    $snapshot = $item->status_at_meeting ?? [];
                    $overdue = ($snapshot['due_date'] ?? null)
                        && \Illuminate\Support\Carbon::parse($snapshot['due_date'])->lt($meeting->meeting_date);
                @endphp
                <tr>
                    <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">{{ $item->number() }}</td>
                    <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">
                        {{ $item->title }}
                        @if($item->isCarriedForward())
                            <div style="font-size: 7pt; color: #64748b;">{{ __('open since :number', ['number' => $item->carriedFrom?->meeting?->number]) }}</div>
                        @endif
                    </td>
                    <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">{{ $snapshot['owner_name'] ?? $item->task?->owner?->name ?? '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; padding: 4px 7px; {{ $overdue ? 'color: #dc2626; font-weight: bold;' : '' }}">
                        {{ ($snapshot['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($snapshot['due_date'])->format($dateFormat) : '—' }}
                    </td>
                    <td style="border: 1px solid #e2e8f0; padding: 4px 7px;">
                        {{ $item->task?->getStatusLabel() ?? '—' }}
                        <span style="color: #64748b;">{{ $snapshot['progress'] ?? $item->task?->progress ?? 0 }}%</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Corrections, if the record has been changed since --}}
@if($meeting->revisions->isNotEmpty())
    <div style="font-size: 8pt; font-weight: bold; color: #b45309; text-transform: uppercase; margin: 14px 0 4px;">{{ __('Corrections') }}</div>
    @foreach($meeting->revisions->sortBy('revision_number') as $revision)
        <div style="border: 1px solid #fde68a; background: #fffbeb; padding: 5px 7px; margin-bottom: 4px; font-size: 8pt;">
            <strong>{{ __('Revision :number', ['number' => $revision->revision_number]) }}</strong>
            — {{ $revision->revisedBy?->name }}, {{ $revision->created_at?->format($stampFormat) }}<br>
            {{ $revision->reason }}
        </div>
    @endforeach
@endif

{{-- The next one --}}
@if($meeting->next_meeting_date)
    <div style="margin-top: 14px; border: 1px solid #e2e8f0; padding: 6px 8px;">
        <span style="font-size: 8pt; font-weight: bold; color: #3F5189; text-transform: uppercase;">{{ __('The Next Meeting') }}:</span>
        <strong>{{ $meeting->next_meeting_date->format($dateFormat) }}</strong>
        @if($meeting->nextMeeting) ({{ $meeting->nextMeeting->number }}) @endif
    </div>
@endif

{{-- Footer --}}
<div style="margin-top: 16px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 7pt; color: #94a3b8;">
    @if($meeting->isPublished())
        {{ __('Published by :name on :date.', ['name' => $meeting->publishedBy?->name, 'date' => $meeting->published_at?->format($stampFormat)]) }}
    @else
        {{ __('This is a draft and has not been published.') }}
    @endif
    @if($meeting->secretary) · {{ __('Secretary: :name', ['name' => $meeting->secretary->name]) }} @endif
    · {{ __('Generated :date', ['date' => now()->format($stampFormat)]) }}
</div>
</body>
</html>
