<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'street',
        'address_2',
        'city',
        'state',
        'postal_code',
        'neighborhood',
        'country',
        'phone',
        'email',
        'description',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this supplier
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
        if ($this->country === 'BR') {
            $addressParts = array_filter([
                $this->street,
                $this->address_2,
                $this->neighborhood,
                $this->city,
                $this->state,
                $this->postal_code,
            ]);
        } else {
            $addressParts = array_filter([
                $this->street,
                $this->address_2,
                $this->city,
                $this->state,
                $this->postal_code,
            ]);
        }

        return implode(', ', $addressParts);
    }
}
