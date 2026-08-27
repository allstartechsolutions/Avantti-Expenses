{{--
    Shared masthead. `$heading` is the document's own word — SOLICITAÇÃO DE
    INFORMAÇÃO, REQUEST FOR INFORMATION — and `$badge` is the state stamp, so a
    printed draft can never be mistaken for the record.
--}}
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
                    @if($company->phone) | {{ $company->phone }}@endif
                </div>
            @endif

            {{-- The empreendimento: which job this sheet belongs to. --}}
            <div style="margin-top: 6px; font-size: 8pt;">
                <strong>{{ $document->project->project_name }}</strong>
                @if($document->jobSite)
                    <span style="color: #666;"> — {{ $document->jobSite->job_site_name }}</span>
                @endif
            </div>
        </td>

        <td style="width: 45%; vertical-align: top; text-align: right; border: none; padding: 0;">
            <div style="font-size: 13pt; font-weight: bold; color: #3F5189;">{{ $heading }}</div>
            <div style="font-size: 11pt; font-weight: bold; margin-top: 2px;">{{ $document->number }}</div>
            <div style="font-size: 8pt; color: #666; margin-top: 2px;">{{ $document->getStatusLabel() }}</div>
            @if(! empty($badge))
                <div style="font-size: 8pt; color: #b45309; font-weight: bold; margin-top: 3px;">{{ $badge }}</div>
            @endif
        </td>
    </tr>
</table>
