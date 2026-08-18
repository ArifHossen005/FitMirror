<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One invoice per billing event — a plan purchase (`subscription_id` set,
 * `addon_id` null) or an add-on purchase (`addon_id` set, `subscription_id`
 * null; Phase 3.D). The two are mutually exclusive; see PaymentService::
 * finalizeInvoice()'s docblock for how a verified payment tells them apart.
 * `number` is assigned by App\Services\Billing\InvoiceNumberGenerator at
 * creation time, never left blank.
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'addon_id',
        'number',
        'subtotal',
        'discount',
        'vat',
        'total',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'vat' => 'integer',
            'total' => 'integer',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
