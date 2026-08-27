<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The facts a laudo or certificate carries beyond the approval itself.
 */
class ApprovalCertificateDetail extends Model
{
    protected $fillable = [
        'approval_id',
        'issuing_body',
        'certificate_number',
        'issued_at',
        'valid_until',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    /** Expired already. */
    public function hasExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /**
     * Expiring soon enough to matter.
     *
     * A certificate that lapses between approval and delivery is a problem
     * somebody needs to see coming, so the screen warns before it bites.
     */
    public function expiresWithin(int $days = 60): bool
    {
        return $this->valid_until !== null
            && ! $this->hasExpired()
            && $this->valid_until->isBefore(now()->addDays($days));
    }
}
