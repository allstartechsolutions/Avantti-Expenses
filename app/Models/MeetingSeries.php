<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A recurring meeting — "Weekly Site Meeting", "Directors Meeting".
 *
 * The series is what makes carry-forward meaningful: the open items of the
 * site meeting must not land on the agenda of the directors meeting, so
 * "the previous meeting" is always read within one series.
 */
class MeetingSeries extends Model
{
    use SoftDeletes;

    protected $table = 'meeting_series';

    protected $fillable = [
        'name',
        'code',
        'description',
        'cadence',
        'agenda_order',
        'default_location',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'cadence' => 'weekly',
        'agenda_order' => 'last_meeting',
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class)->orderByDesc('meeting_date');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MeetingSeriesMember::class);
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(MeetingSeriesScope::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /** The meeting the next one carries its open items forward from. */
    public function latestMeeting(): ?Meeting
    {
        return $this->meetings()->where('status', '!=', 'cancelled')->first();
    }

    public function getCadenceLabel(): string
    {
        return match ($this->cadence) {
            'weekly' => __('Weekly'),
            'biweekly' => __('Every two weeks'),
            'monthly' => __('Monthly'),
            'quarterly' => __('Quarterly'),
            'ad_hoc' => __('As needed'),
            default => ucfirst($this->cadence),
        };
    }

    /**
     * How agendas built from this series are ordered.
     *
     * Both modes group by project / job site and keep the previous meeting's
     * order inside each group; 'overdue_first' additionally lifts the late
     * rows to the top of their own group. See
     * docs/meetings-agenda-order-plan.md §2.4.
     */
    public function putsOverdueFirst(): bool
    {
        return $this->agenda_order === 'overdue_first';
    }

    public function getAgendaOrderLabel(): string
    {
        return static::agendaOrderLabel($this->agenda_order);
    }

    /**
     * Beside the instance method so a form's options and a filter value can be
     * labelled without a series in hand.
     */
    public static function agendaOrderLabel(?string $value): string
    {
        return match ($value) {
            'last_meeting' => __("Last meeting's order"),
            'overdue_first' => __("Past due first, then last meeting's order"),
            default => (string) $value,
        };
    }

    /**
     * The date a follow-up would fall on. A suggestion for the form — nothing
     * is ever scheduled automatically.
     */
    public function suggestNextDate(?\Illuminate\Support\Carbon $from = null): ?\Illuminate\Support\Carbon
    {
        $from = $from ?? $this->latestMeeting()?->meeting_date ?? now();

        return match ($this->cadence) {
            'weekly' => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'monthly' => $from->copy()->addMonth(),
            'quarterly' => $from->copy()->addMonths(3),
            default => null,
        };
    }
}
