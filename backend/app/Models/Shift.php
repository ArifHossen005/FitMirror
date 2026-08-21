<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One staff member rostered at one branch for one wall-clock window on one
 * date. Overnight shifts are expressed by `ends_at` being earlier than
 * `starts_at` (a 22:00–06:00 shift), which is why the absolute instants
 * are derived here in startsAtInstant()/endsAtInstant() rather than being
 * two DATETIME columns — a manager building next week's roster thinks in
 * "Saturday, 22:00 to 06:00", not in UTC instants.
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The longest single shift the scheduler accepts. Anything longer is
     * far more likely to be a typo (an am/pm mix-up) than a real roster
     * entry, and an unbounded shift would make the overlap check
     * meaningless.
     */
    public const MAX_DURATION_HOURS = 16;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',
        'shift_date',
        'starts_at',
        'ends_at',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'status' => ShiftStatus::class,
        ];
    }

    /**
     * withTrashed(): Store is soft-deleted, and a shift on a removed branch
     * must still resolve to the branch it was rostered at — every caller
     * authorises through this relation, and a null store would raise a
     * TypeError in the policy rather than producing a clean refusal.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<Shift> $query
     * @return Builder<Shift>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::Scheduled->value);
    }

    /**
     * @param Builder<Shift> $query
     * @return Builder<Shift>
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('shift_date', [$from, $to]);
    }

    public function startsAtInstant(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->shift_date->format('Y-m-d') . ' ' . $this->starts_at);
    }

    /**
     * Rolls to the following calendar day whenever the end time is at or
     * before the start time — the overnight case described on this class.
     */
    public function endsAtInstant(): CarbonImmutable
    {
        $end = CarbonImmutable::parse($this->shift_date->format('Y-m-d') . ' ' . $this->ends_at);

        return $end->lessThanOrEqualTo($this->startsAtInstant()) ? $end->addDay() : $end;
    }

    public function durationMinutes(): int
    {
        return (int) $this->startsAtInstant()->diffInMinutes($this->endsAtInstant());
    }

    /**
     * True when this shift and $other cover any of the same time. Used by
     * ShiftService to reject double-booking one staff member, including
     * across the midnight boundary an overnight shift crosses.
     */
    public function overlaps(self $other): bool
    {
        return $this->startsAtInstant()->lt($other->endsAtInstant())
            && $other->startsAtInstant()->lt($this->endsAtInstant());
    }
}
