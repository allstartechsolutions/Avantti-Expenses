<?php

namespace App\Models\Concerns;

use App\Models\SubcontractorDocument;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where a vendor stands on its compliance documents, in one word.
 *
 * `expired` beats `expiring_soon` beats `valid`; `none` means nothing active
 * with a date is on file, which is a different fact from "all current" and
 * is shown as such. Only **active** documents whose type requires a date
 * take part — the same rule the badge, the reminders and the vendor page use.
 *
 * Lists load the three counts in one query through `withDocumentHealth()`;
 * a single record answers from the database when the counts are not loaded.
 * Both paths reach the same answer.
 */
trait HasDocumentHealth
{
    public static function documentHealthStates(): array
    {
        return [
            SubcontractorDocument::EXPIRY_EXPIRED,
            SubcontractorDocument::EXPIRY_EXPIRING_SOON,
            SubcontractorDocument::EXPIRY_VALID,
            'none',
        ];
    }

    /** Three counts on every row, computed by the database, never per record. */
    public function scopeWithDocumentHealth(Builder $query): Builder
    {
        // `withCount` on the plain relation, so the subcontractor flag scope
        // is not applied twice; the document scopes carry their own filters.
        return $query->withCount([
            'documents as expired_documents_count' => fn (Builder $q) => $q->expired(),
            'documents as expiring_documents_count' => fn (Builder $q) => $q->expiringWithin(),
            'documents as tracked_documents_count' => fn (Builder $q) => $q->active()->requiringExpiry(),
        ]);
    }

    /** Only the vendors in one state, judged the same way the badge is. */
    public function scopeDocumentHealth(Builder $query, ?string $state): Builder
    {
        $hasExpired = fn (Builder $q) => $q->whereHas('documents', fn (Builder $d) => $d->expired());
        $hasExpiring = fn (Builder $q) => $q->whereHas('documents', fn (Builder $d) => $d->expiringWithin());
        $hasTracked = fn (Builder $q) => $q->whereHas('documents', fn (Builder $d) => $d->active()->requiringExpiry());

        return match ($state) {
            SubcontractorDocument::EXPIRY_EXPIRED => $hasExpired($query),
            SubcontractorDocument::EXPIRY_EXPIRING_SOON => $hasExpiring($query)
                ->whereDoesntHave('documents', fn (Builder $d) => $d->expired()),
            SubcontractorDocument::EXPIRY_VALID => $hasTracked($query)
                ->whereDoesntHave('documents', fn (Builder $d) => $d->expired())
                ->whereDoesntHave('documents', fn (Builder $d) => $d->expiringWithin()),
            'none' => $query->whereDoesntHave('documents', fn (Builder $d) => $d->active()->requiringExpiry()),
            default => $query,
        };
    }

    public function getDocumentHealthAttribute(): string
    {
        $expired = $this->attributes['expired_documents_count']
            ?? $this->documents()->expired()->count();
        $expiring = $this->attributes['expiring_documents_count']
            ?? $this->documents()->expiringWithin()->count();
        $tracked = $this->attributes['tracked_documents_count']
            ?? $this->documents()->active()->requiringExpiry()->count();

        return match (true) {
            $expired > 0 => SubcontractorDocument::EXPIRY_EXPIRED,
            $expiring > 0 => SubcontractorDocument::EXPIRY_EXPIRING_SOON,
            $tracked > 0 => SubcontractorDocument::EXPIRY_VALID,
            default => 'none',
        };
    }

    public function getDocumentHealthLabelAttribute(): string
    {
        return static::documentHealthLabel($this->document_health);
    }

    public static function documentHealthLabel(?string $state): string
    {
        return match ($state) {
            SubcontractorDocument::EXPIRY_EXPIRED => __('Documents expired'),
            SubcontractorDocument::EXPIRY_EXPIRING_SOON => __('Documents expiring soon'),
            SubcontractorDocument::EXPIRY_VALID => __('Documents current'),
            'none' => __('No dated documents'),
            default => __('All'),
        };
    }
}
