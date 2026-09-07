<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubcontractorEmployee extends Model
{
    use HasFormattedPhone;

    protected $fillable = [
        'subcontractor_id',
        'person_id',
        'title',
        'name',
        'phone',
        'email',
        'tax_id',
        'started_at',
        'ended_at',
        'notes',
        'linked_by',
        'linked_at',
        'link_reason',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
        'linked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Deleting a row that was one half of a link leaves a person tying
        // one row together, which is nothing; let the record go with it.
        static::deleted(function (SubcontractorEmployee $employee) {
            $employee->person?->dissolveIfLonely();
        });
    }

    /**
     * Get the subcontractor that owns this employee
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    /**
     * Get the contracts linked to this employee
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** The human this row describes, once somebody has linked it to another row. */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function isLinked(): bool
    {
        return $this->person_id !== null;
    }

    /** Still at this company: no end date, or one still ahead. */
    public function isCurrent(): bool
    {
        return $this->ended_at === null || $this->ended_at->isFuture() || $this->ended_at->isToday();
    }

    /**
     * The other companies this person is known at, for the "also at" line.
     * Needs `person.employees.subcontractor` loaded to cost nothing.
     *
     * @return Collection<int, SubcontractorEmployee>
     */
    public function siblings(): Collection
    {
        if (! $this->person) {
            return collect();
        }

        return $this->person->employees
            ->reject(fn (SubcontractorEmployee $row) => $row->id === $this->id)
            ->sortBy(fn (SubcontractorEmployee $row) => $row->subcontractor?->company_name ?? '')
            ->values();
    }

    /**
     * Declare this row and another one the same person. Whichever of the two
     * already belongs to a person wins; when both do, the two people are
     * folded into one; when neither does, the person is created here. The
     * caller has already checked the two rows sit at different companies.
     */
    public function linkWith(SubcontractorEmployee $other, User $user, ?string $reason = null): Person
    {
        return DB::transaction(function () use ($other, $user, $reason) {
            $person = $this->person ?? $other->person;

            if (! $person) {
                $person = Person::create([
                    'name' => $this->name,
                    'created_by' => $user->id,
                ]);
            } elseif ($this->person && $other->person && $this->person->isNot($other->person)) {
                $person->absorb($other->person);
                $other->unsetRelation('person');
            }

            foreach ([$this, $other] as $row) {
                if ($row->person_id !== $person->id || ! $row->linked_at) {
                    $row->forceFill([
                        'person_id' => $person->id,
                        'linked_by' => $user->id,
                        'linked_at' => now(),
                        'link_reason' => $reason ?: $row->link_reason,
                    ])->save();
                }
            }

            $this->setRelation('person', $person);
            $other->setRelation('person', $person);

            return $person;
        });
    }

    /** Take this row back off its person; the person dissolves if that leaves it alone. */
    public function unlink(): void
    {
        DB::transaction(function () {
            $person = $this->person;

            $this->forceFill([
                'person_id' => null,
                'linked_by' => null,
                'linked_at' => null,
                'link_reason' => null,
            ])->save();

            $this->unsetRelation('person');

            $person?->dissolveIfLonely();
        });
    }

    /**
     * Employee rows at other companies that look like the same person as the
     * details given: same tax id, same phone, same e-mail, or the same name.
     * Each match says what matched so the person deciding can weigh it —
     * a shared tax id is near-certain, a shared name is a hint.
     *
     * Rows already on the same person as `$excludePersonId` are left out:
     * they are linked, there is nothing to suggest.
     *
     * @return Collection<int, array{employee: SubcontractorEmployee, reasons: array<int, string>}>
     */
    public static function lookalikes(
        array $details,
        ?int $excludeSubcontractorId = null,
        ?int $excludePersonId = null,
    ): Collection {
        $name = Person::normalizeName($details['name'] ?? null);
        $phone = Person::normalizePhone($details['phone'] ?? null);
        $email = Person::normalizeEmail($details['email'] ?? null);
        $taxId = Person::normalizeTaxId($details['tax_id'] ?? null);

        if ($name === '' && $phone === '' && $email === '' && $taxId === '') {
            return collect();
        }

        $query = static::query()->with(['subcontractor', 'person.employees.subcontractor']);

        if ($excludeSubcontractorId) {
            $query->where('subcontractor_id', '!=', $excludeSubcontractorId);
        }

        if ($excludePersonId) {
            $query->where(fn (Builder $q) => $q->whereNull('person_id')->orWhere('person_id', '!=', $excludePersonId));
        }

        return $query->get()
            ->map(function (SubcontractorEmployee $row) use ($name, $phone, $email, $taxId) {
                $reasons = [];

                if ($taxId !== '' && Person::normalizeTaxId($row->tax_id) === $taxId) {
                    $reasons[] = 'tax_id';
                }
                if ($phone !== '' && Person::normalizePhone($row->phone) === $phone) {
                    $reasons[] = 'phone';
                }
                if ($email !== '' && Person::normalizeEmail($row->email) === $email) {
                    $reasons[] = 'email';
                }
                if ($name !== '' && Person::normalizeName($row->name) === $name) {
                    $reasons[] = 'name';
                }

                return $reasons ? ['employee' => $row, 'reasons' => $reasons] : null;
            })
            ->filter()
            ->sortBy(fn ($match) => [-count($match['reasons']), $match['employee']->name])
            ->values();
    }

    /** The label for one of the reasons `lookalikes()` gives. */
    public static function matchReasonLabel(string $reason): string
    {
        return match ($reason) {
            'tax_id' => __('Same tax id'),
            'phone' => __('Same phone'),
            'email' => __('Same e-mail'),
            'name' => __('Same name'),
            default => $reason,
        };
    }
}
