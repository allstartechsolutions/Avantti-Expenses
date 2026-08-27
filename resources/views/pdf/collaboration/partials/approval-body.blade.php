{{--
    The body both approval sheets share: what is being submitted, and every
    round it has been through.

    `$showSpec` is on for the US sheet, which cites the specification section;
    `$showCertificate` prints the laudo's own facts, which is the BR case that
    matters most.
--}}
@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    $stampFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';
    $showSpec = $showSpec ?? false;
@endphp

<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8pt;">
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px; width: 25%; background: #f8fafc;"><strong>{{ __('Title') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;" colspan="3">{{ $document->title }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('Type') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px; width: 25%;">{{ $document->getTypeLabel() }}</td>
        <td style="border: 1px solid #ddd; padding: 5px; width: 25%; background: #f8fafc;"><strong>{{ __('collaboration.message.rev') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->current_revision }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('Supplier') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->supplier?->name ?: '—' }}</td>
        <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.due') }}</strong></td>
        <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->due_date?->format($dateFormat) ?: '—' }}</td>
    </tr>
    @if($showSpec)
        <tr>
            <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.spec_section') }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 5px;" colspan="3">{{ $document->spec_section ?: '—' }}</td>
        </tr>
    @endif
</table>

@if($document->description)
    <div style="margin-bottom: 12px;">
        <div style="font-size: 9pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 5px;">
            {{ __('collaboration.label.what_being_submitted') }}
        </div>
        <div style="font-size: 9pt; white-space: pre-line;">{{ $document->description }}</div>
    </div>
@endif

{{-- The laudo's own facts. Validity is the one that bites in practice. --}}
@if($document->type === \App\Models\Approval::TYPE_CERTIFICATE && $document->certificate)
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8pt;">
        <tr>
            <td style="border: 1px solid #ddd; padding: 5px; width: 25%; background: #f8fafc;"><strong>{{ __('collaboration.label.issued') }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 5px; width: 25%;">{{ $document->certificate->issuing_body }}</td>
            <td style="border: 1px solid #ddd; padding: 5px; width: 25%; background: #f8fafc;"><strong>{{ __('collaboration.label.certificate_number') }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->certificate->certificate_number ?: '—' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.issued_2') }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 5px;">{{ $document->certificate->issued_at?->format($dateFormat) ?: '—' }}</td>
            <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc;"><strong>{{ __('collaboration.label.valid_until') }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 5px; {{ $document->certificate->hasExpired() ? 'color: #b91c1c; font-weight: bold;' : '' }}">
                {{ $document->certificate->valid_until?->format($dateFormat) ?: '—' }}
                @if($document->certificate->hasExpired()) — {{ __('collaboration.label.certificate_expired') }}@endif
            </td>
        </tr>
    </table>
@endif

{{-- Every round. A rejection belongs to the submission that was rejected, and
     the sheet has to be able to show that. --}}
<div style="margin-bottom: 12px;">
    <div style="font-size: 9pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 5px;">
        {{ __('collaboration.label.revisions') }}
    </div>

    @if($document->revisions->isEmpty())
        <div style="font-size: 8pt; color: #999;">{{ __('collaboration.message.submitted') }}</div>
    @else
        @foreach($document->revisions as $revision)
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8pt; page-break-inside: avoid;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 5px; background: #f8fafc; width: 18%;">
                        <strong>{{ __('collaboration.label.revision', ['revision' => $revision->revision]) }}</strong>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 5px;">
                        <div>
                            {{ __('collaboration.label.submitted', [
                                'who' => $revision->submittedBy?->name ?? __('collaboration.label.removed_user'),
                                'when' => $revision->submitted_at?->format($stampFormat),
                            ]) }}
                        </div>

                        <div style="margin-top: 3px; color: #444;">
                            <strong>{{ __('collaboration.label.reviewers') }}:</strong>
                            @foreach($revision->reviewers as $reviewer)
                                {{ $reviewer->sequence }}. {{ $reviewer->user?->name ?? __('collaboration.label.removed_user') }}@if($reviewer->user?->company_name) ({{ $reviewer->user->company_name }})@endif{{ ! $loop->last ? ' · ' : '' }}
                            @endforeach
                        </div>

                        @if($revision->responseCode)
                            <div style="margin-top: 4px; font-weight: bold;">
                                {{ $revision->responseCode->getLabel() }}
                                <span style="font-weight: normal; color: #666;">
                                    — {{ $revision->respondedBy?->name ?? __('collaboration.label.removed_user') }},
                                    {{ $revision->responded_at?->format($stampFormat) }}
                                </span>
                            </div>
                        @else
                            <div style="margin-top: 4px; color: #b45309;">{{ __('collaboration.label.out_review') }}</div>
                        @endif

                        @if($revision->comments)
                            <div style="margin-top: 3px; white-space: pre-line; color: #444;">{{ $revision->comments }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        @endforeach
    @endif
</div>
