<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $document->number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.45; color: #333; margin: 0; padding: 18px;">
@include('pdf.collaboration.partials.header', [
    'heading' => __('collaboration.pdf.approval'),
    'badge' => $document->isDraft() ? __('collaboration.pdf.draft_issued') : null,
])

@include('pdf.collaboration.partials.approval-body', ['showSpec' => false])

@include('pdf.collaboration.partials.distribution')
@include('pdf.collaboration.partials.signatures')
@include('pdf.collaboration.partials.footer')
</body>
</html>
