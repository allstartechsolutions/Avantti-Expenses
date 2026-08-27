@if($document->distribution->isNotEmpty())
    <div style="margin-top: 14px; page-break-inside: avoid;">
        <div style="font-size: 9pt; font-weight: bold; color: #3F5189; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 5px;">
            {{ __('Distribution') }}
        </div>
        <div style="font-size: 8pt; color: #444;">
            @foreach($document->distribution as $entry)
                {{ $entry->getName() }}@if($entry->getRoleLabel()) ({{ $entry->getRoleLabel() }})@endif{{ ! $loop->last ? ' · ' : '' }}
            @endforeach
        </div>
    </div>
@endif
