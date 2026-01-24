<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'project_name',
        'street',
        'address_2',
        'city',
        'state',
        'postal_code',
        'neighborhood',
        'country',
        'latitude',
        'longitude',
        'contact_person',
        'phone',
        'email',
        'initial_amount',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get/Set initial_amount as dollars (stored as cents)
     */
    protected function initialAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the client that owns this project
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created this project
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the job sites for this project
     */
    public function jobSites(): HasMany
    {
        return $this->hasMany(JobSite::class);
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
