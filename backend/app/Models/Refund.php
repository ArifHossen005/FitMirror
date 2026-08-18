<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The local ledger entry for a refund, independent of whatever
 * SslCommerzService::initiateRefund() returns — kept even if the gateway
 * call itself fails (status = Failed), so a rejected-tenant refund attempt
 * is always auditable, not silently lost when the gateway call errors.
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'amount',
        'reason',
        'gateway_refund_ref',
        'status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
