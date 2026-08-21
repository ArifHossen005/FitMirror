<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PriceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable audit row recording a single price field change on a
 * product or one of its variants — written by App\Services\Product\
 * PriceHistoryService::record(), never updated after creation. No
 * `updated_at`: UPDATED_AT is nulled out below because an audit trail entry
 * that could later "have an updated_at" would defeat the point of it.
 */
class PriceHistory extends Model
{
    /** @use HasFactory<PriceHistoryFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'price_history';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'variant_id',
        'field',
        'old_value',
        'new_value',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
