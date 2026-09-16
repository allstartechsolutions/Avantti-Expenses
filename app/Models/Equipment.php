<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * A piece of equipment the company owns, leases or rents — a vehicle, a
 * machine, a tool. Not a catalog item: the catalog is what is bought, this is
 * what is kept, maintained and sent to a site. docs/equipment-module.md
 *
 * A company record: it answers to the `equipment` area alone, and being
 * assigned to a project never makes that project its permission scope
 * (`permissionScope()` says so).
 */
class Equipment extends Model
{
    protected $table = 'equipment';

    public const TYPES = ['vehicle', 'machinery', 'tool', 'other'];

    public const OWNERSHIPS = ['owned', 'leased', 'rented'];

    public const METER_TYPES = ['none', 'km', 'mi', 'hours'];

    public const STATUSES = ['active', 'in_maintenance', 'retired', 'sold'];

    protected $fillable = [
        'name', 'asset_tag', 'equipment_type', 'make', 'model', 'year', 'serial_number', 'plate', 'vin',
        'ownership', 'supplier_id', 'purchase_date', 'purchase_cost', 'warranty_until',
        'meter_type', 'current_meter', 'current_meter_at',
        'status', 'retired_at',
        'project_id', 'job_site_id', 'responsible_user_id', 'assigned_at',
        'photo_path', 'notes', 'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'current_meter' => 'decimal:1',
        'current_meter_at' => 'date',
        'retired_at' => 'date',
        'assigned_at' => 'date',
    ];

