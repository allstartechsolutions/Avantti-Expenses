<div style="margin-top: 18px; border-top: 1px solid #ddd; padding-top: 5px; font-size: 7pt; color: #888;">
    {{ __('collaboration.label.raised_2', [
        'who' => $document->createdBy?->name ?? __('collaboration.label.removed_user'),
        'when' => $document->created_at?->format(config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'),
    ]) }}
    · {{ __('collaboration.label.printed', ['when' => now()->format(config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A')]) }}
</div>
