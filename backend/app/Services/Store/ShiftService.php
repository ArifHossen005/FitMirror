<?php

namespace App\Services\Store;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Services\BaseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Staff rostering. The rules that cannot live in a form request — because
 * each needs the tenant's other shifts, or the staff member's tenancy —
 * are all here: a staff member belongs to the tenant they are being
 * rostered for, a shift has a sane duration, and nobody is booked into two
 * places at once.
 *
 * Overlap is checked against absolute instants derived by the model
 * (Shift::overlaps()), not against the raw TIME columns, so an overnight
 * shift really does conflict with the early hours of the next morning
 * rather than looking disjoint because the dates differ.
 */
class ShiftService extends BaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Store $store, User $creator, array $data): Shift
    {
        $staff = $this->resolveStaff($store, (int) $data['user_id']);

        $shift = new Shift([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'user_id' => $staff->id,
            'shift_date' => $data['shift_date'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => ShiftStatus::Scheduled->value,
            'note' => $data['note'] ?? null,
            'created_by' => $creator->id,
        ]);

        $this->assertDurationIsSane($shift);
        $this->assertNoOverlap($shift);

        $shift->save();

        return $shift;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Shift $shift, array $data): Shift
    {
        if (array_key_exists('user_id', $data)) {
            $staff = $this->resolveStaff($shift->store, (int) $data['user_id']);
            $data['user_id'] = $staff->id;
        }

        // Filled on a copy first: the overlap check must run against the
        // *proposed* shift, and mutating the real row before validating it
        // would leave a rejected edit half-applied in memory for anything
        // that later read $shift.
        $proposed = $shift->replicate();
        $proposed->id = $shift->id;
        $proposed->fill($data);

        if ($proposed->status === ShiftStatus::Scheduled) {
            $this->assertDurationIsSane($proposed);
            $this->assertNoOverlap($proposed, ignoreShiftId: $shift->id);
        }

        $shift->fill($data)->save();

        return $shift->refresh();
    }

    public function cancel(Shift $shift): Shift
    {
        if ($shift->status === ShiftStatus::Cancelled) {
            throw ValidationException::withMessages([
                'shift' => ['This shift is already cancelled.'],
            ]);
        }

        $shift->forceFill(['status' => ShiftStatus::Cancelled->value])->save();

        return $shift;
    }

    public function delete(Shift $shift): void
    {
        $shift->delete();
    }

    /**
     * The roster for a date range, optionally narrowed to one branch or one
     * staff member. Eager-loads both sides so the weekly grid renders from
     * a single round trip rather than N+1 per cell.
     *
     * @return Collection<int, Shift>
     */
    public function schedule(string $from, string $to, ?int $storeId = null, ?int $userId = null): Collection
    {
        return Shift::query()
            ->betweenDates($from, $to)
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->with(['staff:id,name,email', 'store:id,name,code'])
            ->orderBy('shift_date')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The staff member must belong to the same tenant as the branch. User
     * carries TenantScope, so a cross-tenant id already resolves to null
     * here — the explicit tenant_id comparison is defence in depth for the
     * case where this is ever called from a context with a different
     * ambient tenant (a queued job, an artisan command).
     */
    private function resolveStaff(Store $store, int $userId): User
    {
        $staff = User::query()->find($userId);

        if (!$staff instanceof User || $staff->tenant_id !== $store->tenant_id) {
            throw ValidationException::withMessages([
                'user_id' => ['That staff member does not belong to this shop.'],
            ]);
        }

        return $staff;
    }

    private function assertDurationIsSane(Shift $shift): void
    {
        $minutes = $shift->durationMinutes();

        if ($minutes <= 0) {
            throw ValidationException::withMessages([
                'ends_at' => ['A shift must be longer than zero minutes.'],
            ]);
        }

        if ($minutes > Shift::MAX_DURATION_HOURS * 60) {
            throw ValidationException::withMessages([
                'ends_at' => [
                    'A shift cannot be longer than ' . Shift::MAX_DURATION_HOURS . ' hours. Split it into two.',
                ],
            ]);
        }
    }

    /**
     * Compares against every scheduled shift for the same staff member on
     * the day before, of, and after — a window wide enough to catch both
     * ends of an overnight shift without loading their whole roster.
     */
    private function assertNoOverlap(Shift $candidate, ?int $ignoreShiftId = null): void
    {
        // shift_date is cast to a date, so it is always a Carbon instance
        // by the time it is read back — even on an unsaved model built from
        // a raw 'Y-m-d' string in create().
        $date = CarbonImmutable::instance($candidate->shift_date);

        $neighbours = Shift::query()
            ->scheduled()
            ->where('user_id', $candidate->user_id)
            ->betweenDates($date->subDay()->format('Y-m-d'), $date->addDay()->format('Y-m-d'))
            ->when($ignoreShiftId !== null, fn ($query) => $query->whereKeyNot($ignoreShiftId))
            ->get();

        foreach ($neighbours as $neighbour) {
            if ($candidate->overlaps($neighbour)) {
                throw ValidationException::withMessages([
                    'starts_at' => [
                        'This staff member is already rostered from '
                        . $neighbour->startsAtInstant()->format('D H:i')
                        . ' to ' . $neighbour->endsAtInstant()->format('D H:i') . '.',
                    ],
                ]);
            }
        }
    }
}
