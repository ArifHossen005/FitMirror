<?php

namespace App\Services\Billing;

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Addon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use App\Services\BaseService;
use App\Support\TaxCalculator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Phase 3.C/3.D payment flow: initiate (redirect to SSLCommerz) →
 * success/fail/cancel callback or IPN → verified payment moves the
 * tenant's Subscription from PendingPayment to PendingApproval (see
 * SubscriptionStatus's transition graph — no new states were needed,
 * PendingPayment/PendingApproval already existed from Phase 3.B). Tenant
 * status is deliberately left untouched here: TenantStatus::Pending
 * already *is* the "pending_approval" state the product document's §1.4
 * flow diagram describes (see its own docblock) — registration sets it,
 * and only a future Mission Control approval action (Phase 13) moves it
 * to Active.
 *
 * Scope is deliberately narrow: a tenant with any subscription already in
 * a usable-or-pending-approval state cannot initiate another one through
 * this service. Upgrade/downgrade is still Phase 3.B's SKIPPED work —
 * Phase 3.D gives it a real invoicing ledger to compute proration against,
 * but the proration math itself needs a real subscription period end date
 * (`subscriptions.ends_at`), which nothing populates yet (that's the
 * still-unbuilt renewal job's job) — building "proration" against a period
 * end that's always null would just be fabricating a number, not a real
 * feature. Left for whichever phase builds the renewal job.
 *
 * The gateway round trip itself (Payment row + SSLCommerz session) is
 * delegated to GatewayCheckoutService, shared with AddonPurchaseService —
 * this class owns pricing (coupons, VAT) and post-payment finalization
 * (activating a Subscription or crediting a TenantAddon balance).
 */
class PaymentService extends BaseService
{
    private const BLOCKING_STATUSES = [
        SubscriptionStatus::Trialing,
        SubscriptionStatus::Active,
        SubscriptionStatus::PendingApproval,
        SubscriptionStatus::PastDue,
        SubscriptionStatus::Grace,
    ];

    public function __construct(
        private readonly SslCommerzService $gateway,
        private readonly InvoiceNumberGenerator $invoiceNumbers,
        private readonly GatewayCheckoutService $checkout,
        private readonly CouponService $coupons,
    ) {}

    /**
     * @return array{invoice: Invoice, payment: Payment, gateway_url: string}
     */
    public function initiate(
        Tenant $tenant,
        User $owner,
        Plan $plan,
        BillingCycle $cycle,
        ?string $couponCode = null,
    ): array {
        $this->assertPlanIsPurchasable($plan);
        $this->assertNoBlockingSubscription($tenant);

        [, $invoice] = $this->resolvePendingInvoice($tenant, $plan, $cycle, $couponCode);

        $result = $this->checkout->beginCheckout(
            $tenant,
            $owner,
            $invoice,
            "{$plan->name} plan — {$cycle->label()} subscription",
        );

        return ['invoice' => $invoice, 'payment' => $result['payment'], 'gateway_url' => $result['gateway_url']];
    }

    /**
     * Called from the success callback and the IPN webhook alike — both
     * paths converge here so a payment is verified and activated exactly
     * once no matter which one (or both, in either order) actually
     * arrives. Idempotent: a payment already marked Success short-circuits
     * without re-validating or re-transitioning anything, satisfying the
     * checklist's "IPN replay" requirement.
     *
     * @param array<string, mixed> $callbackPayload
     *
     * @throws PaymentGatewayException if validation fails or the
     *                                 validated amount doesn't match what this payment was created for.
     */
    public function verifyAndMarkSuccess(Payment $payment, string $valId, array $callbackPayload): Payment
    {
        if ($payment->status === PaymentStatus::Success) {
            return $payment;
        }

        $validation = $this->gateway->validateTransaction($valId);

        $validatedAmount = isset($validation['amount']) ? (int) round((float) $validation['amount']) : null;
        $statusIsValid = in_array($validation['status'] ?? null, ['VALID', 'VALIDATED'], true);

        if (!$statusIsValid || $validatedAmount !== $payment->amount) {
            $payment->appendRawPayload('failed_validation', $validation);
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            throw new PaymentGatewayException(
                'SSLCommerz order validation failed or the validated amount did not match the invoice.',
            );
        }

        return $this->transaction(function () use ($payment, $valId, $validation, $callbackPayload) {
            $payment->appendRawPayload('success_callback', $callbackPayload);
            $payment->appendRawPayload('validation', $validation);
            $payment->forceFill([
                'status' => PaymentStatus::Success,
                'val_id' => $valId,
                'method' => $validation['card_type'] ?? $validation['card_issuer'] ?? null,
            ])->save();

            $this->finalizeInvoice($payment->invoiceUnscoped());

            return $payment;
        });
    }

    /**
     * Idempotent for the same reason as verifyAndMarkSuccess() — a
     * payment already in a terminal state is left alone.
     *
     * @param array<string, mixed> $callbackPayload
     */
    public function markFailed(Payment $payment, array $callbackPayload): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        $payment->appendRawPayload('fail_callback', $callbackPayload);
        $payment->forceFill(['status' => PaymentStatus::Failed])->save();

        return $payment;
    }

    /**
     * @param array<string, mixed> $callbackPayload
     */
    public function markCancelled(Payment $payment, array $callbackPayload): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        $payment->appendRawPayload('cancel_callback', $callbackPayload);
        $payment->forceFill(['status' => PaymentStatus::Cancelled])->save();

        return $payment;
    }

    /**
     * Mission Control's offline/manual payment recording — bank transfer,
     * cash, or any payment collected outside SSLCommerz. Same Invoice/
     * Subscription state machine as a real gateway payment, minus the
     * gateway round trip: the super admin recording it *is* the proof of
     * payment, the same way an SSLCommerz-verified val_id is.
     *
     * $amount, when given, is taken as the final agreed total exactly as
     * typed by Finance — no coupon or VAT pipeline runs on it (a manually
     * negotiated deal isn't list-price-minus-coupon, and Finance is
     * trusted to already know what they're charging), unlike the online
     * checkout path in initiate().
     */
    public function recordOffline(
        Tenant $tenant,
        Plan $plan,
        BillingCycle $cycle,
        ?int $amount,
        SuperAdmin $recordedBy,
        ?string $note,
    ): Payment {
        $this->assertPlanIsPurchasable($plan);
        $this->assertNoBlockingSubscription($tenant);

        $amount ??= $this->priceFor($plan, $cycle);

        return $this->transaction(function () use ($tenant, $plan, $cycle, $amount, $recordedBy, $note) {
            [, $invoice] = $this->resolvePendingInvoice($tenant, $plan, $cycle, null, $amount);

            $payment = Payment::query()->create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'gateway' => PaymentGateway::Manual,
                'gateway_txn_id' => $this->generateOfflineTranId($invoice),
                'amount' => $amount,
                'currency' => $invoice->currency,
                'method' => 'offline',
                'status' => PaymentStatus::Success,
                'raw_payload' => [
                    'recorded_by_super_admin_id' => $recordedBy->id,
                    'recorded_by_email' => $recordedBy->email,
                    'note' => $note,
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]);

            $this->finalizeInvoice($invoice);

            return $payment;
        });
    }

    /**
     * Marks $invoice Paid and activates whatever it paid for — a
     * Subscription (`subscription_id` set: `PendingPayment →
     * PendingApproval`) or a TenantAddon balance credit (`addon_id` set).
     * The two are mutually exclusive by construction (resolvePendingInvoice()
     * only ever sets one; AddonPurchaseService only ever sets the other),
     * so both branches are checked but at most one ever does anything.
     * Always dispatches PDF generation + email delivery — every paid
     * invoice gets one, plan or add-on alike.
     */
    private function finalizeInvoice(Invoice $invoice): void
    {
        $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

        if ($invoice->subscription_id !== null) {
            $subscription = Subscription::withoutTenantScope()->find($invoice->subscription_id);

            if ($subscription && $subscription->canTransitionTo(SubscriptionStatus::PendingApproval)) {
                $subscription->transitionTo(SubscriptionStatus::PendingApproval);
            }
        }

        if ($invoice->addon_id !== null) {
            $this->creditTenantAddonBalance($invoice);
        }

        GenerateInvoicePdfJob::dispatch($invoice->id);
    }

    private function creditTenantAddonBalance(Invoice $invoice): void
    {
        $addon = Addon::query()->find($invoice->addon_id);

        if (!$addon) {
            return;
        }

        $quantity = (int) ($invoice->items()->sum('qty') ?: 1);

        TenantAddon::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'addon_id' => $addon->id,
            'invoice_id' => $invoice->id,
            'remaining_balance' => $addon->unit_amount * $quantity,
            'purchased_at' => now(),
        ]);
    }

    /**
     * Reuses an existing PendingPayment subscription + Pending invoice
     * for this tenant/plan/cycle if a prior attempt already created one
     * (a retried checkout after a failed session initiate, or a second
     * "Pay Now" click) instead of creating a fresh row every time —
     * mirrors the same reuse pattern StaffInvitationService uses for
     * pending invitations.
     *
     * $subtotalOverride, when given (recordOffline() only), skips the
     * coupon/VAT pipeline entirely — see recordOffline()'s own docblock.
     *
     * @return array{0: Subscription, 1: Invoice}
     */
    private function resolvePendingInvoice(
        Tenant $tenant,
        Plan $plan,
        BillingCycle $cycle,
        ?string $couponCode,
        ?int $subtotalOverride = null,
    ): array {
        $subscription = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('plan_id', $plan->id)
            ->where('billing_cycle', $cycle)
            ->where('status', SubscriptionStatus::PendingPayment)
            ->first();

        if (!$subscription) {
            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'status' => SubscriptionStatus::PendingPayment,
                'auto_renew' => true,
            ]);
        }

        $invoice = Invoice::withoutTenantScope()
            ->where('subscription_id', $subscription->id)
            ->where('status', InvoiceStatus::Pending)
            ->first();

        if (!$invoice) {
            if ($subtotalOverride !== null) {
                $subtotal = $subtotalOverride;
                $discount = 0;
                $vat = 0;
                $coupon = null;
            } else {
                $subtotal = $this->priceFor($plan, $cycle);
                $preview = $this->coupons->preview($couponCode, $tenant, $plan, $subtotal);
                $discount = $preview['discount'];
                $vat = TaxCalculator::vatFor($subtotal - $discount);
                $coupon = $preview['coupon'];
            }

            $invoice = Invoice::query()->create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'number' => $this->invoiceNumbers->next(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'vat' => $vat,
                'total' => $subtotal - $discount + $vat,
                'currency' => $plan->currency,
                'status' => InvoiceStatus::Pending,
                'issued_at' => now(),
                'due_at' => now()->addDays(3),
            ]);

            $invoice->items()->create([
                'description' => "{$plan->name} plan — {$cycle->label()} subscription",
                'qty' => 1,
                'unit_price' => $subtotal,
                'total' => $subtotal,
            ]);

            if ($coupon) {
                $this->coupons->redeem($coupon, $tenant, $invoice, $discount);
            }
        }

        return [$subscription, $invoice];
    }

    private function priceFor(Plan $plan, BillingCycle $cycle): int
    {
        return $cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly;
    }

    private function assertPlanIsPurchasable(Plan $plan): void
    {
        if (!$plan->isUsable()) {
            throw ValidationException::withMessages([
                'plan' => ['This plan is not currently available for purchase.'],
            ]);
        }
    }

    private function assertNoBlockingSubscription(Tenant $tenant): void
    {
        $hasBlocking = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->exists();

        if ($hasBlocking) {
            throw ValidationException::withMessages([
                'tenant' => [
                    'This tenant already has an active, trialing, or awaiting-approval subscription. '
                    . 'Plan changes are not available yet.',
                ],
            ]);
        }
    }

    private function generateOfflineTranId(Invoice $invoice): string
    {
        return 'MANUAL' . now()->format('YmdHis') . Str::upper(Str::random(6)) . $invoice->id;
    }
}
