<?php

namespace App\Models\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Somebody's name against a document, and the evidence it is still that
 * document.
 *
 * `payload_hash` is the whole point. Without it a signature row records that a
 * button was pressed; with it, "is this the document that was signed?" has an
 * answer. `HasSignatures::signatureIsIntact()` asks it.
 */
class Signature extends Model
{
    protected $table = 'collaboration_signatures';

    protected $fillable = [
        'user_id',
        'signer_name',
        'signer_document',
        'art_number',
        'method',
        'signed_at',
        'ip_address',
        'payload_hash',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public const METHOD_DRAWN = 'drawn';
    public const METHOD_GOV_BR = 'gov_br';
    public const METHOD_ICP_BRASIL = 'icp_brasil';

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Never print the stored value: 'drawn' is not a word for a screen. */
    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            self::METHOD_DRAWN => __('collaboration.signature.method.drawn'),
            self::METHOD_GOV_BR => __('collaboration.signature.method.gov_br'),
            self::METHOD_ICP_BRASIL => __('collaboration.signature.method.icp_brasil'),
            default => (string) $method,
        };
    }

    public function getMethodLabel(): string
    {
        return static::methodLabel($this->method);
    }

    /**
     * "João Silva — CREA 12345-D", the line that goes on the PDF.
     *
     * A signature block in Brazil without the responsible professional's
     * registration is not worth much, so the registration is shown whenever
     * there is one.
     */
    public function getSignerLine(): string
    {
        return $this->signer_document
            ? $this->signer_name.' — '.$this->signer_document
            : $this->signer_name;
    }
}
