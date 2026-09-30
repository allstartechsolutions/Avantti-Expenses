<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The on/off switch every flavour of vendor shares (Vendor, Supplier,
 * Subcontractor all read the same `vendors` row). An inactive vendor is
 * kept, shown and filterable, but is not offered by the pickers that
 * start a new record — see `scopeActive()` and the list of pickers in
 * docs/vendor-unification.md.
 *
 * The models using this trait must cast `is_active` to boolean and
 * `deactivated_at` to datetime themselves — Eloquent does not merge a
 * trait's casts.
 */
trait HasVendorActiveState
{
    /** Vendors that may be picked for a new record. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('vendors.is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('vendors.is_active', false);
    }

    /**
     * Vendors that may be picked for a new record, plus the one a record
     * already points at — an edit form must keep offering the vendor it was
     * saved with, even after that vendor was switched off.
     */
    public function scopeActiveOrCurrent(Builder $query, int|string|null $currentId): Builder
    {
        $currentId = (int) $currentId;

        return $query->where(function (Builder $q) use ($currentId) {
            $q->where('vendors.is_active', true);

            if ($currentId > 0) {
                $q->orWhere('vendors.id', $currentId);
            }
        });
    }

    /** The '' | active | inactive filter every vendor list offers. */
    public function scopeActiveState(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'active' => $query->active(),
            'inactive' => $query->inactive(),
            default => $query,
        };
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'deactivated_by');
    }

    public function activate(): void
    {
        $this->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ])->save();
    }

    public function deactivate(?int $userId = null): void
    {
        $this->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $userId ?? auth()->id(),
        ])->save();
    }

    public function getActiveLabel(): string
    {
        return static::activeLabel($this->is_active);
    }

    public static function activeLabel(?bool $active): string
    {
        // A vendor is a company: masculine in pt_BR (*fornecedor ativo*).
        return $active ? __('Active') : __('Inactive');
    }

    /** The filter's options, keyed by the value the query string carries. */
    public static function activeStates(): array
    {
        return [
            'active' => __('Active'),
            'inactive' => __('Inactive'),
        ];
    }
}
