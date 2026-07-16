<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFormattedPhone, HasFactory;

    protected $fillable = [
        'company_name',
        'contact_name',
        'title',
        'street',
        'city',
        'state',
        'postal_code',
        'phone',
        'email',
        'website',
        'cardpointe_profile_id',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this client
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the projects for this client
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the saved payment methods for this client
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(ClientPaymentMethod::class);
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
     * Get client initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->company_name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($this->company_name, 0, 2));
    }
}
