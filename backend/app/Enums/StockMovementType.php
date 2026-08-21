<?php

namespace App\Enums;

/**
 * Purely descriptive — `stock_movements.quantity` already carries the
 * signed delta, so nothing reads direction out of this. Adjustment changes
 * the tenant-wide aggregate (`product_variants.stock`); a TransferOut/
 * TransferIn pair does not, since a transfer only redistributes existing
 * stock between branches. See App\Services\Inventory\StockService.
 */
enum StockMovementType: string
{
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    public function label(): string
    {
        return match ($this) {
            self::Adjustment => 'Adjustment',
            self::TransferOut => 'Transfer Out',
            self::TransferIn => 'Transfer In',
        };
    }
}
