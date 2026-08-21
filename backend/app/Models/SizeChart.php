<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SizeChartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable table of body measurements per size, attachable to any number
 * of products (product_size_chart). `rows` is an ordered, free-form array —
 * see the migration's own docblock for why no fixed measurement columns
 * were used.
 *
 * @phpstan-type SizeChartRow array{size: string, measurements: array<string, string>}
 */
class SizeChart extends Model
{
    /** @use HasFactory<SizeChartFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'rows',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_size_chart');
    }
}
