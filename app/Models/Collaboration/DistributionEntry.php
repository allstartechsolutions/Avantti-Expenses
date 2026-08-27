<?php

namespace App\Models\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a document's distribution list.
 *
 * Either a user or a bare name and e-mail. Both are ordinary — the
 * fiscalização often wants the transmittal without wanting a login.
 */
class DistributionEntry extends Model
{
    protected $table = 'collaboration_distribution_entries';

    protected $fillable = [
        'user_id',
        'external_name',
        'external_email',
        'role',
    ];

    public function distributable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whoever this line is, named. */
    public function getName(): string
    {
        return $this->user?->name ?? $this->external_name ?? $this->external_email ?? '';
    }

    /** Where a copy goes. Null when the row names nobody reachable. */
    public function getEmail(): ?string
    {
        return $this->user?->email ?? $this->external_email;
    }

    /** Their part on this document — projetista, fiscalização — translated. */
    public function getRoleLabel(): ?string
    {
        return $this->role ? static::roleLabel($this->role) : null;
    }

    /**
     * Labels for the roles a distribution list uses.
     *
     * Static twin so a filter value can be labelled without a row, per the
     * pt_BR rules in CLAUDE.md. These are Portuguese words in both locales —
     * "projetista" has no single English equivalent and the BR market is the
     * one that uses the list — so the keys are explicit rather than guessed.
     */
    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            'projetista' => __('collaboration.role.projetista'),
            'fornecedor' => __('collaboration.role.fornecedor'),
            'fiscalizacao' => __('collaboration.role.fiscalizacao'),
            'cliente' => __('collaboration.role.cliente'),
            'interno' => __('collaboration.role.interno'),
            default => (string) $role,
        };
    }

    /** @return array<string, string> value => label, for a select. */
    public static function roleOptions(): array
    {
        return collect(['projetista', 'fornecedor', 'fiscalizacao', 'cliente', 'interno'])
            ->mapWithKeys(fn (string $role) => [$role => static::roleLabel($role)])
            ->all();
    }
}
