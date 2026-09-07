<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One human being, as distinct from the employee rows that describe them at
 * each subcontractor. Every employee row has a worker from the moment it is
 * created; linking two rows at different companies merges their workers
 * into one, and unlinking a row gives it a worker of its own again. Each
 * employee row keeps the name, tax id and contact details the worker
 * presented at that company — the worker carries only what is true of the
 * human regardless of the company.
 *
 * See docs/workers-module.md.
 */
class Worker extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'created_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Every employee row this worker is known by, one per company. */
    public function employees(): HasMany
    {
        return $this->hasMany(SubcontractorEmployee::class);
    }

    /** Every contract any of this worker's employee rows was the contact on. */
    public function contracts(): HasManyThrough
    {
        return $this->hasManyThrough(
            Contract::class,
            SubcontractorEmployee::class,
            'worker_id',
            'subcontractor_employee_id',
        );
    }

    /** Known at more than one company. Needs `employees` loaded to cost nothing. */
    public function isAtSeveralCompanies(): bool
    {
        return $this->employees->pluck('subcontractor_id')->unique()->count() > 1;
    }

    /** Still at at least one company. Needs `employees` loaded to cost nothing. */
    public function isCurrent(): bool
    {
        return $this->employees->contains(fn (SubcontractorEmployee $row) => $row->isCurrent());
    }

    /**
     * The distinct tax ids this worker has presented, normalised so that
     * "123.456.789-09" and "12345678909" count as one. Two or more is the
     * compliance fact the page exists to show.
     *
     * @return Collection<int, string>
     */
    public function distinctTaxIds(): Collection
    {
        return $this->employees
            ->pluck('tax_id')
            ->filter()
            ->unique(fn ($taxId) => static::normalizeTaxId($taxId))
            ->values();
    }

    /**
     * A tax id reduced to what identifies it: letters and digits, upper case.
     * Empty when nothing is left, so blanks never match each other.
     */
    public static function normalizeTaxId(?string $taxId): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]/i', '', (string) $taxId));
    }

    /** A phone reduced to its digits, for matching only. */
    public static function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    /** An e-mail as a matching key: trimmed, lower case. */
    public static function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /** A name as a matching key: lower case, accents stripped, letters and digits only. */
    public static function normalizeName(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii((string) $name)));
    }

    /**
     * Fold another worker into this one: every employee row moves across,
     * the other record is deleted. This is what linking two rows does —
     * somebody has said the two workers are one.
     */
    public function absorb(Worker $other): void
    {
        if ($other->id === $this->id) {
            return;
        }

        $other->employees()->update(['worker_id' => $this->id]);

        if (! $this->notes && $other->notes) {
            $this->notes = $other->notes;
            $this->save();
        }

        $other->delete();
        $this->unsetRelation('employees');
    }

    /** A worker with no employee row left describes nobody. */
    public function deleteIfEmpty(): bool
    {
        if ($this->employees()->exists()) {
            return false;
        }

        $this->delete();

        return true;
    }
}