    /** Stored in cents, read in the app's currency (docs/monetary-storage.md). */
    protected function purchaseCost(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : round($value / 100, 2),
            set: fn ($value) => $value === null || $value === '' ? null : round($value * 100),
        );
    }

    /**
     * A company record has no project scope, whatever it is assigned to.
     * PermissionResolver::scopeParentOf() asks this before it looks at
     * `project_id`, which would otherwise make the assigned project decide.
     */
    public function permissionScope(): mixed
    {
        return null;
    }

    /*
    |---------------------------------------------------------------------------
    | Relationships
    |---------------------------------------------------------------------------
    */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(EquipmentMeterReading::class)->orderByDesc('read_at')->orderByDesc('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EquipmentAssignment::class)->orderByDesc('started_at')->orderByDesc('id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(EquipmentMaintenancePlan::class)->orderBy('title');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(EquipmentFinding::class)->latest();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EquipmentHistory::class)->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /** Every expense tagged to this equipment, project-level or the company's. */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /*
    |---------------------------------------------------------------------------
    | Labels — never print a stored enum
    |---------------------------------------------------------------------------
    */

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'vehicle' => __('Equipment type: vehicle'),
            'machinery' => __('Equipment type: machinery'),
            'tool' => __('Equipment type: tool'),
            'other' => __('Equipment type: other'),
            default => ucfirst((string) $type),
        };
    }

    public function getTypeLabel(): string
    {
        return static::typeLabel($this->equipment_type);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => __('Equipment status: active'),
            'in_maintenance' => __('Equipment status: in maintenance'),
            'retired' => __('Equipment status: retired'),
            'sold' => __('Equipment status: sold'),
            default => ucfirst((string) $status),
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public static function ownershipLabel(?string $ownership): string
    {
        return match ($ownership) {
            'owned' => __('Ownership: owned'),
            'leased' => __('Ownership: leased'),
            'rented' => __('Ownership: rented'),
            default => ucfirst((string) $ownership),
        };
    }

    public function getOwnershipLabel(): string
    {
        return static::ownershipLabel($this->ownership);
    }

    /** "Odometer" or "Hour meter", by what this equipment measures. */
    public function meterLabel(): string
    {
        return match ($this->meter_type) {
            'km', 'mi' => __('Odometer'),
            'hours' => __('Hour meter'),
            default => __('Meter'),
        };
    }

    /** The unit printed after a reading: km, mi or h. */
    public function meterUnit(): string
    {
        return match ($this->meter_type) {
            'km' => 'km',
            'mi' => 'mi',
            'hours' => 'h',
            default => '',
        };
    }

    public function hasMeter(): bool
    {
        return $this->meter_type !== 'none' && $this->meter_type !== null;
    }

    /** A reading with its unit, or a dash. */
    public function formatMeter(?float $reading): string
    {
        if ($reading === null || ! $this->hasMeter()) {
            return '—';
        }

        return number_format($reading, 1, '.', ',').' '.$this->meterUnit();
    }

    /*
    |---------------------------------------------------------------------------
    | State
    |---------------------------------------------------------------------------
    */

    public function isRetired(): bool
    {
        return in_array($this->status, ['retired', 'sold'], true);
    }

    public function isAssigned(): bool
    {
        return $this->project_id !== null || $this->job_site_id !== null || $this->responsible_user_id !== null;
    }

    public function warrantyState(): ?string
    {
        if (! $this->warranty_until) {
            return null;
        }

        return $this->warranty_until->isPast() ? 'expired' : 'active';
    }

    /** The open maintenance that comes due first, by date. */
    public function nextMaintenance(): ?EquipmentMaintenance
    {
        return $this->maintenances()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderByRaw('CASE WHEN scheduled_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_date')
            ->orderBy('due_meter')
            ->first();
    }

    /**
     * What stands in the way of deleting. An expense is a financial record:
     * with money tagged to it the equipment is retired or marked sold, never
     * deleted.
     *
     * @return array<string, int>
     */
    public function deleteBlockers(): array
    {
        $expenses = $this->expenses()->count();

        return $expenses > 0 ? ['expenses' => $expenses] : [];
    }

    /**
     * The expenses this reader may count: every tagged row for somebody
     * company-wide, their projects' rows (and the company's, with the grant)
     * for somebody confined. Aggregates are lists — a total across projects
     * somebody cannot open is a leak by aggregate.
     */
    public function visibleExpenses(?User $user): Builder
    {
        return Expense::query()
            ->forEquipment($this)
            ->where('status', '!=', 'cancelled')
            ->visibleTo($user);
    }

    /** Purchase cost plus every expense tagged to this equipment that the reader may see. */
    public function totalCostOfOwnership(?User $user = null): float
    {
        $tagged = $this->visibleExpenses($user ?? auth()->user())->sum('total_amount') / 100;

        return round((float) ($this->purchase_cost ?? 0) + $tagged, 2);
    }

    /*
    |---------------------------------------------------------------------------
    | Assignment — where it is, with a log of where it has been
    |---------------------------------------------------------------------------
    */

    /** The open stay, if any. */
    public function currentAssignment(): ?EquipmentAssignment
    {
        return $this->assignments()->open()->first();
    }

    /**
     * Send it to a project, a job site or a person. A job site implies its
     * project. The open stay is closed the day the new one starts, so the log
     * never has two open rows.
     */
    public function assignTo(?int $projectId, ?int $jobSiteId, ?int $userId, string $startedAt, ?string $notes = null): EquipmentAssignment
    {
        if ($jobSiteId) {
            $projectId = JobSite::findOrFail($jobSiteId)->project_id;
        }

        return DB::transaction(function () use ($projectId, $jobSiteId, $userId, $startedAt, $notes) {
            $this->closeOpenAssignment($startedAt);

            $assignment = $this->assignments()->create([
                'project_id' => $projectId,
                'job_site_id' => $jobSiteId,
                'responsible_user_id' => $userId,
                'started_at' => $startedAt,
                'notes' => $notes,
                'assigned_by' => auth()->id(),
            ]);

            $this->update([
                'project_id' => $projectId,
                'job_site_id' => $jobSiteId,
                'responsible_user_id' => $userId,
                'assigned_at' => $startedAt,
            ]);

            $assignment->load(['project', 'jobSite', 'responsible']);
            $this->recordHistory('assigned', ['where' => $assignment->locationLabel()], $assignment);

            return $assignment;
        });
    }

    /** Bring it back: the open stay ends, and it is nowhere until sent again. */
    public function endAssignment(?string $endedAt = null): void
    {
        DB::transaction(function () use ($endedAt) {
            $closed = $this->closeOpenAssignment($endedAt ?? now()->toDateString());

            $this->update([
                'project_id' => null,
                'job_site_id' => null,
                'responsible_user_id' => null,
                'assigned_at' => null,
            ]);

            if ($closed) {
                $this->recordHistory('unassigned', ['where' => $closed->locationLabel()], $closed);
            }
        });
    }

    protected function closeOpenAssignment(string $endedAt): ?EquipmentAssignment
    {
        $open = $this->currentAssignment();

        if ($open) {
            $open->update(['ended_at' => $endedAt, 'ended_by' => auth()->id()]);
            $open->load(['project', 'jobSite', 'responsible']);
        }

        return $open;
    }

    public function recordHistory(string $action, ?array $changes = null, ?Model $subject = null): void
    {
        $this->histories()->create([
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'changed_by' => auth()->id(),
            'changes' => $changes,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('asset_tag', 'like', $like)
                ->orWhere('plate', 'like', $like)
                ->orWhere('serial_number', 'like', $like)
                ->orWhere('make', 'like', $like)
                ->orWhere('model', 'like', $like);
        });
    }

    public function scopeInService(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['retired', 'sold']);
    }

    /** Assigned to this project (any of its sites) or this job site right now. */
    public function scopeAssignedTo(Builder $query, Project|JobSite $scope): Builder
    {
        return $scope instanceof JobSite
            ? $query->where('job_site_id', $scope->id)
            : $query->where('project_id', $scope->id);
    }
}
