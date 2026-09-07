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
 * each subcontractor. A person never exists on their own: the record is born
 * the first time somebody links two employee rows, and it dissolves when
 * fewer than two rows are left on it. Each employee row keeps the name, tax
 * id and contact details the person presented at that company — the person
 * only carries what is true of the human regardless of the company.
 *
 * See docs/people-module.md.
 */
class Person extends Model
{
    protected $table = 'people';

    protected $fillable = [
        'name',
        'notes',
        'created_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Every employee row this person is known by, one per company. */
    public function employees(): HasMany
    {
        return $this->hasMany(SubcontractorEmployee::class);
    }

    /** Every contract any of this person's employee rows was the contact on. */
    public function contracts(): HasManyThrough
    {
        return $this->hasManyThrough(
            Contract::class,
            SubcontractorEmployee::class,
            'person_id',
            'subcontractor_employee_id',
        );
    }

    /**
     * The distinct tax ids this person has presented, normalised so that
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
     * Drop the record when it no longer ties anything together. A person
     * with one employee row left is just an employee again.
     */
    public function dissolveIfLonely(): bool
    {
        if ($this->employees()->count() >= 2) {
            return false;
        }

        $this->employees()->update([
            'person_id' => null,
            'linked_by' => null,
            'linked_at' => null,
            'link_reason' => null,
        ]);

        $this->delete();

        return true;
    }

    /**
     * Fold another person into this one: every employee row moves across,
     * the other record is deleted. Used when somebody links two rows that
     * already belong to two different people — they have just said the two
     * people are one.
     */
    public function absorb(Person $other): void
    {
        if ($other->id === $this->id) {
            return;
        }

        $other->employees()->update(['person_id' => $this->id]);

        if (! $this->notes && $other->notes) {
            $this->notes = $other->notes;
            $this->save();
        }

        $other->delete();
    }
}
