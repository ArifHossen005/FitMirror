<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Addon;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use App\Support\TaxCalculator;
use Illuminate\Validation\ValidationException;

/**
 * Add-on checkout — reuses GatewayCheckoutService (the same SSLCommerz
 * session-initiate pipeline PaymentService uses for plan purchases), but
 * creates its own Invoice directly rather than going through
 * PaymentService::resolvePendingInvoice(), since add-ons have no
 * Subscription, no billing cycle, and (deliberately, to keep this phase's
 * scope bounded) no coupon support — see PROGRESS.md Phase 3.D.
 */
class AddonPurchaseService extends BaseService
{
    public function __construct(
        private readonly InvoiceNumberGenerator $invoiceNumbers,
        private readonly GatewayCheckoutService $checkout,
    ) {}

    /**
     * @return array{invoice: Invoice, gateway_url: string}
     */
    public function initiate(Tenant $tenant, User $owner, Addon $addon, int $quantity = 1): array
    {
        if (!$addon->isPurchasable()) {
            throw ValidationException::withMessages(['addon' => ['This add-on is not currently available.']]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be at least 1.']]);
        }

        $invoice = $this->transaction(function () use ($tenant, $addon, $quantity) {
            $subtotal = $addon->price * $quantity;
            $vat = TaxCalculator::vatFor($subtotal);

            $invoice = Invoice::query()->create([
                'tenant_id' => $tenant->id,
                'addon_id' => $addon->id,
                'number' => $this->invoiceNumbers->next(),
                'subtotal' => $subtotal,
                'discount' => 0,
                'vat' => $vat,
                'total' => $subtotal + $vat,
                'currency' => $addon->currency,
                'status' => InvoiceStatus::Pending,
                'issued_at' => now(),
                'due_at' => now()->addDays(3),
            ]);

            $invoice->items()->create([
                'description' => "{$addon->name} × {$quantity}",
                'qty' => $quantity,
                'unit_price' => $addon->price,
                'total' => $subtotal,
            ]);

            return $invoice;
        });

        $result = $this->checkout->beginCheckout($tenant, $owner, $invoice, $addon->name);

        return ['invoice' => $invoice, 'gateway_url' => $result['gateway_url']];
    }
}
