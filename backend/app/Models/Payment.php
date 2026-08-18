<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per gateway attempt against an Invoice — a retried checkout
 * creates a new Payment (new `gateway_txn_id`) against the same still-
 * Pending Invoice, never overwrites the failed one, so every attempt is
 * independently reconcilable against SSLCommerz's own records.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'gateway',
        'gateway_txn_id',
        'val_id',
        'amount',
        'currency',
        'method',
        'status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Same target as invoice(), but explicitly bypassing TenantScope. Every
     * caller in the Phase 3.C payment flow (gateway success/fail/cancel
     * callbacks, the IPN webhook, Mission Control's manual-payment and
     * refund actions) runs with no ambient TenantContext of its own —
     * gateway callbacks carry no FitMirror session to resolve one from,
     * and Mission Control's super_admin guard never resolves one either —
     * so the plain invoice() relation's own query would inherit Invoice's
     * TenantScope and silently return null (fails closed, per Decision
     * D-13), even though $this (the Payment) was itself already correctly
     * looked up via withoutTenantScope(). Named separately from invoice()
     * so a normal, correctly-scoped tenant-facing caller (Phase 3.D's
     * invoice listing, made from inside an authenticated tenant request)
     * is never tempted to reach for the bypassed version by accident.
     */
    public function invoiceUnscoped(): Invoice
    {
        return Invoice::withoutTenantScope()->findOrFail($this->invoice_id);
    }

    /**
     * Merges $data under $key into the existing raw_payload JSON rather
     * than overwriting it — every gateway round trip for this payment
     * (session initiate, order validation, the success/fail/cancel
     * callback body, IPN replays) is kept, per the Phase 3.C checklist's
     * "persist every gateway payload for reconciliation and disputes".
     * Callers still need to ->save() afterwards; this only mutates the
     * in-memory attribute.
     *
     * @param array<string, mixed> $data
     */
    public function appendRawPayload(string $key, array $data): void
    {
        $payload = $this->raw_payload ?? [];
        $payload[$key] = $data;
        $this->raw_payload = $payload;
    }
}
