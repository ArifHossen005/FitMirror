<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CouponRedemptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per invoice a coupon was applied to — created when the invoice
 * is created (App\Services\Billing\PaymentService::resolvePendingInvoice()),
 * not when payment succeeds, since `invoice_id` is unique per coupon
 * application and a retried checkout reuses the same still-Pending invoice
 * (never creates a second redemption row for one retry).
 */
class CouponRedemption extends Model
{
    /** @use HasFactory<CouponRedemptionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'coupon_id',
        'tenant_id',
        'invoice_id',
        'amount_discounted',
    ];

    protected function casts(): array
    {
        return [
            'amount_discounted' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
