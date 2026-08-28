<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bundle of approvals submitted together — the US submittal package. */
class ApprovalPackage extends Model
{
    protected $fillable = ['project_id', 'number', 'title', 'status', 'created_by_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public const OPEN = 'open';
    public const CLOSED = 'closed';

    /**
     * Create one with the next number for its project, retrying if another
     * request took that number first.
     *
     * Numbered per project rather than per install: a package is a submission
     * to one architect on one job, and "PKG-0004 on Obra Central" is how it is
     * referred to in a meeting.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithNumber(array $attributes): self
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return static::create($attributes + [
                    'number' => static::nextNumber((int) $attributes['project_id']),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($attempt === 5) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a package number.');
    }

    public static function nextNumber(int $projectId): string
    {
        // Numeric max, not string max: 'PKG-9999' > 'PKG-10000' lexically.
        $highest = (int) static::query()
            ->where('project_id', $projectId)
            ->selectRaw('MAX(CAST(SUBSTRING(number, 5) AS UNSIGNED)) AS max_number')
            ->value('max_number');

        return 'PKG-'.str_pad($highest + 1, 4, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** Never print the stored status. */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::OPEN => __('collaboration.package.status.open'),
            self::CLOSED => __('collaboration.package.status.closed'),
            default => (string) $status,
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }
}
