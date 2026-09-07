<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubcontractorEmployee extends Model
{
    use HasFormattedPhone;

    protected $fillable = [
        'subcontractor_id',
        'worker_id',
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
        // Every row is a worker from the start. Linking later merges two
        // workers; nothing ever has to be created by hand.
        static::created(function (SubcontractorEmployee $employee) {
            if ($employee->worker_id === null) {
                $worker = Worker::create([
                    'name' => $employee->name,
                    'created_by' => Auth::id(),
                ]);

                $employee->forceFill(['worker_id' => $worker->id])->saveQuietly();
                $employee->setRelation('worker', $worker);
            }
        });

        // A worker whose last row is deleted describes nobody.
        static::deleted(function (SubcontractorEmployee $employee) {
            $employee->worker?->deleteIfEmpty();
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

    /** The human this row describes. Always set once the row has been saved. */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /** Known at another company too — the worker has more than this one row. */
    public function isLinked(): bool
    {
        return $this->siblings()->isNotEmpty();
    }

    /** Still at this company: no end date, or one still ahead. */
    public function isCurrent(): bool
    {
        return $this->ended_at === null || $this->ended_at->isFuture() || $this->ended_at->isToday();
    }

    /**
     * The same worker's rows at other companies, for the "also at" line.
     * Needs `worker.employees.subcontractor` loaded to cost nothing.
     *
     * @return Collection<int, SubcontractorEmployee>
     */
    public function siblings(): Collection
    {
        if (! $this->worker) {
            return collect();
        }

        return $this->worker->employees
            ->reject(fn (SubcontractorEmployee $row) => $row->id === $this->id)
            ->sortBy(fn (SubcontractorEmployee $row) => $row->subcontractor?->company_name ?? '')
            ->values();
    }

    /**
     * Declare this row and another one the same worker. This row's worker
     * survives and takes the other's rows; the other worker is deleted. The
     * caller has already checked the two rows sit at different companies.
     */
    public function linkWith(SubcontractorEmployee $other, User $user, ?string $reason = null): Worker
    {
        return DB::transaction(function () use ($other, $user, $reason) {
            $worker = $this->worker ?? Worker::create(['name' => $this->name, 'created_by' => $user->id]);

            if ($other->worker && $other->worker->isNot($worker)) {
                $worker->absorb($other->worker);
                $other->unsetRelation('worker');
            }

            foreach ([$this, $other] as $row) {
                $row->forceFill([
                    'worker_id' => $worker->id,
                    'linked_by' => $user->id,
                    'linked_at' => now(),
                    'link_reason' => $reason ?: $row->link_reason,
                ])->save();
                $row->setRelation('worker', $worker);
            }

            $worker->unsetRelation('employees');

            return $worker;
        });
    }

    /**
     * Take this row off a worker known elsewhere: it becomes a worker of its
     * own again. A row that is its worker's only row has nothing to unlink.
     */
    public function unlink(): bool
    {
        return DB::transaction(function () {
            $worker = $this->worker;

            if (! $worker || $worker->employees()->where('id', '!=', $this->id)->doesntExist()) {
                return false;
            }

            $own = Worker::create(['name' => $this->name, 'created_by' => Auth::id()]);

            $this->forceFill([
                'worker_id' => $own->id,
                'linked_by' => null,
                'linked_at' => null,
                'link_reason' => null,
            ])->save();

            $this->setRelation('worker', $own);
            $worker->unsetRelation('employees');

            return true;
        });
    }

    /**
     * Employee rows at other companies that look like the same worker as the
     * details given: same tax id, same phone, same e-mail, or the same name.
     * Each match says what matched so whoever decides can weigh it —
     * a shared tax id is near-certain, a shared name is a hint.
     *
     * Rows already on the worker `$excludeWorkerId` are left out: they are
     * linked, there is nothing to suggest.
     *
     * @return Collection<int, array{employee: SubcontractorEmployee, reasons: array<int, string>}>
     */
    public static function lookalikes(
        array $details,
        ?int $excludeSubcontractorId = null,
        ?int $excludeWorkerId = null,
    ): Collection {
        $name = Worker::normalizeName($details['name'] ?? null);
        $phone = Worker::normalizePhone($details['phone'] ?? null);
        $email = Worker::normalizeEmail($details['email'] ?? null);
        $taxId = Worker::normalizeTaxId($details['tax_id'] ?? null);

        if ($name === '' && $phone === '' && $email === '' && $taxId === '') {
            return collect();
        }

        $query = static::query()->with(['subcontractor', 'worker.employees.subcontractor']);

        if ($excludeSubcontractorId) {
            $query->where('subcontractor_id', '!=', $excludeSubcontractorId);
        }

        if ($excludeWorkerId) {
            $query->where(fn (Builder $q) => $q->whereNull('worker_id')->orWhere('worker_id', '!=', $excludeWorkerId));
        }

        return $query->get()
            ->map(function (SubcontractorEmployee $row) use ($name, $phone, $email, $taxId) {
                $reasons = [];

                if ($taxId !== '' && Worker::normalizeTaxId($row->tax_id) === $taxId) {
                    $reasons[] = 'tax_id';
                }
                if ($phone !== '' && Worker::normalizePhone($row->phone) === $phone) {
                    $reasons[] = 'phone';
                }
                if ($email !== '' && Worker::normalizeEmail($row->email) === $email) {
                    $reasons[] = 'email';
                }
                if ($name !== '' && Worker::normalizeName($row->name) === $name) {
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
