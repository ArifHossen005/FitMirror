<?php

namespace App\Models;

use Database\Factories\PlanLimitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One countable ceiling per plan — `value = null` means unlimited (see the
 * migration's own comment). Keys in use today: `try_on_sessions_per_day`,
 * `categories`, `skus`, `staff_accounts`, `storage_gb`, `branches`. A key
 * with no row for a given plan is treated identically to `value = null` by
 * PlanService — omission is never a silent zero-cap.
 */
class PlanLimit extends Model
{
    /** @use HasFactory<PlanLimitFactory> */
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUnlimited(): bool
    {
        return $this->value === null;
    }
}
