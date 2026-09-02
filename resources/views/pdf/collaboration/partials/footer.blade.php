<div style="margin-top: 18px; border-top: 1px solid #ddd; padding-top: 5px; font-size: 7pt; color: #888;">
    {{ __('collaboration.label.raised_2', [
        'who' => $document->createdBy?->name ?? __('collaboration.label.removed_user'),
        'when' => $document->created_at?->appDate(),
    ]) }}
    · {{ __('collaboration.label.printed', ['when' => now()->appDateTime()]) }}
</div>
