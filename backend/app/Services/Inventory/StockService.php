<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The stock ledger: `stock_movements` is the source of truth for *where*
 * a variant's units physically are, `product_variants.stock` is a
 * denormalised tenant-wide total kept in sync alongside it. Deliberately
 * bounded scope, recorded so it isn't rediscovered: a low-stock threshold
 * and the "auto-hide" rule (Product::scopeVisibleInCatalog()) both compare
 * against the tenant-wide aggregate, not a per-(variant, store) on-hand
 * figure — the product document's own wording ("per-variant low-stock
 * threshold") never asked for a per-branch one, and adding that axis now
 * would be speculative complexity for a rule nothing yet needs.
 */
class StockService extends BaseService
{
    /**
     * A plain count correction at one branch — receiving new stock,
     * writing off damaged units, fixing a miscount. $quantity is the
     * signed delta; negative decreases on-hand and cannot take a store's
     * on-hand for this variant below zero.
     */
    public function adjust(ProductVariant $variant, Store $store, int $quantity, ?string $note, ?User $actor): StockMovement
    {
        if ($quantity === 0) {
            throw ValidationException::withMessages(['quantity' => ['The adjustment quantity cannot be zero.']]);
        }

        if ($quantity < 0 && $this->onHandAt($variant, $store) + $quantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => ['This would take on-hand stock at this branch below zero.'],
            ]);
        }

        return $this->transaction(function () use ($variant, $store, $quantity, $note, $actor) {
            $movement = StockMovement::query()->create([
                'tenant_id' => $variant->tenant_id,
                'variant_id' => $variant->id,
                'store_id' => $store->id,
                'type' => StockMovementType::Adjustment->value,
                'quantity' => $quantity,
                'note' => $note,
                'user_id' => $actor?->id,
            ]);

            $variant->increment('stock', $quantity);

            return $movement;
        });
    }

    /**
     * Moves $quantity (always positive) of $variant from one branch to
     * another — the tenant-wide aggregate is untouched, since nothing was
     * gained or lost, only relocated. Writes a matched TransferOut/
     * TransferIn pair sharing one `reference` so the two halves can be
     * found together.
     *
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function transfer(ProductVariant $variant, Store $from, Store $to, int $quantity, ?string $note, ?User $actor): array
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_store_id' => ['Choose a different branch to transfer into.']]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['The transfer quantity must be greater than zero.']]);
        }

        if ($this->onHandAt($variant, $from) < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['This branch does not have enough on-hand stock for this transfer.'],
            ]);
        }

        return $this->transaction(function () use ($variant, $from, $to, $quantity, $note, $actor) {
            $reference = (string) Str::uuid();

            $out = StockMovement::query()->create([
                'tenant_id' => $variant->tenant_id,
                'variant_id' => $variant->id,
                'store_id' => $from->id,
                'type' => StockMovementType::TransferOut->value,
                'quantity' => -$quantity,
                'reference' => $reference,
                'note' => $note,
                'user_id' => $actor?->id,
            ]);

            $in = StockMovement::query()->create([
                'tenant_id' => $variant->tenant_id,
                'variant_id' => $variant->id,
                'store_id' => $to->id,
                'type' => StockMovementType::TransferIn->value,
                'quantity' => $quantity,
                'reference' => $reference,
                'note' => $note,
                'user_id' => $actor?->id,
            ]);

            return ['out' => $out, 'in' => $in];
        });
    }

    /**
     * The real on-hand quantity for $variant at $store — the SUM of every
     * movement recorded there, never a cached column.
     */
    public function onHandAt(ProductVariant $variant, Store $store): int
    {
        return (int) StockMovement::query()
            ->where('variant_id', $variant->id)
            ->where('store_id', $store->id)
            ->sum('quantity');
    }

    /**
     * Per-branch on-hand breakdown for $variant, one row per store that
     * has ever had a movement for it (a branch with zero movements is
     * omitted rather than listed at zero — it has simply never stocked
     * this variant).
     *
     * @return array<int, array{store_id: int, store_name: string, on_hand: int}>
     */
    public function breakdownByStore(ProductVariant $variant): array
    {
        // A plain query-builder aggregate, not an Eloquent get() — SUM(...)
        // AS on_hand is a synthetic column no StockMovement row actually
        // has, and grouped rows have no meaningful primary key either;
        // hydrating them as models would fight Eloquent's own assumptions
        // for no benefit, since nothing here needs model behaviour.
        return DB::table('stock_movements')
            ->join('stores', 'stores.id', '=', 'stock_movements.store_id')
            ->where('stock_movements.variant_id', $variant->id)
            ->groupBy('stock_movements.store_id', 'stores.name')
            ->selectRaw('stock_movements.store_id, stores.name as store_name, SUM(stock_movements.quantity) as on_hand')
            ->get()
            ->map(fn (object $row) => [
                'store_id' => (int) $row->store_id,
                'store_name' => (string) $row->store_name,
                'on_hand' => (int) $row->on_hand,
            ])
            ->all();
    }
}
