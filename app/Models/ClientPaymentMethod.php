<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPaymentMethod extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'client_id',
        'cardpointe_profile_id',
        'acctid',
        'card_last_four',
        'card_brand',
        'card_name',
        'expiry',
        'token',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplayName(): string
    {
        $brand = $this->card_brand ? ucfirst($this->card_brand) : 'Card';
        $display = "{$brand} ending in {$this->card_last_four}";

        if ($this->expiry) {
            $display .= " (exp {$this->getExpiryFormatted()})";
        }

        return $display;
    }

    public function getExpiryFormatted(): string
    {
        if (!$this->expiry || strlen($this->expiry) !== 4) {
            return '';
        }

        return substr($this->expiry, 0, 2) . '/' . substr($this->expiry, 2);
    }
}
