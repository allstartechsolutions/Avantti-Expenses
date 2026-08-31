{{-- The US sheet: a transmittal cover citing the specification section. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $document->number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.45; color: #333; margin: 0; padding: 18px;">
@php
    $stampFormat = 'm/d/Y g:i A';
@endphp

@include('pdf.collaboration.partials.header', [
    'heading' => __('collaboration.pdf.request_information'),
    'badge' => $document->isDraft() ? __('collaboration.pdf.draft_issued') : null,
])

<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8pt;">
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px; width: 25%; background: #f8fafc;"><strong>{{ __('Subject') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;" colspan="3">{{ $document->subject }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.discipline') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px; width: 25%;">{{ $document->discipline ? $document->getDisciplineLabel() : '—' }}</td>
        <td style="border: 1px solid #ddd; padding: 5px; width: 25%; background: #f8fafc;"><strong>{{ __('collaboration.label.spec_section') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->spec_section ?: '—' }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.ball_court') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->ballInCourt?->name ?: '—' }}</td>
        <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.due') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->due_date?->appDate() ?: '—' }}</td>
    </tr>
</table>

<div style="margin-bottom: 12px;">
    <div style="font-size: 9pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 5px;">
        {{ __('collaboration.label.question') }}
    </div>
    <div style="font-size: 9pt; white-space: pre-line;">{{ $document->question }}</div>
</div>

<div style="margin-bottom: 12px;">
    <div style="font-size: 9pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 5px;">
        {{ __('collaboration.label.answer') }}
    </div>
    @if($document->answer)
        <div style="font-size: 9pt; white-space: pre-line;">{{ $document->answer }}</div>
        <div style="font-size: 7pt; color: #666; margin-top: 4px;">
            {{ __('collaboration.label.answered', [
                'who' => $document->answeredBy?->name ?? __('collaboration.label.removed_user'),
                'when' => $document->answered_at?->format($stampFormat),
            ]) }}
        </div>
    @else
        <div style="font-size: 8pt; color: #999;">{{ __('collaboration.message.answered') }}</div>
        <div style="border-bottom: 1px solid #ccc; height: 20px;"></div>
        <div style="border-bottom: 1px solid #ccc; height: 20px;"></div>
        <div style="border-bottom: 1px solid #ccc; height: 20px;"></div>
    @endif
</div>

@include('pdf.collaboration.partials.distribution')
@include('pdf.collaboration.partials.signatures')
@include('pdf.collaboration.partials.footer')
</body>
</html>
