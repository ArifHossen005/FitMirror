<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\StoreHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekday's trading window for one branch, plus the (optionally
 * narrower) window during which a paired kiosk may run try-on sessions.
 *
 * Times are wall clock in the store's own timezone (`stores.timezone`) and
 * are stored as TIME, never DATETIME — see the migration's own comment.
 * All comparison logic lives here rather than in the service so the kiosk
 * guard, the dashboard editor, and the "is the kiosk open right now"
 * endpoint can never drift apart in how they read the same row.
 */
class StoreHour extends Model
{
    /** @use HasFactory<StoreHourFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Carbon::dayOfWeek ordering — index 0 is Sunday.
     *
     * @var list<string>
     */
    public const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = [
        'tenant_id',
        'store_id',
        'day_of_week',
        'is_closed',
        'opens_at',
        'closes_at',
        'kiosk_opens_at',
        'kiosk_closes_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function dayName(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * The kiosk window, falling back to the branch's own trading hours
     * whenever the tenant has not narrowed it — leaving the kiosk times
     * blank means "whenever we are open", not "never".
     *
     * @return array{0: string, 1: string}|null Null when the branch is shut this day.
     */
    public function kioskWindow(): ?array
    {
        if ($this->is_closed || $this->opens_at === null || $this->closes_at === null) {
            return null;
        }

        return [
            $this->kiosk_opens_at ?? $this->opens_at,
            $this->kiosk_closes_at ?? $this->closes_at,
        ];
    }

    /**
     * Whether $at (already converted to the branch's timezone by the
     * caller) falls inside this day's kiosk window.
     *
     * An overnight window — closing time earlier than opening time, e.g. a
     * mall kiosk running 18:00 to 02:00 — is read as wrapping past
     * midnight rather than as an empty range, which a naive
     * `>= open && < close` comparison would produce.
     */
    public function kioskIsOpenAt(CarbonImmutable $at): bool
    {
        $window = $this->kioskWindow();

        if ($window === null) {
            return false;
        }

        [$opensAt, $closesAt] = $window;
        $now = $at->format('H:i:s');

        if ($opensAt === $closesAt) {
            // Equal endpoints mean a full 24-hour window, the only reading
            // that makes an explicitly configured row meaningful — an empty
            // window is expressed by is_closed instead.
            return true;
        }

        return $opensAt < $closesAt
            ? ($now >= $opensAt && $now < $closesAt)
            : ($now >= $opensAt || $now < $closesAt);
    }
}
