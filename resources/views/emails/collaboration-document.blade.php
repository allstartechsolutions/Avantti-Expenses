@php
    $isRfi = $document instanceof \App\Models\Rfi;
@endphp

<p>
    {{ $isRfi
        ? __('collaboration.message.rfi_been_sent', ['project' => $document->project->project_name])
        : __('collaboration.help.approval_been_sent', ['project' => $document->project->project_name]) }}
</p>

<table cellpadding="4" style="border-collapse: collapse;">
    <tr>
        <td><strong>{{ __('Number') }}</strong></td>
        <td>{{ $document->number }}</td>
    </tr>
    <tr>
        <td><strong>{{ $isRfi ? __('Subject') : __('Title') }}</strong></td>
        <td>{{ $isRfi ? $document->subject : $document->title }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('Status') }}</strong></td>
        <td>{{ $document->getStatusLabel() }}</td>
    </tr>
    @if($document->due_date)
        <tr>
            <td><strong>{{ __('Due') }}</strong></td>
            <td>{{ $document->due_date->appDate() }}</td>
        </tr>
    @endif
</table>

@if($note)
    <p>{{ $note }}</p>
@endif

<p>{{ __('collaboration.message.document_attached') }}</p>
