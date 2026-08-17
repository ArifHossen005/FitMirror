<?php

namespace Database\Seeders;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Free/Pro/Max, seeded exactly from the product document's §15
 * "Subscription Plan Comparison" table (FitMirror_Full_Product_Doc_v2.docx).
 * Yearly price = monthly x 12 x 0.8 (the 20% annual discount referenced by
 * PROGRESS.md Phase 3.E's "monthly / yearly with 20% discount" checklist
 * item), rounded down to the nearest taka.
 *
 * Idempotent via updateOrCreate() — safe to re-run if a price or limit
 * changes; Mission Control's own plan-editing UI (Phase 13) is the
 * intended long-term way to change these without a redeploy, per the
 * product document's own note ("সব limit Mission Control থেকে... code
 * change ছাড়াই... পরিবর্তন").
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlan(
            name: 'Free',
            slug: 'free',
            priceMonthly: 0,
            trialDays: 0,
            sortOrder: 1,
            limits: [
                'try_on_sessions_per_day' => 50,
                'categories' => 2,
                'skus' => 50,
                'staff_accounts' => 1,
                'storage_gb' => 5,
                'branches' => 1,
            ],
            features: [
                'campaign_manager' => [false, null],
                'loyalty_program' => [false, null],
                'social_media_post' => [false, null],
                'analytics' => [true, 'basic'],
                'custom_branding' => [false, null],
                'api_access' => [false, null],
                'sslcommerz_payment' => [false, null],
            ],
        );

        $this->seedPlan(
            name: 'Pro',
            slug: 'pro',
            priceMonthly: 499,
            trialDays: 14,
            sortOrder: 2,
            limits: [
                'try_on_sessions_per_day' => 500,
                'categories' => 10,
                'skus' => 500,
                'staff_accounts' => 5,
                'storage_gb' => 50,
                'branches' => 3,
            ],
            features: [
                'campaign_manager' => [true, 'basic'],
                'loyalty_program' => [true, 'standard'],
                'social_media_post' => [true, 'manual'],
                'analytics' => [true, 'advanced'],
                'custom_branding' => [true, 'logo'],
                'api_access' => [false, null],
                'sslcommerz_payment' => [true, null],
            ],
        );

        $this->seedPlan(
            name: 'Max',
            slug: 'max',
            priceMonthly: 1299,
            trialDays: 14,
            sortOrder: 3,
            limits: [
                'try_on_sessions_per_day' => null,
                'categories' => null,
                'skus' => null,
                'staff_accounts' => null,
                'storage_gb' => 500,
                'branches' => null,
            ],
            features: [
                'campaign_manager' => [true, 'full_ai'],
                'loyalty_program' => [true, 'custom'],
                'social_media_post' => [true, 'auto'],
                'analytics' => [true, 'full_ai'],
                'custom_branding' => [true, 'white_label'],
                'api_access' => [true, null],
                'sslcommerz_payment' => [true, null],
            ],
        );
    }

    /**
     * @param array<string, int|null> $limits
     * @param array<string, array{0: bool, 1: string|null}> $features
     */
    private function seedPlan(
        string $name,
        string $slug,
        int $priceMonthly,
        int $trialDays,
        int $sortOrder,
        array $limits,
        array $features,
    ): void {
        $plan = Plan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'price_monthly' => $priceMonthly,
                'price_yearly' => (int) floor($priceMonthly * 12 * 0.8),
                'currency' => 'BDT',
                'trial_days' => $trialDays,
                'is_public' => true,
                'sort_order' => $sortOrder,
                'status' => PlanStatus::Active,
            ],
        );

        foreach ($limits as $key => $value) {
            $plan->limits()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        foreach ($features as $featureKey => [$enabled, $tier]) {
            $plan->features()->updateOrCreate(
                ['feature_key' => $featureKey],
                ['enabled' => $enabled, 'meta' => $tier ? ['tier' => $tier] : null],
            );
        }
    }
}
