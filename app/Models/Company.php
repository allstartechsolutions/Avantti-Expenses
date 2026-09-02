<?php

namespace App\Models;

use App\Services\Branding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The branding cache holds this row, so any write to it — a new icon, a new
     * name, a logo removed — has to drop the cache or the header keeps showing
     * yesterday's mark until the cache is cleared by hand.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Branding::forget());
        static::deleted(fn () => Branding::forget());
    }

    /**
     * Get the user who created this company
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the full address as a formatted string
     */
    public function getFullAddressAttribute(): string
    {
        $addressParts = array_filter([
            $this->street,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);

        return implode(', ', $addressParts);
    }

    /**
     * Get the app icon URL (the square mark on screen), if one was uploaded
     */
    public function getAppIconUrlAttribute(): ?string
    {
        return $this->app_icon ? Storage::disk('public')->url($this->app_icon) : null;
    }

    /**
     * Get the dark-mode app icon URL, if one was uploaded
     */
    public function getAppIconDarkUrlAttribute(): ?string
    {
        return $this->app_icon_dark ? Storage::disk('public')->url($this->app_icon_dark) : null;
    }

    /**
     * Get the favicon URL, if one was uploaded
     */
    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon ? Storage::disk('public')->url($this->favicon) : null;
    }

    /**
     * Get the logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
